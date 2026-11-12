<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Enum\Status;
use App\Tests\FunctionalTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class DashboardControllerTest extends FunctionalTestCase
{
    public function testTheTotalAndInProgressBoxesLinkToTheProjectList(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        self::assertCount(4, $crawler->filter('#dashboard-stats .kpi'));

        // Exactly these two boxes are links, keyed by the value cell they wrap;
        // the in-progress one filters on the same status the count is built from.
        $links = [];
        foreach ($crawler->filter('#dashboard-stats a.kpi') as $node) {
            $link = new Crawler($node);
            $links[$link->filter('[data-live-text]')->attr('data-live-text')] = $link->attr('href');
        }
        self::assertSame([
            'kpi-total' => '/projects',
            'kpi-in-progress' => '/projects?status='.Status::Granted->value,
        ], $links);
    }

    public function testFollowingTheInProgressLinkPreselectsTheStatusFilter(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/');

        $inProgress = $crawler->filter('#dashboard-stats a.kpi')->reduce(
            static fn (Crawler $box): bool => $box->filter('[data-live-text="kpi-in-progress"]')->count() > 0,
        );
        $crawler = $this->client->click($inProgress->link());

        $this->assertResponseIsSuccessful();
        self::assertSame(
            Status::Granted->value,
            $crawler->filter('select[name="status"] option[selected]')->attr('value'),
        );
    }
}
