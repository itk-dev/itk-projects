<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * New cases' backing values must stay within the column length mapped on
 * {@see \App\Entity\Project}, or they will be truncated when persisted.
 */
enum EndorsementAuthor: string implements TranslatableEnum
{
    case CityCouncil = 'city_council';
    case Magistrate = 'magistrate';
    case Committee = 'committee';
    case Department = 'department';

    public function labelKey(): string
    {
        return 'enum.endorsement_author.'.$this->value;
    }
}
