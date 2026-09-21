<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Project;
use App\Entity\ProjectImage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\File\File;

final class ProjectImageTest extends TestCase
{
    public function testAccessors(): void
    {
        $project = new Project();
        $image = (new ProjectImage())
            ->setProject($project)
            ->setImageName('stored.png')
            ->setOriginalName('sample.png')
            ->setMimeType('image/png')
            ->setSize(1234)
            ->setAlt('A sample');

        self::assertSame($project, $image->getProject());
        self::assertSame('stored.png', $image->getImageName());
        self::assertSame('sample.png', $image->getOriginalName());
        self::assertSame('image/png', $image->getMimeType());
        self::assertSame(1234, $image->getSize());
        self::assertSame('A sample', $image->getAlt());
    }

    public function testSettingAFileMarksItDirty(): void
    {
        $image = new ProjectImage();
        self::assertNull($image->getImageFile());
        self::assertFalse($image->hasFile());

        $image->setImageFile(new File(__FILE__));
        self::assertInstanceOf(File::class, $image->getImageFile());
        self::assertTrue($image->hasFile());

        $image->setImageFile(null);
        self::assertNull($image->getImageFile());
    }

    public function testHasFileIsTrueWhenOnlyAStoredNameIsPresent(): void
    {
        $image = (new ProjectImage())->setImageName('stored.png');

        self::assertTrue($image->hasFile());
    }
}
