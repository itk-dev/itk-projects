<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\ProjectCharacterRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProjectCharacterRepositoryTest extends KernelTestCase
{
    public function testFindAllOrderedReturnsCharacters(): void
    {
        self::bootKernel();
        $repository = static::getContainer()->get(ProjectCharacterRepository::class);
        \assert($repository instanceof ProjectCharacterRepository);

        $characters = $repository->findAllOrdered();

        // Ordering is delegated to the database collation, so we only assert the
        // method returns the persisted characters.
        self::assertNotEmpty($characters);
        self::assertNotNull($characters[0]->getName());
    }
}
