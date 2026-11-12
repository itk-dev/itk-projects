<?php

declare(strict_types=1);

namespace App\Service;

use App\Enum\Status;
use App\Repository\DepartmentRepository;
use App\Repository\ProjectRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds the dashboard numbers (KPIs, status distribution and budget per
 * status) as one plain array, ready to be JSON encoded into the page and
 * re-encoded for live broadcasts. Labels are translated here so the client
 * controller stays language-agnostic; colours are a presentation concern and
 * live in the Stimulus controller instead.
 */
final class DashboardData
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly DepartmentRepository $departments,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        // Departments are managed entities, keyed by the id as a string, which
        // is what dashboardRows() returns by joining and selecting the id.
        $deptIndex = [];
        foreach ($this->departments->findAllOrdered() as $i => $department) {
            $deptIndex[(string) $department->getId()] = $i;
        }
        $statuses = Status::cases();
        $statusIndex = $this->index($statuses);

        $statusDistribution = array_fill(0, \count($statuses), 0);
        $budgetByStatus = array_fill(0, \count($statuses), 0);

        /** @var array<string, array<int, true>> $deptsByArea */
        $deptsByArea = [];
        $total = 0;
        $deptsSeen = [];

        foreach ($this->projects->dashboardRows() as $row) {
            ++$total;
            $status = $this->enumValue($row['status'] ?? null);
            $si = null !== $status ? ($statusIndex[$status] ?? null) : null;

            // A project belongs to any number of areas, keyed by id.
            $areas = [];
            foreach (($row['areas'] ?? []) as $areaId) {
                $area = $this->enumValue($areaId);
                if (null !== $area) {
                    $areas[$area] = true;
                }
            }

            // A project is anchored in any number of departments; keep only the
            // ids that resolve to a known department, as department indexes.
            $depts = [];
            foreach (($row['organizationalAnchoring'] ?? []) as $deptId) {
                $dept = $this->enumValue($deptId);
                if (null !== $dept && isset($deptIndex[$dept])) {
                    $depts[$dept] = $deptIndex[$dept];
                }
            }

            // A project has exactly one status, so the budget bars add up to the
            // total budget of every project that has a status.
            if (null !== $si) {
                ++$statusDistribution[$si];
                $budgetByStatus[$si] += (int) ($row['budget'] ?? 0);
            }

            // Each of the project's areas is marked as worked on in each of its
            // departments.
            foreach ($depts as $di) {
                $deptsSeen[$di] = true;
                foreach (array_keys($areas) as $area) {
                    $deptsByArea[$area][$di] = true;
                }
            }
        }

        // An area worked on in two or more departments counts as a possible
        // collaboration ("sammenfald").
        $collaborationCount = \count(array_filter(
            $deptsByArea,
            static fn (array $depts): bool => \count($depts) >= 2,
        ));

        // "In progress" on the dashboard means funded and running, i.e. granted;
        // applications still being written or awaiting an answer are not counted.
        $inProgressStatus = Status::Granted;
        $inProgress = $statusDistribution[$statusIndex[$inProgressStatus->value]] ?? 0;

        return [
            'kpis' => [
                'total' => $total,
                'inProgress' => $inProgress,
                // The status behind the count, so the KPI can link to the list
                // filtered the same way it counts.
                'inProgressStatus' => $inProgressStatus->value,
                'departments' => \count($deptsSeen),
                'collaboration' => $collaborationCount,
            ],
            'statuses' => $this->labelled($statuses),
            'statusDistribution' => $statusDistribution,
            'budgetByStatus' => $budgetByStatus,
        ];
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
