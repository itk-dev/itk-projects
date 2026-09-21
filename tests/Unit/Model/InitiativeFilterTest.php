<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Entity\Area;
use App\Entity\Department;
use App\Enum\InitiativeType;
use App\Enum\Status;
use App\Model\InitiativeFilter;
use PHPUnit\Framework\TestCase;

final class InitiativeFilterTest extends TestCase
{
    public function testDefaults(): void
    {
        $filter = new InitiativeFilter();

        self::assertNull($filter->q);
        self::assertNull($filter->status);
        self::assertNull($filter->area);
        self::assertNull($filter->initiativeType);
        self::assertNull($filter->organizationalAnchoring);
        self::assertNull($filter->endorsement);
        self::assertSame('createdAt', $filter->sort);
        self::assertSame('DESC', $filter->direction);
    }

    public function testIsMutable(): void
    {
        $filter = new InitiativeFilter();
        $filter->q = 'klima';
        $filter->status = Status::Granted;
        $filter->area = (new Area())->setName('Klima og miljø');
        $filter->initiativeType = InitiativeType::Project;
        $filter->organizationalAnchoring = (new Department())->setName('Sundhed og Omsorg');
        $filter->endorsement = true;
        $filter->sort = 'title';
        $filter->direction = 'ASC';

        self::assertSame('klima', $filter->q);
        self::assertSame(Status::Granted, $filter->status);
        self::assertTrue($filter->endorsement);
    }
}
