<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Area;
use App\Entity\Department;
use App\Enum\Funding;
use App\Enum\Status;
use App\Repository\AreaRepository;
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
        self::assertGreaterThan(0, array_sum(array_map('array_sum', $data['heatmap'])), 'heatmap should have entries');
    }

    /**
     * Drives build() from controlled rows so every aggregation branch is
     * exercised deterministically — independent of the random dev fixtures.
     */
    public function testBuildFromControlledRows(): void
    {
        $deptA = (new Department())->setName('A');
        $deptB = (new Department())->setName('B');
        $shared = (new Area())->setName('Shared');
        $solo = (new Area())->setName('Solo');

        $departments = $this->createStub(DepartmentRepository::class);
        $departments->method('findAllOrdered')->willReturn([$deptA, $deptB]);
        $areas = $this->createStub(AreaRepository::class);
        $areas->method('findAllOrdered')->willReturn([$shared, $solo]);

        $a = (string) $deptA->getId();
        $b = (string) $deptB->getId();
        $start = new \DateTimeImmutable('2025-01-01');
        $end = new \DateTimeImmutable('2025-06-01');
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('dashboardRows')->willReturn([
            // "Shared" worked on in two departments -> a collaboration opportunity;
            // also carries budget, funding (a known and an unknown slug) and a span.
            ['title' => 'Shared A', 'area' => (string) $shared->getId(), 'status' => Status::Active, 'organizationalAnchoring' => [$a], 'budget' => 1000, 'funding' => [Funding::MunicipalBudget->value, 'unknown'], 'timePeriodStart' => $start, 'timePeriodEnd' => $end],
            ['title' => 'Shared B', 'area' => (string) $shared->getId(), 'status' => Status::Active, 'organizationalAnchoring' => [$b], 'budget' => 2000, 'funding' => [], 'timePeriodStart' => null, 'timePeriodEnd' => null],
            // "Solo" only in one department -> skipped by collaboration().
            ['title' => 'Solo', 'area' => (string) $solo->getId(), 'status' => Status::Active, 'organizationalAnchoring' => [$a], 'budget' => null, 'funding' => [], 'timePeriodStart' => null, 'timePeriodEnd' => null],
            // Anchored in both departments (plus an id no department has, which is
            // dropped) but without an area: counted under each department, absent
            // from the heatmap, coloured by its first department on the timeline.
            ['title' => 'Cross', 'area' => null, 'status' => Status::Active, 'organizationalAnchoring' => [$a, $b, 'unknown'], 'budget' => 500, 'funding' => [], 'timePeriodStart' => $start->modify('+1 month'), 'timePeriodEnd' => $end],
            // No area/department/status -> exercises the null guards.
            ['title' => 'Loose', 'area' => null, 'status' => null, 'organizationalAnchoring' => [], 'budget' => null, 'funding' => [], 'timePeriodStart' => null, 'timePeriodEnd' => null],
        ]);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $data = (new DashboardData($projects, $departments, $areas, $translator))->build();

        self::assertSame(5, $data['kpis']['total']);
        self::assertSame(2, $data['kpis']['departments']);
        // Only "Shared" spans >= 2 departments; "Solo" is skipped.
        self::assertSame(1, $data['kpis']['collaboration']);
        self::assertCount(1, $data['collaboration']);
        self::assertSame('Shared', $data['collaboration'][0]['theme']);

        // Heatmap rows are departments, columns areas: "Cross" has no area.
        self::assertSame([[1, 1], [1, 0]], $data['heatmap']);
        // A shared project's budget counts in full under each of its departments.
        self::assertSame([1500, 2500], $data['budgetByDept']);
        $active = array_search(Status::Active->value, array_column($data['statuses'], 'key'), true);
        self::assertSame([3, 2], $data['statusByDept'][$active]);

        self::assertCount(2, $data['timeline']);
        self::assertSame(['Shared A', 'Cross'], array_column($data['timeline'], 'title'));
        self::assertSame($a, $data['timeline'][1]['dept']);
    }
}
