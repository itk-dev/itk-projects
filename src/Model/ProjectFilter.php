<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Area;
use App\Entity\Department;
use App\Enum\ProjectType;
use App\Enum\Status;

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

    public ?Department $organizationalAnchoring = null;

    public ?bool $endorsement = null;

    public string $sort = 'createdAt';

    public string $direction = 'DESC';
}
