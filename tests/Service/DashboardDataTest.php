<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Area;
use App\Entity\Department;
use App\Enum\Funding;
use App\Enum\Status;
use App\Repository\AreaRepository;
use App\Repository\DepartmentRepository;
use App\Repository\InitiativeRepository;
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
        // Fixtures set organizationalAnchoring on every initiative, so the
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

        $start = new \DateTimeImmutable('2025-01-01');
        $end = new \DateTimeImmutable('2025-06-01');
        $initiatives = $this->createStub(InitiativeRepository::class);
        $initiatives->method('dashboardRows')->willReturn([
            // "Shared" worked on in two departments -> a collaboration opportunity;
            // also carries budget, funding (a known and an unknown slug) and a span.
            ['title' => 'Shared A', 'area' => (string) $shared->getId(), 'status' => Status::Granted, 'organizationalAnchoring' => (string) $deptA->getId(), 'budget' => 1000, 'funding' => [Funding::MunicipalBudget->value, 'unknown'], 'timePeriodStart' => $start, 'timePeriodEnd' => $end],
            ['title' => 'Shared B', 'area' => (string) $shared->getId(), 'status' => Status::Granted, 'organizationalAnchoring' => (string) $deptB->getId(), 'budget' => 2000, 'funding' => [], 'timePeriodStart' => null, 'timePeriodEnd' => null],
            // "Solo" only in one department -> skipped by collaboration().
            ['title' => 'Solo', 'area' => (string) $solo->getId(), 'status' => Status::Granted, 'organizationalAnchoring' => (string) $deptA->getId(), 'budget' => null, 'funding' => [], 'timePeriodStart' => null, 'timePeriodEnd' => null],
            // No area/department/status -> exercises the null guards.
            ['title' => 'Loose', 'area' => null, 'status' => null, 'organizationalAnchoring' => null, 'budget' => null, 'funding' => [], 'timePeriodStart' => null, 'timePeriodEnd' => null],
        ]);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $data = (new DashboardData($initiatives, $departments, $areas, $translator))->build();

        self::assertSame(4, $data['kpis']['total']);
        // Only "Shared" spans >= 2 departments; "Solo" is skipped.
        self::assertSame(1, $data['kpis']['collaboration']);
        self::assertCount(1, $data['collaboration']);
        self::assertSame('Shared', $data['collaboration'][0]['theme']);
        self::assertCount(1, $data['timeline']);
    }
}
