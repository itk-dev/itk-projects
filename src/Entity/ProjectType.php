<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ProjectTypeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The type (or nature) of a project — "Projekt", "Program", "Drift", … An
 * admin-managed pool, like {@see Area} and {@see Department}, that a project
 * picks any number of.
 */
#[ORM\Entity(repositoryClass: ProjectTypeRepository::class)]
#[UniqueEntity(fields: ['name'], message: 'project_type.name_duplicate')]
class ProjectType extends AbstractEntity
{
    #[Assert\NotBlank]
    #[ORM\Column(length: 255, unique: true)]
    private ?string $name = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }
}
