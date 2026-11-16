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

    public function testTheChartsAreKeyedOnStatusAndKnowTheListUrl(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        // The chart controller turns a clicked status into a visit to the project
        // list filtered on it, and reads the list URL from its controller element.
        self::assertSame(
            '/projects',
            $crawler->filter('[data-controller~="dashboard-viz"]')->attr('data-dashboard-viz-list-url-value'),
        );

        $viz = json_decode($crawler->filter('#dashboard-viz-data')->text(), true, 512, \JSON_THROW_ON_ERROR);
        \assert(\is_array($viz));
        // Both charts read their labels from the statuses list, in enum order, and
        // index into the distribution and budget cells with the same position.
        self::assertSame(
            array_map(static fn (Status $status): string => $status->value, Status::cases()),
            array_column($viz['statuses'], 'key'),
        );
        self::assertCount(\count(Status::cases()), $viz['statusDistribution']);
        self::assertCount(\count(Status::cases()), $viz['budgetByStatus']);
        self::assertArrayNotHasKey('budgetByDept', $viz);
    }
}
