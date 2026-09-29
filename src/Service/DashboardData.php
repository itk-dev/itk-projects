<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Department;
use App\Enum\Status;
use App\Repository\DepartmentRepository;
use App\Repository\ProjectRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Builds the dashboard numbers (KPIs, status distribution and budget per
 * department) as one plain array, ready to be JSON encoded into the page and
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
        // Departments are managed entities, so normalise them to the same
        // {key, label} shape the enums get; the key is the id as a string, which is
        // what dashboardRows() returns by joining and selecting the id.
        $departments = array_map(
            static fn (Department $department): array => ['key' => (string) $department->getId(), 'label' => (string) $department->getName()],
            $this->departments->findAllOrdered(),
        );
        $statuses = Status::cases();

        $deptIndex = $this->indexKeys($departments);
        $statusIndex = $this->index($statuses);

        $statusDistribution = array_fill(0, \count($statuses), 0);
        $budgetByDept = array_fill(0, \count($departments), 0);

        /** @var array<string, array<int, true>> $deptsByArea */
        $deptsByArea = [];
        $total = 0;
        $deptsSeen = [];

        foreach ($this->projects->dashboardRows() as $row) {
            ++$total;
            $area = $this->enumValue($row['area'] ?? null);
            $status = $this->enumValue($row['status'] ?? null);
            $si = null !== $status ? ($statusIndex[$status] ?? null) : null;

            // A project is anchored in any number of departments; keep only the
            // ids that resolve to a known department, as department indexes.
            $depts = [];
            foreach (($row['organizationalAnchoring'] ?? []) as $deptId) {
                $dept = $this->enumValue($deptId);
                if (null !== $dept && isset($deptIndex[$dept])) {
                    $depts[$dept] = $deptIndex[$dept];
                }
            }

            if (null !== $si) {
                ++$statusDistribution[$si];
            }

            // Every per-department aggregate counts a shared project once under
            // each of its departments: the budget shows what each department is
            // involved in, so the bars sum to more than the plain total.
            foreach ($depts as $di) {
                $deptsSeen[$di] = true;
                if (null !== $area) {
                    $deptsByArea[$area][$di] = true;
                }
                if (null !== ($row['budget'] ?? null)) {
                    $budgetByDept[$di] += (int) $row['budget'];
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
        $inProgress = $statusDistribution[$statusIndex[Status::Granted->value]] ?? 0;

        return [
            'kpis' => [
                'total' => $total,
                'inProgress' => $inProgress,
                'departments' => \count($deptsSeen),
                'departmentsTotal' => \count($departments),
                'collaboration' => $collaborationCount,
            ],
            'departments' => $departments,
            'statuses' => $this->labelled($statuses),
            'statusDistribution' => $statusDistribution,
            'budgetByDept' => $budgetByDept,
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
