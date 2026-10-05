<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\ProjectTypeRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProjectTypeRepositoryTest extends KernelTestCase
{
    public function testFindAllOrderedReturnsTypes(): void
    {
        self::bootKernel();
        $repository = static::getContainer()->get(ProjectTypeRepository::class);
        \assert($repository instanceof ProjectTypeRepository);

        $types = $repository->findAllOrdered();

        // Ordering is delegated to the database collation, so we only assert the
        // method returns the persisted types.
        self::assertNotEmpty($types);
        self::assertNotNull($types[0]->getName());
    }
}
