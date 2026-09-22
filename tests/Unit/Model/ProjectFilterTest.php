<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Entity\Area;
use App\Entity\Department;
use App\Enum\ProjectType;
use App\Enum\Status;
use App\Model\ProjectFilter;
use PHPUnit\Framework\TestCase;

final class ProjectFilterTest extends TestCase
{
    public function testDefaults(): void
    {
        $filter = new ProjectFilter();

        self::assertNull($filter->q);
        self::assertNull($filter->status);
        self::assertNull($filter->area);
        self::assertNull($filter->projectType);
        self::assertNull($filter->organizationalAnchoring);
        self::assertNull($filter->endorsement);
        self::assertSame('createdAt', $filter->sort);
        self::assertSame('DESC', $filter->direction);
    }

    public function testIsMutable(): void
    {
        $filter = new ProjectFilter();
        $filter->q = 'klima';
        $filter->status = Status::Active;
        $filter->area = (new Area())->setName('Klima og miljø');
        $filter->projectType = ProjectType::Project;
        $filter->organizationalAnchoring = (new Department())->setName('Sundhed og Omsorg');
        $filter->endorsement = true;
        $filter->sort = 'title';
        $filter->direction = 'ASC';

        self::assertSame('klima', $filter->q);
        self::assertSame(Status::Active, $filter->status);
        self::assertTrue($filter->endorsement);
    }
}
