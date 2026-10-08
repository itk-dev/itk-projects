<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\FunctionalTestCase;

final class TidyFeedbackTest extends FunctionalTestCase
{
    public function testTheFeedbackWidgetIsInjectedUnderTheIdsTheTurboHookReliesOn(): void
    {
        // The bundle injects the widget into every page, the login page included.
        // assets/app.js marks these two elements permanent on each Turbo visit, so
        // a renamed id in the bundle would silently bring the problem back.
        $crawler = $this->client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#tidy-feedback'));
        self::assertCount(1, $crawler->filter('#tidy-feedback-region'));
    }
}
