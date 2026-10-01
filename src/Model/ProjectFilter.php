<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Area;
use App\Entity\Department;
use App\Enum\ProjectType;
use App\Enum\Status;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Bound to the project list filter form (GET) and consumed by
 * {@see \App\Repository\ProjectRepository::search()}.
 */
class ProjectFilter
{
    public ?string $q = null;

    public ?Status $status = null;

    public ?Area $area = null;

    public ?ProjectType $projectType = null;

    /**
     * Several departments may be chosen; a project matches when it is anchored
     * in any of them. A Collection rather than an array because a multiple
     * EntityType writes an ArrayCollection back into the model.
     *
     * @var Collection<int, Department>
     */
    public Collection $organizationalAnchoring;

    public ?bool $endorsement = null;

    public string $sort = 'createdAt';

    public string $direction = 'DESC';

    public function __construct()
    {
        $this->organizationalAnchoring = new ArrayCollection();
    }
}
