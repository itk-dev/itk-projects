<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\User;
use App\Repository\AreaRepository;
use App\Repository\ContactRepository;
use App\Repository\DepartmentRepository;
use App\Repository\PartnerRepository;
use App\Repository\ProjectRepository;
use App\Repository\TermRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base class for database-backed functional tests. Assumes the development
 * fixtures have been loaded into the test database (admin@example.com /
 * editor@example.com).
 */
abstract class FunctionalTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = static::createClient();
    }

    protected function entityManager(): EntityManagerInterface
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        return $em;
    }

    protected function users(): UserRepository
    {
        $repository = static::getContainer()->get(UserRepository::class);
        \assert($repository instanceof UserRepository);

        return $repository;
    }

    protected function projects(): ProjectRepository
    {
        $repository = static::getContainer()->get(ProjectRepository::class);
        \assert($repository instanceof ProjectRepository);

        return $repository;
    }

    protected function contacts(): ContactRepository
    {
        $repository = static::getContainer()->get(ContactRepository::class);
        \assert($repository instanceof ContactRepository);

        return $repository;
    }

    protected function partners(): PartnerRepository
    {
        $repository = static::getContainer()->get(PartnerRepository::class);
        \assert($repository instanceof PartnerRepository);

        return $repository;
    }

    protected function departments(): DepartmentRepository
    {
        $repository = static::getContainer()->get(DepartmentRepository::class);
        \assert($repository instanceof DepartmentRepository);

        return $repository;
    }

    protected function areas(): AreaRepository
    {
        $repository = static::getContainer()->get(AreaRepository::class);
        \assert($repository instanceof AreaRepository);

        return $repository;
    }

    protected function terms(): TermRepository
    {
        $repository = static::getContainer()->get(TermRepository::class);
        \assert($repository instanceof TermRepository);

        return $repository;
    }

    protected function loginAsAdmin(): User
    {
        return $this->login('admin@example.com');
    }

    protected function loginAsEditor(): User
    {
        return $this->login('editor@example.com');
    }

    protected function login(string $email): User
    {
        $user = $this->users()->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user, sprintf('User "%s" not found — load fixtures into the test database first.', $email));
        $this->client->loginUser($user);

        return $user;
    }
}
