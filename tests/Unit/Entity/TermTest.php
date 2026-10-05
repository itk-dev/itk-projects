<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Term;
use App\Enum\Vocabulary;
use PHPUnit\Framework\TestCase;

final class TermTest extends TestCase
{
    public function testDefaultVocabularyIsTag(): void
    {
        $term = new Term();

        self::assertNull($term->getName());
        self::assertSame(Vocabulary::Tag, $term->getVocabulary());
        // Timestamps are populated by the bundle's listener on flush, so they
        // are still null on a freshly constructed (unpersisted) entity.
        self::assertNull($term->getCreatedAt());
        self::assertSame('', (string) $term);
    }

    public function testAccessors(): void
    {
        $term = (new Term(Vocabulary::Tag))->setName('Klima');

        self::assertSame('Klima', $term->getName());
        self::assertSame(Vocabulary::Tag, $term->getVocabulary());
        self::assertSame('Klima', (string) $term);

        $term->setVocabulary(Vocabulary::Tag);
        self::assertSame(Vocabulary::Tag, $term->getVocabulary());
    }
}
