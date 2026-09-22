<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * New cases' backing values must stay within the column length mapped on
 * {@see \App\Entity\Project}, or they will be truncated when persisted.
 */
enum ProjectType: string implements TranslatableEnum
{
    case Project = 'project';
    case Programme = 'programme';
    case Policy = 'policy';
    case Pilot = 'pilot';
    case Operation = 'operation';

    public function labelKey(): string
    {
        return 'enum.project_type.'.$this->value;
    }
}
