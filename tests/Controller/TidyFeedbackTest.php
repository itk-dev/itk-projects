<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Tests\FunctionalTestCase;

final class TidyFeedbackTest extends FunctionalTestCase
{
    public function testTheWidgetIsInjectedUnderTheIdsTheTurboHookReliesOn(): void
    {
        // assets/app.js marks these two elements permanent on each Turbo visit, so
        // a renamed id in the bundle would silently bring the problem back.
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('#tidy-feedback'));
        self::assertCount(1, $crawler->filter('#tidy-feedback-region'));
    }

    public function testTheMercureStreamSourceIsKeptOutOfTheScreenshot(): void
    {
        // The widget screenshots the page with snapdom, which clones the DOM; a
        // cloned <turbo-mercure-stream-source> connects without its src and
        // throws, so the dashboard keeps it inside an excluded wrapper.
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/');

        self::assertCount(1, $crawler->filter('[data-capture="exclude"] turbo-mercure-stream-source[src]'));
    }

    public function testTheLoginPageCarriesNoWidget(): void
    {
        // TIDY_FEEDBACK_DISABLE_PATTERN keeps the widget off /login: its check
        // endpoint and feedback form need a signed-in user anyway, and a redirected
        // request from it would hijack the post-login redirect.
        $crawler = $this->client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        self::assertCount(0, $crawler->filter('#tidy-feedback'));
        // The widget only initialises on a full page load, so the first page after
        // login must be one.
        self::assertSame('false', $crawler->filter('form')->attr('data-turbo'));
    }

    public function testTheWidgetAssetsNeedNoSignIn(): void
    {
        $this->client->request('GET', '/tidy-feedback/asset/widget.css');

        $this->assertResponseIsSuccessful();
        self::assertStringStartsWith('text/css', (string) $this->client->getResponse()->headers->get('Content-Type'));
    }

    public function testAnAssetRequestBeforeLoginDoesNotHijackTheRedirect(): void
    {
        // Before the asset path was public, this request was answered with a
        // redirect that stored itself as the target path, so the login below
        // ended on the stylesheet instead of the dashboard.
        $this->client->request('GET', '/tidy-feedback/asset/widget.css');

        $crawler = $this->client->request('GET', '/login');
        $this->client->request('POST', '/login', [
            '_username' => 'admin@example.com',
            '_password' => 'password',
            '_csrf_token' => (string) $crawler->filter('input[name="_csrf_token"]')->attr('value'),
        ]);

        $this->assertResponseRedirects('/');
    }
}
