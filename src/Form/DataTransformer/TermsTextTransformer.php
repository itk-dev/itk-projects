<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\Entity\Term;
use App\Enum\Vocabulary;
use App\Repository\TermRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\DataTransformerInterface;

/**
 * Bridges a comma-separated text input and a collection of {@see Term}s in a
 * free-tagging vocabulary, creating missing terms on the fly — the Symfony
 * equivalent of the original "separate multiple values with commas" tagging.
 *
 * Note: new terms are persisted as a side effect of reverseTransform(), and a
 * term later removed from every project is not garbage-collected — the row
 * lingers in the vocabulary.
 *
 * @implements DataTransformerInterface<mixed, mixed>
 */
final readonly class TermsTextTransformer implements DataTransformerInterface
{
    public function __construct(
        private TermRepository $termRepository,
        private Vocabulary $vocabulary,
    ) {
    }

    public function transform(mixed $value): string
    {
        if (!is_iterable($value)) {
            return '';
        }

        $names = [];
        foreach ($value as $term) {
            if ($term instanceof Term) {
                $names[] = $term->getName();
            }
        }

        return implode(', ', $names);
    }

    /**
     * @return Collection<int, Term>
     */
    public function reverseTransform(mixed $value): Collection
    {
        $terms = new ArrayCollection();

        if (!\is_string($value) || '' === trim($value)) {
            return $terms;
        }

        $seen = [];
        foreach (explode(',', $value) as $name) {
            $name = trim($name);
            $key = mb_strtolower($name);
            if ('' === $name || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $terms->add($this->termRepository->findOrCreate($name, $this->vocabulary));
        }

        return $terms;
    }
}
