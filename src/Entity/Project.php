<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\EndorsementAuthor;
use App\Enum\Funding;
use App\Enum\FundingRate;
use App\Enum\Status;
use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project extends AbstractEntity
{
    /**
     * Fields that count toward {@see getCompletionPercentage()} and the client-side
     * progress bar. Limited to the project's own columns so list rendering stays
     * query-free; the booleans and the free-tagging lists are intentionally excluded.
     * Areas, types and departments are the three collections counted: all are
     * fixed, admin-managed pools, and what a project is about, what kind of thing
     * it is and where it is anchored are core to describing it. Of the economy
     * fields, co-financing is a boolean and the remaining-funding text is
     * explicitly optional, so neither counts.
     *
     * @var list<string>
     */
    public const array COMPLETION_FIELDS = [
        'title', 'areas', 'summary', 'description', 'types', 'status',
        'organizationalAnchoring',
        'amountApplied', 'budget', 'budgetItk', 'fundingRate', 'funding',
        'timePeriodStart', 'timePeriodEnd',
    ];

    #[Assert\NotBlank]
    #[ORM\Column(length: 255)]
    private ?string $title = null;

    /**
     * Which wider programme the project is a part of — the title names this
     * project, the topic places it ("DS4SSCC" → "Digital Europe Blueprint for
     * Data Space for smart and sustainable cities and communities").
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $topic = null;

    /**
     * The thematic areas a project belongs to. Like departments, an admin-managed
     * pool a project can sit in several of.
     *
     * @var Collection<int, Area>
     */
    #[ORM\ManyToMany(targetEntity: Area::class)]
    #[ORM\JoinTable(name: 'project_area')]
    private Collection $areas;

    /** A few lines summing up the project's purpose, content, strategy and plans. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summary = null;

    /** The fuller account: why the project is done and what it is anchored in. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /**
     * What kind of thing the project is ("Projekt", "Drift", …). A project can be
     * several at once, e.g. a pilot that is also operations.
     *
     * @var Collection<int, ProjectType>
     */
    #[ORM\ManyToMany(targetEntity: ProjectType::class)]
    #[ORM\JoinTable(name: 'project_project_type')]
    private Collection $types;

    #[ORM\Column(length: 32, nullable: true, enumType: Status::class)]
    private ?Status $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $statusAdditional = null;

    /**
     * The departments a project is anchored in. Cross-cutting projects belong to
     * several, which is what the dashboard's collaboration views build on.
     *
     * @var Collection<int, Department>
     */
    #[ORM\ManyToMany(targetEntity: Department::class)]
    #[ORM\JoinTable(name: 'project_department')]
    private Collection $organizationalAnchoring;

    #[ORM\Column]
    private bool $endorsement = false;

    #[ORM\Column(length: 32, nullable: true, enumType: EndorsementAuthor::class)]
    private ?EndorsementAuthor $endorsementAuthor = null;

    /** @var Collection<int, Contact> */
    #[ORM\ManyToMany(targetEntity: Contact::class, cascade: ['persist'])]
    #[ORM\JoinTable(name: 'project_contact')]
    private Collection $contacts;

    /**
     * Not cascade-validated, as on the other free-tagging collections: a violation
     * would carry the path partners[0].name, which the single text input cannot render.
     *
     * @var Collection<int, Partner>
     */
    #[ORM\ManyToMany(targetEntity: Partner::class, cascade: ['persist'])]
    #[ORM\JoinTable(name: 'project_partner')]
    private Collection $partners;

    /** How much money the project has applied for, in whole kroner. */
    #[Assert\PositiveOrZero]
    #[ORM\Column(nullable: true)]
    private ?int $amountApplied = null;

    /** The project's total budget, in whole kroner. */
    #[Assert\PositiveOrZero]
    #[ORM\Column(nullable: true)]
    private ?int $budget = null;

    /** The part of the total budget that lies with ITK, in whole kroner. */
    #[Assert\PositiveOrZero]
    #[ORM\Column(nullable: true)]
    private ?int $budgetItk = null;

    /** Whether the project is partly paid by co-financing or own financing. */
    #[ORM\Column]
    private bool $coFinancing = false;

    #[ORM\Column(length: 32, nullable: true, enumType: FundingRate::class)]
    private ?FundingRate $fundingRate = null;

    /** What the share of the budget not covered by the funding rate consists of. */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $remainingFunding = null;

    /**
     * Stored as the backing values of {@see Funding}; accessors expose enums.
     *
     * @var list<string>
     */
    #[ORM\Column]
    private array $funding = [];

    /** @var Collection<int, Term> */
    #[ORM\ManyToMany(targetEntity: Term::class, cascade: ['persist'])]
    #[ORM\JoinTable(name: 'project_tag')]
    private Collection $tags;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $timePeriodStart = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $timePeriodEnd = null;

    /**
     * Relevant links, each a url with an optional note saying what it points to.
     *
     * @var list<array{url: string, note: string|null}>
     */
    #[ORM\Column]
    private array $links = [];

    public function __construct()
    {
        parent::__construct();
        $this->areas = new ArrayCollection();
        $this->organizationalAnchoring = new ArrayCollection();
        $this->types = new ArrayCollection();
        $this->contacts = new ArrayCollection();
        $this->partners = new ArrayCollection();
        $this->tags = new ArrayCollection();
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getTopic(): ?string
    {
        return $this->topic;
    }

    public function setTopic(?string $topic): static
    {
        $this->topic = $topic;

        return $this;
    }

    /** @return Collection<int, Area> */
    public function getAreas(): Collection
    {
        return $this->areas;
    }

    public function addArea(Area $area): static
    {
        if (!$this->areas->contains($area)) {
            $this->areas->add($area);
        }

        return $this;
    }

    public function removeArea(Area $area): static
    {
        $this->areas->removeElement($area);

        return $this;
    }

    /** @param iterable<Area> $areas */
    public function setAreas(iterable $areas): static
    {
        $this->areas->clear();
        foreach ($areas as $area) {
            $this->addArea($area);
        }

        return $this;
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): static
    {
        $this->summary = $summary;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /** @return Collection<int, ProjectType> */
    public function getTypes(): Collection
    {
        return $this->types;
    }

    public function addType(ProjectType $type): static
    {
        if (!$this->types->contains($type)) {
            $this->types->add($type);
        }

        return $this;
    }

    public function removeType(ProjectType $type): static
    {
        $this->types->removeElement($type);

        return $this;
    }

    /** @param iterable<ProjectType> $types */
    public function setTypes(iterable $types): static
    {
        $this->types->clear();
        foreach ($types as $type) {
            $this->addType($type);
        }

        return $this;
    }

    public function getStatus(): ?Status
    {
        return $this->status;
    }

    public function setStatus(?Status $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getStatusAdditional(): ?string
    {
        return $this->statusAdditional;
    }

    public function setStatusAdditional(?string $statusAdditional): static
    {
        $this->statusAdditional = $statusAdditional;

        return $this;
    }

    /** @return Collection<int, Department> */
    public function getOrganizationalAnchoring(): Collection
    {
        return $this->organizationalAnchoring;
    }

    public function addOrganizationalAnchoring(Department $department): static
    {
        if (!$this->organizationalAnchoring->contains($department)) {
            $this->organizationalAnchoring->add($department);
        }

        return $this;
    }

    public function removeOrganizationalAnchoring(Department $department): static
    {
        $this->organizationalAnchoring->removeElement($department);

        return $this;
    }

    /** @param iterable<Department> $departments */
    public function setOrganizationalAnchoring(iterable $departments): static
    {
        $this->organizationalAnchoring->clear();
        foreach ($departments as $department) {
            $this->addOrganizationalAnchoring($department);
        }

        return $this;
    }

    public function isEndorsement(): bool
    {
        return $this->endorsement;
    }

    public function setEndorsement(bool $endorsement): static
    {
        $this->endorsement = $endorsement;

        return $this;
    }

    public function getEndorsementAuthor(): ?EndorsementAuthor
    {
        return $this->endorsementAuthor;
    }

    public function setEndorsementAuthor(?EndorsementAuthor $endorsementAuthor): static
    {
        $this->endorsementAuthor = $endorsementAuthor;

        return $this;
    }

    /** @return Collection<int, Contact> */
    public function getContacts(): Collection
    {
        return $this->contacts;
    }

    public function addContact(Contact $contact): static
    {
        if (!$this->contacts->contains($contact)) {
            $this->contacts->add($contact);
        }

        return $this;
    }

    public function removeContact(Contact $contact): static
    {
        $this->contacts->removeElement($contact);

        return $this;
    }

    /** @return Collection<int, Partner> */
    public function getPartners(): Collection
    {
        return $this->partners;
    }

    public function addPartner(Partner $partner): static
    {
        if (!$this->partners->contains($partner)) {
            $this->partners->add($partner);
        }

        return $this;
    }

    public function removePartner(Partner $partner): static
    {
        $this->partners->removeElement($partner);

        return $this;
    }

    public function getAmountApplied(): ?int
    {
        return $this->amountApplied;
    }

    public function setAmountApplied(?int $amountApplied): static
    {
        $this->amountApplied = $amountApplied;

        return $this;
    }

    public function getBudget(): ?int
    {
        return $this->budget;
    }

    public function setBudget(?int $budget): static
    {
        $this->budget = $budget;

        return $this;
    }

    public function getBudgetItk(): ?int
    {
        return $this->budgetItk;
    }

    public function setBudgetItk(?int $budgetItk): static
    {
        $this->budgetItk = $budgetItk;

        return $this;
    }

    public function isCoFinancing(): bool
    {
        return $this->coFinancing;
    }

    public function setCoFinancing(bool $coFinancing): static
    {
        $this->coFinancing = $coFinancing;

        return $this;
    }

    public function getFundingRate(): ?FundingRate
    {
        return $this->fundingRate;
    }

    public function setFundingRate(?FundingRate $fundingRate): static
    {
        $this->fundingRate = $fundingRate;

        return $this;
    }

    public function getRemainingFunding(): ?string
    {
        return $this->remainingFunding;
    }

    public function setRemainingFunding(?string $remainingFunding): static
    {
        $this->remainingFunding = $remainingFunding;

        return $this;
    }

    /** @return Funding[] */
    public function getFunding(): array
    {
        return array_values(array_filter(array_map(
            static fn (string $value): ?Funding => Funding::tryFrom($value),
            $this->funding,
        )));
    }

    /** @param Funding[] $funding */
    public function setFunding(array $funding): static
    {
        $this->funding = array_values(array_map(
            static fn (Funding $item): string => $item->value,
            $funding,
        ));

        return $this;
    }

    /** @return Collection<int, Term> */
    public function getTags(): Collection
    {
        return $this->tags;
    }

    public function addTag(Term $term): static
    {
        if (!$this->tags->contains($term)) {
            $this->tags->add($term);
        }

        return $this;
    }

    public function removeTag(Term $term): static
    {
        $this->tags->removeElement($term);

        return $this;
    }

    /** @param iterable<Term> $terms */
    public function setTags(iterable $terms): static
    {
        $this->tags->clear();
        foreach ($terms as $term) {
            $this->addTag($term);
        }

        return $this;
    }

    public function getTimePeriodStart(): ?\DateTimeImmutable
    {
        return $this->timePeriodStart;
    }

    public function setTimePeriodStart(?\DateTimeImmutable $timePeriodStart): static
    {
        $this->timePeriodStart = $timePeriodStart;

        return $this;
    }

    public function getTimePeriodEnd(): ?\DateTimeImmutable
    {
        return $this->timePeriodEnd;
    }

    public function setTimePeriodEnd(?\DateTimeImmutable $timePeriodEnd): static
    {
        $this->timePeriodEnd = $timePeriodEnd;

        return $this;
    }

    /** @return list<array{url: string, note: string|null}> */
    public function getLinks(): array
    {
        return $this->links;
    }

    /**
     * Rows without a url are dropped (the form's empty prototype row posts as
     * one), as are non-http(s) urls, since a javascript: scheme would be stored XSS.
     *
     * @param list<array{url?: string|null, note?: string|null}> $links
     */
    public function setLinks(array $links): static
    {
        $this->links = [];
        foreach ($links as $link) {
            $url = trim((string) ($link['url'] ?? ''));
            $scheme = strtolower((string) parse_url($url, \PHP_URL_SCHEME));
            if ('http' !== $scheme && 'https' !== $scheme) {
                continue;
            }
            $note = trim((string) ($link['note'] ?? ''));
            $this->links[] = ['url' => $url, 'note' => '' === $note ? null : $note];
        }

        return $this;
    }

    /**
     * Share of {@see COMPLETION_FIELDS} that are filled in, as a 0–100 percentage.
     * Reads own columns plus the area, type and department collections (one lazy
     * load each), so it is cheap enough to call per row in a listing.
     */
    public function getCompletionPercentage(): int
    {
        $checks = [
            null !== $this->title && '' !== $this->title,
            !$this->areas->isEmpty(),
            null !== $this->summary && '' !== $this->summary,
            null !== $this->description && '' !== $this->description,
            !$this->types->isEmpty(),
            null !== $this->status,
            !$this->organizationalAnchoring->isEmpty(),
            null !== $this->amountApplied,
            null !== $this->budget,
            null !== $this->budgetItk,
            null !== $this->fundingRate,
            [] !== $this->funding,
            null !== $this->timePeriodStart,
            null !== $this->timePeriodEnd,
        ];

        return (int) round(\count(array_filter($checks)) / \count($checks) * 100);
    }

    /**
     * Whether the project has been edited since it was created — drives the
     * "updated" vs "created" label in the dashboard activity feed. Only counts as
     * an edit once it's more than a day past creation, so the initial save and any
     * same-day tweaks still read as "created".
     */
    public function wasUpdatedAfterCreation(): bool
    {
        $created = $this->getCreatedAt();
        $updated = $this->getUpdatedAt();

        if (null === $created || null === $updated) {
            return false;
        }

        return $updated->getTimestamp() - $created->getTimestamp() > 60 * 60 * 24;
    }

    public function __toString(): string
    {
        return (string) $this->title;
    }
}
