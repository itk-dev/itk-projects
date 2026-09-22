<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SmokeTest extends WebTestCase
{
    public function testDashboardRequiresLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        $this->assertResponseRedirects();
    }

    #[DataProvider('authenticatedPages')]
    public function testAuthenticatedPagesRender(string $url): void
    {
        $client = static::createClient();
        $admin = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'admin@example.com']);
        self::assertNotNull($admin, 'Load fixtures into the test database first.');

        $client->loginUser($admin);
        $client->request('GET', $url);

        $this->assertResponseIsSuccessful(sprintf('GET %s should succeed', $url));
    }

    public function testProjectShowAndEditRender(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $admin = $container->get(UserRepository::class)->findOneBy(['email' => 'admin@example.com']);
        self::assertNotNull($admin);
        $client->loginUser($admin);

        $project = $container->get(ProjectRepository::class)->findOneBy([]);
        self::assertNotNull($project, 'Fixtures should create at least one project.');

        $client->request('GET', sprintf('/projects/%s', $project->getId()));
        $this->assertResponseIsSuccessful();

        $client->request('GET', sprintf('/projects/%s/edit', $project->getId()));
        $this->assertResponseIsSuccessful();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function authenticatedPages(): iterable
    {
        yield 'dashboard' => ['/'];
        yield 'projects' => ['/projects'];
        yield 'projects filtered' => ['/projects?status=active&endorsement=1&sort=title&direction=ASC'];
        yield 'projects freetext search' => ['/projects?q=teknik'];
        yield 'project new' => ['/projects/new'];
        yield 'csv export' => ['/projects/export'];
        yield 'admin users' => ['/admin/users'];
        yield 'admin user new' => ['/admin/users/new'];
        yield 'admin contacts' => ['/admin/contacts'];
        yield 'admin contact new' => ['/admin/contacts/new'];
        yield 'admin partners' => ['/admin/partners'];
        yield 'admin partner new' => ['/admin/partners/new'];
        yield 'admin departments' => ['/admin/departments'];
        yield 'admin department new' => ['/admin/departments/new'];
    }
}
