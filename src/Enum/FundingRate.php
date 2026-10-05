<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The share of the project's budget that the funding source covers. The
 * backing value is the percentage, so it doubles as the label's number.
 */
enum FundingRate: string implements TranslatableEnum
{
    case Quarter = '25';
    case Half = '50';
    case ThreeQuarters = '75';
    case Full = '100';

    public function labelKey(): string
    {
        return 'enum.funding_rate.'.$this->value;
    }
}
