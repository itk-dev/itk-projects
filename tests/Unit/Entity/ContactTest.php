<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Contact;
use App\Entity\Department;
use PHPUnit\Framework\TestCase;

final class ContactTest extends TestCase
{
    public function testDefaults(): void
    {
        $contact = new Contact();

        self::assertNull($contact->getName());
        self::assertNull($contact->getEmail());
        self::assertNull($contact->getPhone());
        self::assertNull($contact->getDepartment());
        // Timestamps are populated by the bundle's listener on flush, so they
        // are still null on a freshly constructed (unpersisted) entity.
        self::assertNull($contact->getCreatedAt());
        self::assertSame('', (string) $contact);
    }

    public function testAccessors(): void
    {
        $department = new Department();
        $department->setName('Teknik og Miljø');

        $contact = (new Contact())
            ->setName('Anne Jensen')
            ->setEmail('anne@example.com')
            ->setPhone('+45 12 34 56 78')
            ->setDepartment($department);

        self::assertSame('Anne Jensen', $contact->getName());
        self::assertSame('anne@example.com', $contact->getEmail());
        self::assertSame('+45 12 34 56 78', $contact->getPhone());
        self::assertSame($department, $contact->getDepartment());
        self::assertSame('Anne Jensen', (string) $contact);
    }
}
