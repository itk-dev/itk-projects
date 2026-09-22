<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Tests\FunctionalTestCase;

final class UserSettingsControllerTest extends FunctionalTestCase
{
    protected function tearDown(): void
    {
        try {
            // Reset the persisted preference at the SQL level so we don't leak it
            // between tests. An ORM flush here would trigger the blameable listener
            // against the logged-in user left over from the client's request cycle,
            // which is detached from this entity manager once the kernel reboots.
            $this->entityManager()->getConnection()->executeStatement(
                'UPDATE `user` SET user_settings = :empty WHERE email = :email',
                ['empty' => '[]', 'email' => 'editor@example.com'],
            );
        } finally {
            parent::tearDown();
        }
    }

    public function testAjaxToggleReturnsNoContentAndFlipsThePreference(): void
    {
        $this->loginAsEditor();
        $token = $this->mascotToggleToken();

        $this->client->request('POST', '/settings/mascot/toggle', ['_token' => $token], [], ['HTTP_X-Requested-With' => 'fetch']);
        $this->assertResponseStatusCodeSame(204);
        self::assertFalse($this->reloadEditor()->isMascotEnabled());

        $this->client->request('POST', '/settings/mascot/toggle', ['_token' => $token], [], ['HTTP_X-Requested-With' => 'fetch']);
        $this->assertResponseStatusCodeSame(204);
        self::assertTrue($this->reloadEditor()->isMascotEnabled());
    }

    public function testPlainFormToggleRedirectsToReturn(): void
    {
        $this->loginAsEditor();
        $token = $this->mascotToggleToken();

        $this->client->request('POST', '/settings/mascot/toggle', ['_token' => $token, 'return' => '/projects']);

        $this->assertResponseRedirects('/projects');
        self::assertFalse($this->reloadEditor()->isMascotEnabled());
    }

    public function testInvalidTokenIsIgnoredAndRedirectsToDashboard(): void
    {
        $this->loginAsEditor();

        $this->client->request('POST', '/settings/mascot/toggle', ['_token' => 'not-a-valid-token']);

        $this->assertResponseRedirects('/');
        self::assertTrue($this->reloadEditor()->isMascotEnabled());
    }

    public function testStarsToggleRedirectsAndFlipsThePreference(): void
    {
        $this->loginAsEditor();
        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
        $token = (string) $crawler->filter('#starsToggleForm input[name="_token"]')->attr('value');

        $this->client->request('POST', '/settings/stars/toggle', ['_token' => $token, 'return' => '/projects']);

        $this->assertResponseRedirects('/projects');
        self::assertFalse($this->reloadEditor()->isStarsEnabled());
    }

    public function testTourSeenAjaxReturnsNoContentAndPersists(): void
    {
        $this->loginAsEditor();
        $token = $this->tourSeenToken();

        $this->client->request('POST', '/settings/tour/seen', ['_token' => $token], [], ['HTTP_X-Requested-With' => 'fetch']);
        $this->assertResponseStatusCodeSame(204);
        self::assertTrue($this->reloadEditor()->isTourSeen());
    }

    public function testTourSeenPlainFormRedirectsToReturn(): void
    {
        $this->loginAsEditor();
        $token = $this->tourSeenToken();

        $this->client->request('POST', '/settings/tour/seen', ['_token' => $token, 'return' => '/projects']);

        $this->assertResponseRedirects('/projects');
        self::assertTrue($this->reloadEditor()->isTourSeen());
    }

    private function mascotToggleToken(): string
    {
        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        return (string) $crawler->filter('#mascotToggleForm input[name="_token"]')->attr('value');
    }

    private function tourSeenToken(): string
    {
        $crawler = $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();

        return (string) $crawler->filter('.mascot')->attr('data-mascot-tour-seen-token-value');
    }

    private function reloadEditor(): User
    {
        $this->entityManager()->clear();
        $user = $this->users()->findOneBy(['email' => 'editor@example.com']);
        self::assertInstanceOf(User::class, $user);

        return $user;
    }
}
