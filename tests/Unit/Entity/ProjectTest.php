<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Area;
use App\Entity\Contact;
use App\Entity\Department;
use App\Entity\Partner;
use App\Entity\Project;
use App\Entity\ProjectAttachment;
use App\Entity\ProjectImage;
use App\Entity\Term;
use App\Enum\EndorsementAuthor;
use App\Enum\Funding;
use App\Enum\ProjectType;
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
        self::assertSame([], $project->getFunding());
        self::assertSame([], $project->getLinks());
        self::assertCount(0, $project->getStrategies());
        self::assertCount(0, $project->getStakeholders());
        self::assertCount(0, $project->getTags());
        self::assertCount(0, $project->getContacts());
        self::assertCount(0, $project->getPartners());
        self::assertCount(0, $project->getImages());
        self::assertCount(0, $project->getAttachments());
        self::assertNull($project->getCreatedAt());
        self::assertNull($project->getUpdatedAt());
        self::assertSame('', (string) $project);
    }

    public function testScalarAccessors(): void
    {
        $start = new \DateTimeImmutable('2025-01-01');
        $end = new \DateTimeImmutable('2025-12-31');
        $department = (new Department())->setName('Teknik og Miljø');
        $area = (new Area())->setName('Klima og miljø');

        $project = (new Project())
            ->setTitle('Grøn omstilling')
            ->setTopic('Digital Europe Blueprint for Data Space')
            ->setArea($area)
            ->setSummary('Opsummering')
            ->setDescription('Beskrivelse')
            ->setProjectType(ProjectType::Project)
            ->setStatus(Status::Granted)
            ->setStatusAdditional('Igangsat')
            ->setOrganizationalAnchoring($department)
            ->setEndorsement(false)
            ->setEndorsementAuthor(EndorsementAuthor::CityCouncil)
            ->setBudget(500000)
            ->setTimePeriodStart($start)
            ->setTimePeriodEnd($end);

        self::assertSame('Grøn omstilling', $project->getTitle());
        self::assertSame('Digital Europe Blueprint for Data Space', $project->getTopic());
        self::assertSame($area, $project->getArea());
        self::assertSame('Opsummering', $project->getSummary());
        self::assertSame('Beskrivelse', $project->getDescription());
        self::assertSame(ProjectType::Project, $project->getProjectType());
        self::assertSame(Status::Granted, $project->getStatus());
        self::assertSame('Igangsat', $project->getStatusAdditional());
        self::assertSame($department, $project->getOrganizationalAnchoring());
        self::assertFalse($project->isEndorsement());
        self::assertSame(EndorsementAuthor::CityCouncil, $project->getEndorsementAuthor());
        self::assertSame(500000, $project->getBudget());
        self::assertSame($start, $project->getTimePeriodStart());
        self::assertSame($end, $project->getTimePeriodEnd());
        self::assertSame('Grøn omstilling', (string) $project);
    }

    public function testCompletionPercentage(): void
    {
        self::assertSame(0, (new Project())->getCompletionPercentage());

        $full = (new Project())
            ->setTitle('T')
            ->setArea((new Area())->setName('Klima og miljø'))
            ->setSummary('S')
            ->setDescription('D')
            ->setProjectType(ProjectType::Project)
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

    public function testLinksKeepOnlyHttpUrls(): void
    {
        $project = (new Project())->setLinks([
            '',
            'https://ok.example',
            'javascript:alert(1)',
            '   ',
            'http://plain.example',
        ]);

        self::assertSame(['https://ok.example', 'http://plain.example'], $project->getLinks());
    }

    public function testStrategyCollection(): void
    {
        $project = new Project();
        $term = new Term(Vocabulary::Strategy);

        $project->addStrategy($term);
        $project->addStrategy($term);
        self::assertCount(1, $project->getStrategies());

        $project->removeStrategy($term);
        self::assertCount(0, $project->getStrategies());

        $project->setStrategies([new Term(Vocabulary::Strategy), new Term(Vocabulary::Strategy)]);
        self::assertCount(2, $project->getStrategies());
        $project->setStrategies([]);
        self::assertCount(0, $project->getStrategies());
    }

    public function testStakeholderCollection(): void
    {
        $project = new Project();
        $term = new Term(Vocabulary::Stakeholder);

        $project->addStakeholder($term);
        $project->addStakeholder($term);
        self::assertCount(1, $project->getStakeholders());

        $project->removeStakeholder($term);
        self::assertCount(0, $project->getStakeholders());

        $project->setStakeholders([new Term(Vocabulary::Stakeholder)]);
        self::assertCount(1, $project->getStakeholders());
        $project->setStakeholders([]);
        self::assertCount(0, $project->getStakeholders());
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

    public function testImageCollectionLinksBackToProject(): void
    {
        $project = new Project();
        $image = new ProjectImage();

        $project->addImage($image);
        $project->addImage($image);
        self::assertCount(1, $project->getImages());
        self::assertSame($project, $image->getProject());

        $project->removeImage($image);
        self::assertCount(0, $project->getImages());
    }

    public function testAttachmentCollectionLinksBackToProject(): void
    {
        $project = new Project();
        $attachment = new ProjectAttachment();

        $project->addAttachment($attachment);
        $project->addAttachment($attachment);
        self::assertCount(1, $project->getAttachments());
        self::assertSame($project, $attachment->getProject());

        $project->removeAttachment($attachment);
        self::assertCount(0, $project->getAttachments());
    }
}
