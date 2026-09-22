<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * The free-tagging vocabularies. Unlike the controlled enums, terms in these
 * vocabularies are user-managed data (Term entity), creatable on the fly.
 */
enum Vocabulary: string implements TranslatableEnum
{
    case Tag = 'tag';
    case Strategy = 'strategy';

    public function labelKey(): string
    {
        return 'enum.vocabulary.'.$this->value;
    }
}
