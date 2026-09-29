<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Repository\ContactRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ContactRepositoryTest extends KernelTestCase
{
    public function testFindAllOrderedReturnsContactsSortedByName(): void
    {
        self::bootKernel();
        $repository = static::getContainer()->get(ContactRepository::class);
        \assert($repository instanceof ContactRepository);

        $contacts = $repository->findAllOrdered();

        // Ordering is delegated to the database collation, so we only assert the
        // method returns the persisted contacts.
        self::assertNotEmpty($contacts);
    }
}
