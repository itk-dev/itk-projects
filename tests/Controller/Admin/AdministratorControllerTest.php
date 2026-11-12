<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Tests\FunctionalTestCase;

final class AdministratorControllerTest extends FunctionalTestCase
{
    public function testEveryUserCanSeeWhoTheAdministratorsAre(): void
    {
        $this->loginAsEditor();
        $crawler = $this->client->request('GET', '/admin/administrators');

        $this->assertResponseIsSuccessful();
        // The admin fixture is listed with a mail link; the editor is not an admin.
        self::assertCount(1, $crawler->filter('a[href="mailto:admin@example.com"]'));
        self::assertStringContainsString('Administrator', $crawler->filter('td.cell-title')->text());
        self::assertCount(0, $crawler->filter('a[href="mailto:editor@example.com"]'));
    }

    public function testTheSidebarOffersEditorsTheAdministratorsInsteadOfTheVocabularies(): void
    {
        $this->loginAsEditor();
        $crawler = $this->client->request('GET', '/admin/administrators');

        self::assertCount(1, $crawler->filter('.admin-sidebar a[href="/admin/administrators"]'));
        foreach (['/admin/users', '/admin/departments', '/admin/areas', '/admin/project-types'] as $adminOnly) {
            self::assertCount(0, $crawler->filter(sprintf('.admin-sidebar a[href="%s"]', $adminOnly)), $adminOnly);
        }

        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/admin/administrators');
        foreach (['/admin/users', '/admin/departments', '/admin/areas', '/admin/project-types', '/admin/administrators'] as $url) {
            self::assertCount(1, $crawler->filter(sprintf('.admin-sidebar a[href="%s"]', $url)), $url);
        }
    }
}
