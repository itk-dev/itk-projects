<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Area;
use App\Entity\Contact;
use App\Entity\Department;
use App\Entity\Partner;
use App\Entity\Project;
use App\Entity\ProjectType;
use App\Entity\Term;
use App\Enum\EndorsementAuthor;
use App\Enum\Funding;
use App\Enum\FundingRate;
use App\Enum\Status;
use App\Enum\Vocabulary;
use PHPUnit\Framework\TestCase;

final class ProjectTest extends TestCase
{
    public function testDefaults(): void
    {
        $project = new Project();

        self::assertNull($project->getTitle());
        self::assertFalse($project->isEndorsement());
        self::assertNull($project->getAmountApplied());
        self::assertNull($project->getBudget());
        self::assertNull($project->getBudgetItk());
        self::assertFalse($project->isCoFinancing());
        self::assertNull($project->getFundingRate());
        self::assertNull($project->getRemainingFunding());
        self::assertSame([], $project->getFunding());
        self::assertSame([], $project->getLinks());
        self::assertCount(0, $project->getAreas());
        self::assertCount(0, $project->getOrganizationalAnchoring());
        self::assertCount(0, $project->getTypes());
        self::assertCount(0, $project->getTags());
        self::assertCount(0, $project->getContacts());
        self::assertCount(0, $project->getPartners());
        self::assertNull($project->getCreatedAt());
        self::assertNull($project->getUpdatedAt());
        self::assertSame('', (string) $project);
    }

    public function testScalarAccessors(): void
    {
        $start = new \DateTimeImmutable('2025-01-01');
        $end = new \DateTimeImmutable('2025-12-31');

        $project = (new Project())
            ->setTitle('Grøn omstilling')
            ->setTopic('Digital Europe Blueprint for Data Space')
            ->setDescription('Beskrivelse')
            ->setStatus(Status::Granted)
            ->setStatusAdditional('Igangsat')
            ->setEndorsement(false)
            ->setEndorsementAuthor(EndorsementAuthor::CityCouncil)
            ->setAmountApplied(400000)
            ->setBudget(500000)
            ->setBudgetItk(125000)
            ->setCoFinancing(true)
            ->setFundingRate(FundingRate::ThreeQuarters)
            ->setRemainingFunding('Egenfinansiering')
            ->setTimePeriodStart($start)
            ->setTimePeriodEnd($end);

        self::assertSame('Grøn omstilling', $project->getTitle());
        self::assertSame('Digital Europe Blueprint for Data Space', $project->getTopic());
        self::assertSame('Beskrivelse', $project->getDescription());
        self::assertSame(Status::Granted, $project->getStatus());
        self::assertSame('Igangsat', $project->getStatusAdditional());
        self::assertFalse($project->isEndorsement());
        self::assertSame(EndorsementAuthor::CityCouncil, $project->getEndorsementAuthor());
        self::assertSame(400000, $project->getAmountApplied());
        self::assertSame(500000, $project->getBudget());
        self::assertSame(125000, $project->getBudgetItk());
        self::assertTrue($project->isCoFinancing());
        self::assertSame(FundingRate::ThreeQuarters, $project->getFundingRate());
        self::assertSame('Egenfinansiering', $project->getRemainingFunding());
        self::assertSame($start, $project->getTimePeriodStart());
        self::assertSame($end, $project->getTimePeriodEnd());
        self::assertSame('Grøn omstilling', (string) $project);
    }

    public function testCompletionPercentage(): void
    {
        self::assertSame(0, (new Project())->getCompletionPercentage());

        $full = (new Project())
            ->setTitle('T')
            ->addArea((new Area())->setName('Klima og miljø'))
            ->setDescription('D')
            ->addType((new ProjectType())->setName('Projekt'))
            ->setStatus(Status::Granted)
            ->addOrganizationalAnchoring((new Department())->setName('Teknik og Miljø'))
            ->setAmountApplied(800)
            ->setBudget(1000)
            ->setBudgetItk(250)
            ->setFundingRate(FundingRate::Half)
            ->setFunding([Funding::EuFunds])
            ->setTimePeriodStart(new \DateTimeImmutable())
            ->setTimePeriodEnd(new \DateTimeImmutable());

        // The Vedtagelse (endorsement) fields, co-financing and the optional
        // remaining-funding text are intentionally excluded, so this reaches 100%
        // without them.
        self::assertSame(100, $full->getCompletionPercentage());
    }

