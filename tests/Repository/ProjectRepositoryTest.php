<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Area;
use App\Entity\Department;
use App\Entity\Project;
use App\Enum\ProjectType;
use App\Enum\Status;
use App\Model\ProjectFilter;
use App\Repository\AreaRepository;
use App\Repository\DepartmentRepository;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProjectRepositoryTest extends KernelTestCase
{
    private ProjectRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $repository = static::getContainer()->get(ProjectRepository::class);
        \assert($repository instanceof ProjectRepository);
        $this->repository = $repository;
    }

    public function testSearchAppliesEveryFilterBranch(): void
    {
        $departments = static::getContainer()->get(DepartmentRepository::class);
        \assert($departments instanceof DepartmentRepository);
        $areas = static::getContainer()->get(AreaRepository::class);
        \assert($areas instanceof AreaRepository);

        $filter = new ProjectFilter();
        $filter->q = '100%_'; // also exercises LIKE wildcard escaping
        $filter->status = Status::Active;
        $filter->area = $areas->findAllOrdered()[0];
        $filter->projectType = ProjectType::Project;
        $filter->organizationalAnchoring = $departments->findAllOrdered()[0];
        $filter->endorsement = true;
        $filter->sort = 'title';
        $filter->direction = 'ASC';

        self::assertIsArray($this->repository->search($filter)->getQuery()->getResult());
    }

    public function testSearchByDepartmentOrAreaMatchesOnlyProjectsWithThem(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        $department = (new Department())->setName('Filter dept '.uniqid());
        $area = (new Area())->setName('Filter area '.uniqid());
        $anchored = (new Project())
            ->setTitle('Anchored '.uniqid())
            ->addOrganizationalAnchoring($department)
            ->setArea($area);
        $loose = (new Project())->setTitle('Loose '.uniqid());
        foreach ([$department, $area, $anchored, $loose] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $byDepartment = new ProjectFilter();
        $byDepartment->organizationalAnchoring = $department;
        $byArea = new ProjectFilter();
        $byArea->area = $area;

        // Both filters must actually narrow the list: the binary ULID foreign keys
        // only match when the id is bound with the ulid type, so an entity bound
        // as-is would silently return nothing.
        foreach ([$byDepartment, $byArea] as $filter) {
            self::assertSame([$anchored->getTitle()], $this->titles($filter));
        }

        foreach ([$anchored, $loose, $department, $area] as $entity) {
            $em->remove($entity);
        }
        $em->flush();
    }

    /** @return list<string> */
    private function titles(ProjectFilter $filter): array
    {
        return array_map(
            static fn (Project $project): string => (string) $project->getTitle(),
            $this->repository->search($filter)->getQuery()->getResult(),
        );
    }

    public function testSearchMatchesTranslatedFundingLabel(): void
    {
        $filter = new ProjectFilter();
        // "midler" is a substring of the Danish "EU-midler" funding label, so the
        // search maps it to the eu_funds slug and matches it inside the funding JSON.
        $filter->q = 'midler';

        self::assertIsArray($this->repository->search($filter)->getQuery()->getResult());
    }

    public function testSearchMatchesTranslatedEnumLabel(): void
    {
        $filter = new ProjectFilter();
        // "projekt" is the Danish label for the Project case of the project type, so the
        // search maps it to the project slug and matches the enum column.
        $filter->q = 'projekt';

        self::assertIsArray($this->repository->search($filter)->getQuery()->getResult());
    }

    public function testSearchFallsBackForUnknownSortAndDirection(): void
    {
        $filter = new ProjectFilter();
        $filter->sort = 'not-a-column';
        $filter->direction = 'sideways';

        self::assertIsArray($this->repository->search($filter)->getQuery()->getResult());
    }

    public function testFindForExportReturnsEmptyArrayWhenNothingMatches(): void
    {
        $filter = new ProjectFilter();
        $filter->q = 'no-such-project-'.uniqid();

        self::assertSame([], $this->repository->findForExport($filter));
    }

    public function testFindForExportPrimesCollections(): void
    {
        $rows = $this->repository->findForExport(new ProjectFilter());

        self::assertNotEmpty($rows);
    }

    public function testCountAll(): void
    {
        self::assertGreaterThan(0, $this->repository->countAll());
    }

    public function testCountByStatusSkipsProjectsWithoutStatus(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        $before = array_sum($this->repository->countByStatus());

        $project = (new Project())->setTitle('No status '.uniqid());
        $em->persist($project);
        $em->flush();

        // A status-less project must not appear in any status bucket.
        self::assertSame($before, array_sum($this->repository->countByStatus()));

        $em->remove($project);
        $em->flush();
    }

    public function testFindRecentRespectsTheLimit(): void
    {
        self::assertLessThanOrEqual(3, \count($this->repository->findRecent(3)));
    }
}
