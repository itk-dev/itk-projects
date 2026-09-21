<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Area;
use App\Entity\Contact;
use App\Entity\Department;
use App\Entity\Initiative;
use App\Entity\InitiativeAttachment;
use App\Entity\InitiativeImage;
use App\Entity\Partner;
use App\Entity\Term;
use App\Enum\EndorsementAuthor;
use App\Enum\Funding;
use App\Enum\InitiativeType;
use App\Enum\Status;
use App\Enum\Vocabulary;
use PHPUnit\Framework\TestCase;

final class InitiativeTest extends TestCase
{
    public function testDefaults(): void
    {
        $initiative = new Initiative();

        self::assertNull($initiative->getTitle());
        self::assertFalse($initiative->isEndorsement());
        self::assertSame([], $initiative->getFunding());
        self::assertSame([], $initiative->getLinks());
        self::assertCount(0, $initiative->getStrategies());
        self::assertCount(0, $initiative->getStakeholders());
        self::assertCount(0, $initiative->getTags());
        self::assertCount(0, $initiative->getContacts());
        self::assertCount(0, $initiative->getPartners());
        self::assertCount(0, $initiative->getImages());
        self::assertCount(0, $initiative->getAttachments());
        self::assertNull($initiative->getCreatedAt());
        self::assertNull($initiative->getUpdatedAt());
        self::assertSame('', (string) $initiative);
    }

    public function testScalarAccessors(): void
    {
        $start = new \DateTimeImmutable('2025-01-01');
        $end = new \DateTimeImmutable('2025-12-31');
        $department = (new Department())->setName('Teknik og Miljø');
        $area = (new Area())->setName('Klima og miljø');

        $initiative = (new Initiative())
            ->setTitle('Grøn omstilling')
            ->setTopic('Digital Europe Blueprint for Data Space')
            ->setArea($area)
            ->setDescription('Beskrivelse')
            ->setInitiativeType(InitiativeType::Project)
            ->setStatus(Status::Granted)
            ->setStatusAdditional('Igangsat')
            ->setOrganizationalAnchoring($department)
            ->setEndorsement(false)
            ->setEndorsementAuthor(EndorsementAuthor::CityCouncil)
            ->setBudget(500000)
            ->setTimePeriodStart($start)
            ->setTimePeriodEnd($end);

        self::assertSame('Grøn omstilling', $initiative->getTitle());
        self::assertSame('Digital Europe Blueprint for Data Space', $initiative->getTopic());
        self::assertSame($area, $initiative->getArea());
        self::assertSame('Beskrivelse', $initiative->getDescription());
        self::assertSame(InitiativeType::Project, $initiative->getInitiativeType());
        self::assertSame(Status::Granted, $initiative->getStatus());
        self::assertSame('Igangsat', $initiative->getStatusAdditional());
        self::assertSame($department, $initiative->getOrganizationalAnchoring());
        self::assertFalse($initiative->isEndorsement());
        self::assertSame(EndorsementAuthor::CityCouncil, $initiative->getEndorsementAuthor());
        self::assertSame(500000, $initiative->getBudget());
        self::assertSame($start, $initiative->getTimePeriodStart());
        self::assertSame($end, $initiative->getTimePeriodEnd());
        self::assertSame('Grøn omstilling', (string) $initiative);
    }

    public function testCompletionPercentage(): void
    {
        self::assertSame(0, (new Initiative())->getCompletionPercentage());

        $full = (new Initiative())
            ->setTitle('T')
            ->setArea((new Area())->setName('Klima og miljø'))
            ->setDescription('D')
            ->setInitiativeType(InitiativeType::Project)
            ->setStatus(Status::Granted)
            ->setOrganizationalAnchoring((new Department())->setName('Teknik og Miljø'))
            ->setBudget(1000)
            ->setFunding([Funding::EuFunds])
            ->setTimePeriodStart(new \DateTimeImmutable())
            ->setTimePeriodEnd(new \DateTimeImmutable());

        // The Vedtagelse (endorsement) fields are intentionally excluded, so this
        // reaches 100% without setting an endorsement author.
        self::assertSame(100, $full->getCompletionPercentage());
    }

