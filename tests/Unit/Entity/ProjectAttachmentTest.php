<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Project;
use App\Entity\ProjectAttachment;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;

final class ProjectAttachmentTest extends TestCase
{
    public function testAccessors(): void
    {
        $project = new Project();
        $attachment = (new ProjectAttachment())
            ->setProject($project)
            ->setFileName('stored.pdf')
            ->setOriginalName('report.pdf')
            ->setMimeType('application/pdf')
            ->setSize(4096);

        self::assertSame($project, $attachment->getProject());
        self::assertSame('stored.pdf', $attachment->getFileName());
        self::assertSame('report.pdf', $attachment->getOriginalName());
        self::assertSame('application/pdf', $attachment->getMimeType());
        self::assertSame(4096, $attachment->getSize());
    }

    public function testSettingAFileMarksItDirty(): void
    {
        $attachment = new ProjectAttachment();
        self::assertNull($attachment->getFile());
        self::assertFalse($attachment->hasFile());

        $attachment->setFile(new File(__FILE__));
        self::assertInstanceOf(File::class, $attachment->getFile());
        self::assertTrue($attachment->hasFile());

        $attachment->setFile(null);
        self::assertNull($attachment->getFile());
    }

    public function testHasFileIsTrueWhenOnlyAStoredNameIsPresent(): void
    {
        $attachment = (new ProjectAttachment())->setFileName('stored.pdf');

        self::assertTrue($attachment->hasFile());
    }
}
