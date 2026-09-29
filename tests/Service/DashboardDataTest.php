<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Area;
use App\Entity\Department;
use App\Enum\Status;
use App\Repository\DepartmentRepository;
use App\Repository\ProjectRepository;
use App\Service\DashboardData;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

final class DashboardDataTest extends KernelTestCase
{
    public function testBuildAggregatesDepartmentData(): void
    {
        self::bootKernel();
        $dashboard = static::getContainer()->get(DashboardData::class);
        \assert($dashboard instanceof DashboardData);

        $data = $dashboard->build();

        self::assertGreaterThan(0, $data['kpis']['total']);
        self::assertGreaterThan(0, $data['kpis']['departmentsTotal']);
        // Fixtures anchor every project in at least one department, so the
        // department-keyed aggregates must be populated.
        self::assertGreaterThan(0, $data['kpis']['departments'], 'deptsSeen should be > 0');
        // One budget cell per department and one status cell per status case,
        // in the same order as the labels the chart reads.
        self::assertCount(\count($data['departments']), $data['budgetByDept']);
        self::assertCount(\count(Status::cases()), $data['statusDistribution']);
    }

    /**
     * Drives build() from controlled rows so every aggregation branch is
     * exercised deterministically — independent of the random dev fixtures.
     */
    public function testBuildFromControlledRows(): void
    {
        $deptA = (new Department())->setName('A');
        $deptB = (new Department())->setName('B');
        $shared = (string) (new Area())->setName('Shared')->getId();
        $solo = (string) (new Area())->setName('Solo')->getId();
        $third = (string) (new Area())->setName('Third')->getId();

        $departments = $this->createStub(DepartmentRepository::class);
        $departments->method('findAllOrdered')->willReturn([$deptA, $deptB]);

        $a = (string) $deptA->getId();
        $b = (string) $deptB->getId();
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('dashboardRows')->willReturn([
            // "Shared" worked on in two departments -> counts as a collaboration.
            ['areas' => [$shared], 'status' => Status::Granted, 'organizationalAnchoring' => [$a], 'budget' => 1000],
            ['areas' => [$shared], 'status' => Status::Granted, 'organizationalAnchoring' => [$b], 'budget' => 2000],
            // "Solo" only in one department, and without a budget.
            ['areas' => [$solo], 'status' => Status::Granted, 'organizationalAnchoring' => [$a], 'budget' => null],
            // Anchored in both departments (plus an id no department has, which is
            // dropped) but without an area: its budget counts under each department,
            // and it is no collaboration on its own since it has no area.
            ['areas' => [], 'status' => Status::Granted, 'organizationalAnchoring' => [$a, $b, 'unknown'], 'budget' => 500],
            // Two areas in one department: each area is marked as worked on in B,
            // which puts "Solo" in both departments too, while "Third" stays in one.
            ['areas' => [$solo, $third], 'status' => Status::Granted, 'organizationalAnchoring' => [$b], 'budget' => null],
            // No area/department/status -> exercises the null guards.
            ['areas' => [], 'status' => null, 'organizationalAnchoring' => [], 'budget' => null],
        ]);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $data = (new DashboardData($projects, $departments, $translator))->build();

        self::assertSame(6, $data['kpis']['total']);
        self::assertSame(5, $data['kpis']['inProgress']);
        self::assertSame(2, $data['kpis']['departments']);
        self::assertSame(2, $data['kpis']['departmentsTotal']);
        // "Shared" and "Solo" span >= 2 departments; "Third" does not count.
        self::assertSame(2, $data['kpis']['collaboration']);
        self::assertSame(['A', 'B'], array_column($data['departments'], 'label'));
        // A shared project's budget counts in full under each of its departments.
        self::assertSame([1500, 2500], $data['budgetByDept']);
        self::assertSame(5, array_sum($data['statusDistribution']));
        // The stubbed translator echoes the key, so labels are the enum label keys.
        self::assertContains(['key' => Status::Granted->value, 'label' => Status::Granted->labelKey()], $data['statuses']);
    }
}
