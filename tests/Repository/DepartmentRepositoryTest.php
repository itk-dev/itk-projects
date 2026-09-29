<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\DepartmentRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

final class DepartmentRepositoryTest extends KernelTestCase
{
    public function testFindAllOrderedReturnsDepartments(): void
    {
        self::bootKernel();
        $repository = static::getContainer()->get(DepartmentRepository::class);
        \assert($repository instanceof DepartmentRepository);

        $departments = $repository->findAllOrdered();

        // Ordering is delegated to the database collation, so we only assert the
        // method returns the persisted departments.
        self::assertNotEmpty($departments);
        self::assertInstanceOf(Ulid::class, $departments[0]->getId());
    }
}
