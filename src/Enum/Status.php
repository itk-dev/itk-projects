<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * New cases' backing values must stay within the column length mapped on
 * {@see \App\Entity\Project}, or they will be truncated when persisted.
 *
 * Cases are listed in the order a project typically moves through them;
 * forms, filters and dashboard charts present them in this order.
 */
enum Status: string implements TranslatableEnum
{
    case Idea = 'idea';
    case Opportunity = 'opportunity';
    case ApplicationInProgress = 'application_in_progress';
    case ApplicationSubmitted = 'application_submitted';
    case Granted = 'granted';
    case OnHold = 'on_hold';
    case Rejected = 'rejected';
    case Completed = 'completed';

    public function labelKey(): string
    {
        return 'enum.status.'.$this->value;
    }
}
