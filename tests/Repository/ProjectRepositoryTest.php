<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Area;
use App\Entity\Department;
use App\Entity\Project;
use App\Entity\ProjectType;
use App\Enum\Status;
use App\Model\ProjectFilter;
use App\Repository\AreaRepository;
use App\Repository\DepartmentRepository;
use App\Repository\ProjectRepository;
use App\Repository\ProjectTypeRepository;
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
        $types = static::getContainer()->get(ProjectTypeRepository::class);
        \assert($types instanceof ProjectTypeRepository);

        $filter = new ProjectFilter();
        $filter->q = '100%_'; // also exercises LIKE wildcard escaping
        $filter->status = Status::Granted;
        $filter->area = $areas->findAllOrdered()[0];
        $filter->type = $types->findAllOrdered()[0];
        $filter->organizationalAnchoring = $departments->findAllOrdered()[0];
        $filter->endorsement = true;
        $filter->sort = 'title';
        $filter->direction = 'ASC';

        self::assertIsArray($this->repository->search($filter)->getQuery()->getResult());
    }

    public function testSearchByDepartmentAreaOrTypeMatchesOnlyProjectsWithThem(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        $department = (new Department())->setName('Filter dept '.uniqid());
        $area = (new Area())->setName('Filter area '.uniqid());
        $type = (new ProjectType())->setName('Filter type '.uniqid());
        $anchored = (new Project())
            ->setTitle('Anchored '.uniqid())
            ->addOrganizationalAnchoring($department)
            ->addType($type)
            ->setArea($area);
        $loose = (new Project())->setTitle('Loose '.uniqid());
        foreach ([$department, $area, $type, $anchored, $loose] as $entity) {
            $em->persist($entity);
        }
        $em->flush();

        $byDepartment = new ProjectFilter();
        $byDepartment->organizationalAnchoring = $department;
        $byArea = new ProjectFilter();
        $byArea->area = $area;
        $byType = new ProjectFilter();
        $byType->type = $type;
        // The free-text search matches the type by its stored name.
        $byTypeName = new ProjectFilter();
        $byTypeName->q = (string) $type->getName();

        // Every filter must actually narrow the list: the binary ULID foreign keys
        // only match when the id is bound with the ulid type, so an entity bound
        // as-is would silently return nothing.
        foreach ([$byDepartment, $byArea, $byType, $byTypeName] as $filter) {
            self::assertSame([$anchored->getTitle()], $this->titles($filter));
        }

        foreach ([$anchored, $loose, $department, $area, $type] as $entity) {
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
        // "bevilliget" is the Danish label for the Granted status, so the search
        // maps it to the granted slug and matches the enum column.
        $filter->q = 'bevilliget';

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
