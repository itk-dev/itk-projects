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
        // Fixtures set organizationalAnchoring on every project, so the
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
        $shared = (new Area())->setName('Shared');
        $solo = (new Area())->setName('Solo');

        $departments = $this->createStub(DepartmentRepository::class);
        $departments->method('findAllOrdered')->willReturn([$deptA, $deptB]);

        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('dashboardRows')->willReturn([
            // "Shared" worked on in two departments -> counts as a collaboration.
            ['area' => (string) $shared->getId(), 'status' => Status::Active, 'organizationalAnchoring' => (string) $deptA->getId(), 'budget' => 1000],
            ['area' => (string) $shared->getId(), 'status' => Status::Active, 'organizationalAnchoring' => (string) $deptB->getId(), 'budget' => 2000],
            // "Solo" only in one department, and without a budget.
            ['area' => (string) $solo->getId(), 'status' => Status::Active, 'organizationalAnchoring' => (string) $deptA->getId(), 'budget' => null],
            // No area/department/status -> exercises the null guards.
            ['area' => null, 'status' => null, 'organizationalAnchoring' => null, 'budget' => null],
        ]);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $data = (new DashboardData($projects, $departments, $translator))->build();

        self::assertSame(4, $data['kpis']['total']);
        self::assertSame(3, $data['kpis']['inProgress']);
        self::assertSame(2, $data['kpis']['departments']);
        self::assertSame(2, $data['kpis']['departmentsTotal']);
        // Only "Shared" spans >= 2 departments; "Solo" does not count.
        self::assertSame(1, $data['kpis']['collaboration']);
        self::assertSame(['A', 'B'], array_column($data['departments'], 'label'));
        self::assertSame([1000, 2000], $data['budgetByDept']);
        self::assertSame(3, array_sum($data['statusDistribution']));
        // The stubbed translator echoes the key, so labels are the enum label keys.
        self::assertContains(['key' => Status::Active->value, 'label' => Status::Active->labelKey()], $data['statuses']);
    }
}
