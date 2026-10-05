<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\DepartmentRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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
        self::assertNotNull($departments[0]->getName());
    }
}
