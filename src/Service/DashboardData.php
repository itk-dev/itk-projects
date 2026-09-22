<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Area;
use App\Entity\Department;
use App\Enum\Funding;
use App\Enum\Status;
use App\Repository\AreaRepository;
use App\Repository\DepartmentRepository;
use App\Repository\ProjectRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds every dashboard visualisation as one plain array, ready to be JSON
 * encoded into the page and re-encoded for live broadcasts. Labels are
 * translated here so the client controller stays language-agnostic; colours
 * are a presentation concern and live in the Stimulus controller instead.
 */
final class DashboardData
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly DepartmentRepository $departments,
        private readonly AreaRepository $areas,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        // Departments and areas are managed entities, so normalise them to the same
        // {key, label} shape the enums get; the key is the id as a string, which is
        // what dashboardRows() returns by joining and selecting the id.
        $departments = array_map(
            static fn (Department $department): array => ['key' => (string) $department->getId(), 'label' => (string) $department->getName()],
            $this->departments->findAllOrdered(),
        );
        $areas = array_map(
            static fn (Area $area): array => ['key' => (string) $area->getId(), 'label' => (string) $area->getName()],
            $this->areas->findAllOrdered(),
        );
        $statuses = Status::cases();
        $fundings = Funding::cases();

        $deptIndex = $this->indexKeys($departments);
        $areaIndex = $this->indexKeys($areas);
        $statusIndex = $this->index($statuses);
        $fundingIndex = $this->index($fundings);

        $heatmap = $this->zeroMatrix(\count($departments), \count($areas));
        $statusByDept = $this->zeroMatrix(\count($statuses), \count($departments));
        $statusDistribution = array_fill(0, \count($statuses), 0);
        $budgetByDept = array_fill(0, \count($departments), 0);
        $fundingCount = array_fill(0, \count($fundings), 0);
        $reachByDept = array_fill(0, \count($areas), []);

        /** @var array<string, array<string, list<string>>> $titlesByAreaDept */
        $titlesByAreaDept = [];
        $timeline = [];
        $total = 0;
        $deptsSeen = [];

        foreach ($this->projects->dashboardRows() as $row) {
            ++$total;
            $dept = $this->enumValue($row['organizationalAnchoring'] ?? null);
            $area = $this->enumValue($row['area'] ?? null);
            $status = $this->enumValue($row['status'] ?? null);
            $di = null !== $dept ? ($deptIndex[$dept] ?? null) : null;
            $ai = null !== $area ? ($areaIndex[$area] ?? null) : null;
            $si = null !== $status ? ($statusIndex[$status] ?? null) : null;

            if (null !== $di) {
                $deptsSeen[$di] = true;
            }
            if (null !== $di && null !== $ai) {
                ++$heatmap[$di][$ai];
                $reachByDept[$ai][$di] = true;
                $titlesByAreaDept[$area][$dept][] = (string) $row['title'];
            }
            if (null !== $si) {
                ++$statusDistribution[$si];
                if (null !== $di) {
                    ++$statusByDept[$si][$di];
                }
            }
            if (null !== $di && null !== ($row['budget'] ?? null)) {
                $budgetByDept[$di] += (int) $row['budget'];
            }
            foreach (($row['funding'] ?? []) as $f) {
                $fi = $fundingIndex[$f] ?? null;
                if (null !== $fi) {
                    ++$fundingCount[$fi];
                }
            }
            if (($row['timePeriodStart'] ?? null) instanceof \DateTimeInterface
                && ($row['timePeriodEnd'] ?? null) instanceof \DateTimeInterface) {
                $timeline[] = [
                    'title' => (string) $row['title'],
                    'dept' => $dept,
                    'start' => $row['timePeriodStart']->format('Y-m-d'),
                    'end' => $row['timePeriodEnd']->format('Y-m-d'),
                ];
            }
        }

        usort($timeline, static fn (array $a, array $b): int => strcmp($a['start'], $b['start']));

        $inProgress = $statusDistribution[$statusIndex[Status::Active->value]] ?? 0;
        $collaborationCount = 0;
        foreach ($areas as $area) {
            if (\count($titlesByAreaDept[$area['key']] ?? []) >= 2) {
                ++$collaborationCount;
            }
        }

        return [
            'kpis' => [
                'total' => $total,
                'inProgress' => $inProgress,
                'departments' => \count($deptsSeen),
                'departmentsTotal' => \count($departments),
                'collaboration' => $collaborationCount,
            ],
            'departments' => $departments,
            'areas' => $areas,
            'statuses' => $this->labelled($statuses),
            'fundings' => $this->labelled($fundings),
            'heatmap' => $heatmap,
            'statusByDept' => $statusByDept,
            'statusDistribution' => $statusDistribution,
            'budgetByDept' => $budgetByDept,
            'fundingCount' => $fundingCount,
            'reach' => $this->reach($areas, $reachByDept),
            'collaboration' => $this->collaboration($areas, $departments, $titlesByAreaDept),
            'timeline' => \array_slice($timeline, 0, 10),
        ];
    }

    /**
     * Cross-department themes: an area worked on in two or more departments
     * is a candidate for "sammenfald". Ranked by how broadly it spans.
     *
     * @param list<array{key: string, label: string}>    $areas
     * @param list<array{key: string, label: string}>    $departments
     * @param array<string, array<string, list<string>>> $titlesByAreaDept
     *
     * @return list<array<string, mixed>>
     */
    private function collaboration(array $areas, array $departments, array $titlesByAreaDept): array
    {
        $deptLabel = [];
        foreach ($departments as $d) {
            $deptLabel[$d['key']] = $d['label'];
        }

        $opportunities = [];
        foreach ($areas as $area) {
            $byDept = $titlesByAreaDept[$area['key']] ?? [];
            if (\count($byDept) < 2) {
                continue;
            }

            $departmentsInvolved = [];
            $inits = [];
            $total = 0;
            foreach ($byDept as $deptValue => $titles) {
                $total += \count($titles);
                $departmentsInvolved[] = ['key' => $deptValue, 'label' => $deptLabel[$deptValue], 'count' => \count($titles)];
                $inits[] = ['title' => $titles[0], 'deptKey' => $deptValue, 'deptLabel' => $deptLabel[$deptValue]];
            }

            $distinctDepts = \count($byDept);
            $strength = min(100, $distinctDepts * 22 + min($total, 8) * 4);
            $opportunities[] = [
                'theme' => $area['label'],
                'themeKey' => $area['key'],
                'departmentCount' => $distinctDepts,
                'projectCount' => $total,
                'strength' => $strength,
                'rank' => $strength >= 70 ? 'high' : 'med',
                'departments' => $departmentsInvolved,
                'inits' => \array_slice($inits, 0, 4),
            ];
        }

        usort($opportunities, static fn (array $a, array $b): int => $b['strength'] <=> $a['strength']);

        return \array_slice($opportunities, 0, 5);
    }

    /**
     * @param list<array{key: string, label: string}> $areas
     * @param list<array<int, bool>>                  $reachByDept
     *
     * @return list<array<string, mixed>>
     */
    private function reach(array $areas, array $reachByDept): array
    {
        $reach = [];
        foreach ($areas as $ai => $area) {
            $reach[] = ['label' => $area['label'], 'depts' => \count($reachByDept[$ai])];
        }

        // Kept in area order (not sorted by count) so the radar axes stay stable
        // across live updates rather than rotating when a count changes.
        return $reach;
    }

    /**
     * @param list<\BackedEnum&\App\Enum\TranslatableEnum> $cases
     *
     * @return list<array{key: string, label: string}>
     */
    private function labelled(array $cases): array
    {
        return array_map(fn ($case): array => ['key' => $case->value, 'label' => $this->t($case->labelKey())], $cases);
    }

    /**
     * @param list<\BackedEnum> $cases
     *
     * @return array<string, int>
     */
    private function index(array $cases): array
    {
        $map = [];
        foreach ($cases as $i => $case) {
            $map[$case->value] = $i;
        }

        return $map;
    }

    /**
     * @param list<array{key: string, label: string}> $rows
     *
     * @return array<string, int>
     */
    private function indexKeys(array $rows): array
    {
        $map = [];
        foreach ($rows as $i => $row) {
            $map[$row['key']] = $i;
        }

        return $map;
    }

    /**
     * @return list<list<int>>
     */
    private function zeroMatrix(int $rows, int $cols): array
    {
        return array_fill(0, $rows, array_fill(0, $cols, 0));
    }

    private function enumValue(mixed $value): ?string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return null !== $value ? (string) $value : null;
    }

    private function t(string $key): string
    {
        return $this->translator->trans($key);
    }
}
