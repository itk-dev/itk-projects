<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ContactRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A person attached to projects. Names are deliberately not unique — two people
 * can share one — so the project form's picker identifies contacts by id and
 * shows {@see getLabel()} to tell namesakes apart.
 */
#[ORM\Entity(repositoryClass: ContactRepository::class)]
class Contact extends AbstractEntity
{
    public const int NAME_MAX_LENGTH = 255;

    #[Assert\NotBlank]
    #[Assert\Length(max: self::NAME_MAX_LENGTH)]
    #[ORM\Column(length: self::NAME_MAX_LENGTH)]
    private ?string $name = null;

    #[Assert\Email]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $phone = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true)]
    private ?Department $department = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    /**
     * The name with the email in parentheses, when there is one: what the
     * project form's contact picker shows so namesakes can be told apart. The
     * plain name stays the string form (admin lists, CSV export).
     */
    public function getLabel(): string
    {
        $email = trim((string) $this->email);

        return '' === $email ? (string) $this->name : sprintf('%s (%s)', $this->name, $email);
    }

    public function __toString(): string
    {
        return (string) $this->name;
    }

    public function getDepartment(): ?Department
    {
        return $this->department;
    }

    public function setDepartment(?Department $department): static
    {
        $this->department = $department;

        return $this;
    }
}
