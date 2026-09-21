<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Model\ProjectFilter;
use App\Repository\ProjectRepository;
use App\Service\Paginator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PaginatorTest extends KernelTestCase
{
    private Paginator $paginator;
    private ProjectRepository $projects;

    protected function setUp(): void
    {
        self::bootKernel();

        $paginator = static::getContainer()->get(Paginator::class);
        \assert($paginator instanceof Paginator);
        $this->paginator = $paginator;

        $projects = static::getContainer()->get(ProjectRepository::class);
        \assert($projects instanceof ProjectRepository);
        $this->projects = $projects;
    }

    public function testClampsPageBeyondTheLastPage(): void
    {
        $result = $this->paginator->paginate($this->projects->search(new ProjectFilter()), 999, 5);

        self::assertGreaterThan(0, $result->total);
        self::assertSame($result->pages, $result->page, 'A page beyond the range is clamped to the last page.');
        self::assertLessThanOrEqual(5, \count($result->items));
        self::assertNotEmpty($result->items, 'The clamped last page should still return rows.');
    }

    public function testClampsPageBelowOne(): void
    {
        $result = $this->paginator->paginate($this->projects->search(new ProjectFilter()), 0, 5);

        self::assertSame(1, $result->page);
        self::assertSame(5, $result->perPage);
    }
}
