<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class UserRepositoryTest extends KernelTestCase
{
    private UserRepository $repository;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $repository = static::getContainer()->get(UserRepository::class);
        \assert($repository instanceof UserRepository);
        $this->repository = $repository;

        $em = static::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);
        $this->em = $em;
    }

    public function testUpgradePasswordStoresTheNewHash(): void
    {
        $user = (new User())
            ->setEmail('upgrade.'.uniqid().'@example.com')
            ->setName('Upgrade')
            ->setRoles(['ROLE_USER'])
            ->setPassword('old-hash');
        $this->em->persist($user);
        $this->em->flush();

        $this->repository->upgradePassword($user, 'new-hash');

        self::assertSame('new-hash', $user->getPassword());

        $this->em->remove($user);
        $this->em->flush();
    }

    public function testUpgradePasswordRejectsUnsupportedUsers(): void
    {
        $unsupported = new class implements PasswordAuthenticatedUserInterface {
            public function getPassword(): ?string
            {
                return null;
            }
        };

        $this->expectException(UnsupportedUserException::class);
        $this->repository->upgradePassword($unsupported, 'hash');
    }

    public function testFindAdminsListsOnlyUsersWithTheAdminRole(): void
    {
        $admins = $this->repository->findAdmins();
        $emails = array_map(static fn (User $user): ?string => $user->getEmail(), $admins);

        self::assertContains('admin@example.com', $emails);
        self::assertNotContains('editor@example.com', $emails);
        foreach ($admins as $admin) {
            self::assertContains('ROLE_ADMIN', $admin->getRoles());
        }
    }
}