    public function testWasUpdatedAfterCreation(): void
    {
        // No timestamps yet (entity not persisted) — treated as not updated.
        self::assertFalse((new Initiative())->wasUpdatedAfterCreation());

        $created = new \DateTimeImmutable('2025-01-01 10:00:00');

        // Edited within a day of creation: still reads as "created".
        $fresh = new Initiative();
        $fresh->setCreatedAt($created);
        $fresh->setUpdatedAt($created->modify('+5 hours'));
        self::assertFalse($fresh->wasUpdatedAfterCreation());

        // Edited more than a day after creation.
        $edited = new Initiative();
        $edited->setCreatedAt($created);
        $edited->setUpdatedAt($created->modify('+2 days'));
        self::assertTrue($edited->wasUpdatedAfterCreation());
    }

    public function testFundingRoundTrip(): void
    {
        $initiative = (new Initiative())->setFunding([Funding::MunicipalBudget, Funding::EuFunds]);

        self::assertSame([Funding::MunicipalBudget, Funding::EuFunds], $initiative->getFunding());
    }

    public function testLinksKeepOnlyHttpUrls(): void
    {
        $initiative = (new Initiative())->setLinks([
            '',
            'https://ok.example',
            'javascript:alert(1)',
            '   ',
            'http://plain.example',
        ]);

        self::assertSame(['https://ok.example', 'http://plain.example'], $initiative->getLinks());
    }

    public function testStrategyCollection(): void
    {
        $initiative = new Initiative();
        $term = new Term(Vocabulary::Strategy);

        $initiative->addStrategy($term);
        $initiative->addStrategy($term);
        self::assertCount(1, $initiative->getStrategies());

        $initiative->removeStrategy($term);
        self::assertCount(0, $initiative->getStrategies());

        $initiative->setStrategies([new Term(Vocabulary::Strategy), new Term(Vocabulary::Strategy)]);
        self::assertCount(2, $initiative->getStrategies());
        $initiative->setStrategies([]);
        self::assertCount(0, $initiative->getStrategies());
    }

    public function testStakeholderCollection(): void
    {
        $initiative = new Initiative();
        $term = new Term(Vocabulary::Stakeholder);

        $initiative->addStakeholder($term);
        $initiative->addStakeholder($term);
        self::assertCount(1, $initiative->getStakeholders());

        $initiative->removeStakeholder($term);
        self::assertCount(0, $initiative->getStakeholders());

        $initiative->setStakeholders([new Term(Vocabulary::Stakeholder)]);
        self::assertCount(1, $initiative->getStakeholders());
        $initiative->setStakeholders([]);
        self::assertCount(0, $initiative->getStakeholders());
    }

    public function testTagCollection(): void
    {
        $initiative = new Initiative();
        $term = new Term(Vocabulary::Tag);

        $initiative->addTag($term);
        $initiative->addTag($term);
        self::assertCount(1, $initiative->getTags());

        $initiative->removeTag($term);
        self::assertCount(0, $initiative->getTags());

        $initiative->setTags([new Term(Vocabulary::Tag), new Term(Vocabulary::Tag)]);
        self::assertCount(2, $initiative->getTags());
        $initiative->setTags([]);
        self::assertCount(0, $initiative->getTags());
    }

    public function testContactCollection(): void
    {
        $initiative = new Initiative();
        $contact = (new Contact())->setName('Anne');

        $initiative->addContact($contact);
        $initiative->addContact($contact);
        self::assertCount(1, $initiative->getContacts());

        $initiative->removeContact($contact);
        self::assertCount(0, $initiative->getContacts());
    }

    public function testPartnerCollection(): void
    {
        $initiative = new Initiative();
        $partner = (new Partner())->setName('Aarhus Universitet');

        $initiative->addPartner($partner);
        $initiative->addPartner($partner);
        self::assertCount(1, $initiative->getPartners());

        $initiative->removePartner($partner);
        self::assertCount(0, $initiative->getPartners());
    }

    public function testImageCollectionLinksBackToInitiative(): void
    {
        $initiative = new Initiative();
        $image = new InitiativeImage();

        $initiative->addImage($image);
        $initiative->addImage($image);
        self::assertCount(1, $initiative->getImages());
        self::assertSame($initiative, $image->getInitiative());

        $initiative->removeImage($image);
        self::assertCount(0, $initiative->getImages());
    }

    public function testAttachmentCollectionLinksBackToInitiative(): void
    {
        $initiative = new Initiative();
        $attachment = new InitiativeAttachment();

        $initiative->addAttachment($attachment);
        $initiative->addAttachment($attachment);
        self::assertCount(1, $initiative->getAttachments());
        self::assertSame($initiative, $attachment->getInitiative());

        $initiative->removeAttachment($attachment);
        self::assertCount(0, $initiative->getAttachments());
    }
}
