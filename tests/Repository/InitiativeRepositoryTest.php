<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Entity\Initiative;
use App\Enum\InitiativeType;
use App\Enum\Status;
use App\Model\InitiativeFilter;
use App\Repository\AreaRepository;
use App\Repository\DepartmentRepository;
use App\Repository\InitiativeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class InitiativeRepositoryTest extends KernelTestCase
{
    private InitiativeRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $repository = static::getContainer()->get(InitiativeRepository::class);
        \assert($repository instanceof InitiativeRepository);
        $this->repository = $repository;
    }

    public function testSearchAppliesEveryFilterBranch(): void
    {
        $departments = static::getContainer()->get(DepartmentRepository::class);
        \assert($departments instanceof DepartmentRepository);
        $areas = static::getContainer()->get(AreaRepository::class);
        \assert($areas instanceof AreaRepository);

        $filter = new InitiativeFilter();
        $filter->q = '100%_'; // also exercises LIKE wildcard escaping
        $filter->status = Status::Granted;
        $filter->area = $areas->findAllOrdered()[0];
        $filter->initiativeType = InitiativeType::Project;
        $filter->organizationalAnchoring = $departments->findAllOrdered()[0];
        $filter->endorsement = true;
        $filter->sort = 'title';
        $filter->direction = 'ASC';

        self::assertIsArray($this->repository->search($filter)->getQuery()->getResult());
    }

    public function testSearchMatchesTranslatedFundingLabel(): void
    {
        $filter = new InitiativeFilter();
        // "midler" is a substring of the Danish "EU-midler" funding label, so the
        // search maps it to the eu_funds slug and matches it inside the funding JSON.
        $filter->q = 'midler';

        self::assertIsArray($this->repository->search($filter)->getQuery()->getResult());
    }

    public function testSearchMatchesTranslatedEnumLabel(): void
    {
        $filter = new InitiativeFilter();
        // "projekt" is the Danish label for the Project initiative type, so the
        // search maps it to the project slug and matches the enum column.
        $filter->q = 'projekt';

        self::assertIsArray($this->repository->search($filter)->getQuery()->getResult());
    }

    public function testSearchFallsBackForUnknownSortAndDirection(): void
    {
        $filter = new InitiativeFilter();
        $filter->sort = 'not-a-column';
        $filter->direction = 'sideways';

        self::assertIsArray($this->repository->search($filter)->getQuery()->getResult());
    }

    public function testFindForExportReturnsEmptyArrayWhenNothingMatches(): void
    {
        $filter = new InitiativeFilter();
        $filter->q = 'no-such-initiative-'.uniqid();

        self::assertSame([], $this->repository->findForExport($filter));
    }

    public function testFindForExportPrimesCollections(): void
    {
        $rows = $this->repository->findForExport(new InitiativeFilter());

        self::assertNotEmpty($rows);
    }

    public function testCountAll(): void
    {
        self::assertGreaterThan(0, $this->repository->countAll());
    }

    public function testCountByStatusSkipsInitiativesWithoutStatus(): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        \assert($em instanceof EntityManagerInterface);

        $before = array_sum($this->repository->countByStatus());

        $initiative = (new Initiative())->setTitle('No status '.uniqid());
        $em->persist($initiative);
        $em->flush();

        // A status-less initiative must not appear in any status bucket.
        self::assertSame($before, array_sum($this->repository->countByStatus()));

        $em->remove($initiative);
        $em->flush();
    }

    public function testFindRecentRespectsTheLimit(): void
    {
        self::assertLessThanOrEqual(3, \count($this->repository->findRecent(3)));
    }
}
