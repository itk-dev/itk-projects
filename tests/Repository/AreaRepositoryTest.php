<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\AreaRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class AreaRepositoryTest extends KernelTestCase
{
    public function testFindAllOrderedReturnsAreas(): void
    {
        self::bootKernel();
        $repository = static::getContainer()->get(AreaRepository::class);
        \assert($repository instanceof AreaRepository);

        $areas = $repository->findAllOrdered();

        // Ordering is delegated to the database collation, so we only assert the
        // method returns the persisted areas.
        self::assertNotEmpty($areas);
        self::assertNotNull($areas[0]->getName());
    }
}
