<?php

declare(strict_types=1);

namespace App\Tests\Repository;

use App\Enum\Vocabulary;
use App\Repository\TermRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TermRepositoryTest extends KernelTestCase
{
    private TermRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $repository = static::getContainer()->get(TermRepository::class);
        \assert($repository instanceof TermRepository);
        $this->repository = $repository;
    }

    public function testFindByVocabularyReturnsOnlyMatchingTerms(): void
    {
        $terms = $this->repository->findByVocabulary(Vocabulary::Tag);

        self::assertNotEmpty($terms);
        foreach ($terms as $term) {
            self::assertSame(Vocabulary::Tag, $term->getVocabulary());
        }
    }

    public function testFindOrCreateReturnsAnExistingTermCaseInsensitively(): void
    {
        // The fixtures create a "Klima" tag.
        $term = $this->repository->findOrCreate('klima', Vocabulary::Tag);

        self::assertSame('Klima', $term->getName());
    }

    public function testFindOrCreateBuildsANewUnflushedTerm(): void
    {
        $name = 'BrandNewTag-'.uniqid();

        $term = $this->repository->findOrCreate($name, Vocabulary::Tag);

        self::assertSame($name, $term->getName());
        self::assertSame(Vocabulary::Tag, $term->getVocabulary());
        self::assertCount(0, $this->repository->findBy(['name' => $name]), 'A freshly created term is not yet flushed to the database.');
    }

    public function testFindOrCreateCapitalisesANewTerm(): void
    {
        $term = $this->repository->findOrCreate('grøn omstilling '.uniqid(), Vocabulary::Tag);

        self::assertSame('G', mb_substr((string) $term->getName(), 0, 1));
    }
}
