<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Partner;
use App\Entity\Project;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Ulid;

/**
 * @phpstan-type PartnerUsage list<array{id: string, title: string}>
 *
 * @extends ServiceEntityRepository<Partner>
 */
class PartnerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Partner::class);
    }

    /**
     * @return Partner[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Which projects reference each partner, keyed by partner id. Answers the
     * whole admin list in one query so the delete confirmation can name the
     * projects a partner would be pulled off.
     *
     * @return array<string, PartnerUsage>
     */
    public function findProjectUsage(): array
    {
        // Project owns the (unidirectional) association, so usage can only be
        // asked from that side — Partner has no inverse collection to traverse.
        // Selecting the joined p.id (rather than IDENTITY()) is what makes Doctrine
        // apply the ULID type instead of handing back the raw binary FK.
        /** @var list<array{partnerId: Ulid, id: Ulid, title: string}> $rows */
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('p.id AS partnerId', 'i.id AS id', 'i.title AS title')
            ->from(Project::class, 'i')
            ->innerJoin('i.partners', 'p')
            ->orderBy('i.title', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $usage = [];
        foreach ($rows as $row) {
            $usage[(string) $row['partnerId']][] = [
                'id' => (string) $row['id'],
                'title' => $row['title'],
            ];
        }

        return $usage;
    }

    /**
     * The projects referencing a single partner. Read this *before* removing the
     * partner: `project_partner` is cleared by the join table's ON DELETE
     * CASCADE, so after the flush there is nothing left to report.
     *
     * @return PartnerUsage
     */
    public function findProjectsUsing(Partner $partner): array
    {
        /** @var list<array{id: Ulid, title: string}> $rows */
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('i.id AS id', 'i.title AS title')
            ->from(Project::class, 'i')
            ->innerJoin('i.partners', 'p')
            ->andWhere('p.id = :partner')
            ->setParameter('partner', $partner->getId(), 'ulid')
            ->orderBy('i.title', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [
            'id' => (string) $row['id'],
            'title' => $row['title'],
        ], $rows);
    }

    /**
     * Return an existing partner matched on name (case-insensitive) or a new,
     * unflushed one. Lets partners be picked from the shared pool or typed in on
     * the fly; the extra fields (description, website) are filled in later under
     * the partners admin.
     */
    public function findOrCreate(string $name): Partner
    {
        $name = trim($name);

        $existing = $this->createQueryBuilder('p')
            ->andWhere('LOWER(p.name) = :name')
            ->setParameter('name', mb_strtolower($name))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existing instanceof Partner) {
            return $existing;
        }

        $partner = (new Partner())->setName($name);
        $this->getEntityManager()->persist($partner);

        return $partner;
    }
}
