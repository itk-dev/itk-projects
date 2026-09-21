<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Contact;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contact>
 */
class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    /**
     * @return Contact[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Return an existing contact matched on name (case-insensitive) or a new,
     * unflushed one. Lets people be picked from the shared pool or typed in on
     * the fly; the extra fields (email, phone, department) are filled in later
     * under the contacts admin.
     */
    public function findOrCreate(string $name): Contact
    {
        $name = trim($name);

        $existing = $this->createQueryBuilder('c')
            ->andWhere('LOWER(c.name) = :name')
            ->setParameter('name', mb_strtolower($name))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existing instanceof Contact) {
            return $existing;
        }

        $contact = (new Contact())->setName($name);
        $this->getEntityManager()->persist($contact);

        return $contact;
    }

    /**
     * The user's most recent contact that still lacks an email — typically one
     * they created on the fly from a project's contact picker (name only).
     * Used by the mascot to nudge them to fill in the rest.
     */
    public function findIncompleteByCreator(User $user): ?Contact
    {
        return $this->findIncompleteListByCreator($user, 1)[0] ?? null;
    }

    /**
     * The user's contacts that still lack an email (created name-only and not yet
     * finished), most recent first, capped at $limit. Surfaced on the dashboard
     * as outstanding work.
     *
     * @return Contact[]
     */
    public function findIncompleteListByCreator(User $user, int $limit = 6): array
    {
        // createdBy is a ManyToOne to the UserInterface (resolved to User via
        // resolve_target_entities); binding the entity to a ULID FK doesn't match,
        // so compare the raw FK against the user's id with the ulid type applied.
        return $this->createQueryBuilder('c')
            ->andWhere('IDENTITY(c.createdBy) = :user')
            ->andWhere("(c.email IS NULL OR c.email = '')")
            ->setParameter('user', $user->getId(), 'ulid')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