    public function testWasUpdatedAfterCreation(): void
    {
        // No timestamps yet (entity not persisted) — treated as not updated.
        self::assertFalse((new Project())->wasUpdatedAfterCreation());

        $created = new \DateTimeImmutable('2025-01-01 10:00:00');

        // Edited within a day of creation: still reads as "created".
        $fresh = new Project();
        $fresh->setCreatedAt($created);
        $fresh->setUpdatedAt($created->modify('+5 hours'));
        self::assertFalse($fresh->wasUpdatedAfterCreation());

        // Edited more than a day after creation.
        $edited = new Project();
        $edited->setCreatedAt($created);
        $edited->setUpdatedAt($created->modify('+2 days'));
        self::assertTrue($edited->wasUpdatedAfterCreation());
    }

    public function testFundingRoundTrip(): void
    {
        $project = (new Project())->setFunding([Funding::MunicipalBudget, Funding::EuFunds]);

        self::assertSame([Funding::MunicipalBudget, Funding::EuFunds], $project->getFunding());
    }

    public function testLinksKeepOnlyHttpUrlsAndTrimNotes(): void
    {
        $project = (new Project())->setLinks([
            ['url' => '', 'note' => 'A note is not a link'],
            ['url' => 'https://ok.example', 'note' => '  Project site  '],
            ['url' => 'javascript:alert(1)', 'note' => null],
            ['url' => '   ', 'note' => null],
            ['url' => ' http://plain.example ', 'note' => ''],
            ['note' => 'No url key at all'],
        ]);

        self::assertSame([
            ['url' => 'https://ok.example', 'note' => 'Project site'],
            ['url' => 'http://plain.example', 'note' => null],
        ], $project->getLinks());
    }

    public function testOrganizationalAnchoringCollection(): void
    {
        $project = new Project();
        $department = (new Department())->setName('Teknik og Miljø');

        $project->addOrganizationalAnchoring($department);
        $project->addOrganizationalAnchoring($department);
        self::assertCount(1, $project->getOrganizationalAnchoring());

        $project->removeOrganizationalAnchoring($department);
        self::assertCount(0, $project->getOrganizationalAnchoring());

        $project->setOrganizationalAnchoring([
            (new Department())->setName('Sundhed og Omsorg'),
            (new Department())->setName('Børn og Unge'),
        ]);
        self::assertCount(2, $project->getOrganizationalAnchoring());
        $project->setOrganizationalAnchoring([]);
        self::assertCount(0, $project->getOrganizationalAnchoring());
    }

    public function testAreaCollection(): void
    {
        $project = new Project();
        $area = (new Area())->setName('Klima og miljø');

        $project->addArea($area);
        $project->addArea($area);
        self::assertCount(1, $project->getAreas());

        $project->removeArea($area);
        self::assertCount(0, $project->getAreas());

        $project->setAreas([
            (new Area())->setName('Mobilitet'),
            (new Area())->setName('Velfærd'),
        ]);
        self::assertCount(2, $project->getAreas());
        $project->setAreas([]);
        self::assertCount(0, $project->getAreas());
    }

    public function testTypeCollection(): void
    {
        $project = new Project();
        $type = (new ProjectType())->setName('Pilot');

        $project->addType($type);
        $project->addType($type);
        self::assertCount(1, $project->getTypes());

        $project->removeType($type);
        self::assertCount(0, $project->getTypes());

        $project->setTypes([
            (new ProjectType())->setName('Projekt'),
            (new ProjectType())->setName('Drift'),
        ]);
        self::assertCount(2, $project->getTypes());
        $project->setTypes([]);
        self::assertCount(0, $project->getTypes());
    }

    public function testTagCollection(): void
    {
        $project = new Project();
        $term = new Term(Vocabulary::Tag);

        $project->addTag($term);
        $project->addTag($term);
        self::assertCount(1, $project->getTags());

        $project->removeTag($term);
        self::assertCount(0, $project->getTags());

        $project->setTags([new Term(Vocabulary::Tag), new Term(Vocabulary::Tag)]);
        self::assertCount(2, $project->getTags());
        $project->setTags([]);
        self::assertCount(0, $project->getTags());
    }

    public function testContactCollection(): void
    {
        $project = new Project();
        $contact = (new Contact())->setName('Anne');

        $project->addContact($contact);
        $project->addContact($contact);
        self::assertCount(1, $project->getContacts());

        $project->removeContact($contact);
        self::assertCount(0, $project->getContacts());
    }

    public function testPartnerCollection(): void
    {
        $project = new Project();
        $partner = (new Partner())->setName('Aarhus Universitet');

        $project->addPartner($partner);
        $project->addPartner($partner);
        self::assertCount(1, $project->getPartners());

        $project->removePartner($partner);
        self::assertCount(0, $project->getPartners());
    }
}
