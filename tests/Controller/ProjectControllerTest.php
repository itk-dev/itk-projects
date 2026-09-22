<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Project;
use App\Enum\Status;
use App\Tests\FunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;

final class ProjectControllerTest extends FunctionalTestCase
{
    public function testIndexAppliesFiltersFromTheQueryString(): void
    {
        $this->loginAsAdmin();
        $match = $this->createProject('Deeplinked project', Status::Active);
        $other = $this->createProject('Deeplinked other project', Status::Cancelled);

        // Opening a shared link must narrow the list, not just fill the form.
        $crawler = $this->client->request('GET', '/projects?q=Deeplinked&status=active&sort=title&direction=ASC');

        $this->assertResponseIsSuccessful();
        self::assertSame('active', $crawler->filter('#project-filters select[name="status"] option[selected]')->attr('value'));
        self::assertSame('Deeplinked', $crawler->filter('#project-filters input[name="q"]')->attr('value'));

        $titles = $crawler->filter('#project-results .cell-title')->each(static fn ($node): string => $node->text());
        self::assertContains('Deeplinked project', $titles);
        self::assertNotContains('Deeplinked other project', $titles);

        $this->removeProject((string) $match->getId());
        $this->removeProject((string) $other->getId());
    }

    public function testIndexSearchFieldDoesNotSubmitOnEnter(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/projects');

        $this->assertResponseIsSuccessful();

        // Enter clicks the form's default button, so an owned submit button
        // anywhere would turn it into a CSV download.
        self::assertCount(0, $crawler->filter('#project-filters button[type="submit"], #project-filters input[type="submit"]'));
        self::assertCount(0, $crawler->filter('button[form="project-filters"], input[form="project-filters"]'));

        $search = $crawler->filter('#project-filters input[name="q"]');
        self::assertStringContainsString('keydown.enter->live-search#ignoreEnter', (string) $search->attr('data-action'));
        // Merged onto the field's attributes, not swapped in.
        self::assertNotEmpty($search->attr('placeholder'));
    }

    public function testNewPersistsProjectWithInlineContactAndPartner(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/projects/new');
        $this->assertResponseIsSuccessful();

        $token = (string) $crawler->filter('input[name="project[_token]"]')->attr('value');
        $this->client->request('POST', '/projects/new', [
            'project' => [
                'title' => 'Coverage project',
                // A typed name creates a new contact on the fly and attaches it.
                'contacts' => 'Coverage Contact',
                // Same free-tagging behaviour for partners.
                'partners' => 'Coverage Partner',
                '_token' => $token,
            ],
        ]);

        $this->assertResponseRedirects();

        $em = $this->entityManager();
        $project = $this->projects()->findOneBy(['title' => 'Coverage project']);
        self::assertInstanceOf(Project::class, $project);
        self::assertGreaterThanOrEqual(1, $project->getContacts()->count(), 'Inline contact should be merged in.');
        self::assertGreaterThanOrEqual(1, $project->getPartners()->count(), 'Inline partner should be merged in.');

        $em->remove($project);
        $em->flush();

        foreach ($this->contacts()->findBy(['name' => 'Coverage Contact']) as $contact) {
            $em->remove($contact);
        }
        foreach ($this->partners()->findBy(['name' => 'Coverage Partner']) as $partner) {
            $em->remove($partner);
        }
        $em->flush();
    }

    public function testTopicIsSavedShownSearchableAndExported(): void
    {
        $this->loginAsAdmin();
        $topic = 'Digital Europe Blueprint '.uniqid();

        $crawler = $this->client->request('GET', '/projects/new');
        $token = (string) $crawler->filter('input[name="project[_token]"]')->attr('value');
        $this->client->request('POST', '/projects/new', [
            'project' => ['title' => 'Topic project', 'topic' => $topic, '_token' => $token],
        ]);
        $this->assertResponseRedirects();

        $project = $this->projects()->findOneBy(['title' => 'Topic project']);
        self::assertInstanceOf(Project::class, $project);
        self::assertSame($topic, $project->getTopic());
        $id = (string) $project->getId();

        $crawler = $this->client->request('GET', '/projects/'.$id);
        self::assertStringContainsString($topic, $crawler->filter('.card__body')->first()->text());

        // The free-text filter searches the topic alongside title and description.
        $crawler = $this->client->request('GET', '/projects?q='.urlencode($topic));
        self::assertStringContainsString('Topic project', $crawler->filter('#project-results')->text());

        $this->client->request('GET', '/projects/export?q='.urlencode($topic));
        $csv = (string) $this->client->getInternalResponse()->getContent();
        self::assertStringContainsString($topic, $csv);

        $this->removeProject($id);
    }

    public function testEditUpdatesProject(): void
    {
        $this->loginAsAdmin();
        $project = $this->createProject('Editable project');
        $id = (string) $project->getId();

        $crawler = $this->client->request('GET', sprintf('/projects/%s/edit', $id));
        $this->assertResponseIsSuccessful();

        $token = (string) $crawler->filter('input[name="project[_token]"]')->attr('value');
        $this->client->request('POST', sprintf('/projects/%s/edit', $id), [
            'project' => [
                'title' => 'Edited project',
                '_token' => $token,
            ],
        ]);

        $this->assertResponseRedirects(sprintf('/projects/%s', $id));

        $this->removeProject($id);
    }

    public function testDeleteRemovesProjectWithAValidToken(): void
    {
        $this->loginAsAdmin();
        $project = $this->createProject('Deletable project');
        $id = (string) $project->getId();

        $crawler = $this->client->request('GET', sprintf('/projects/%s/edit', $id));
        $form = $crawler->filter('form[action$="/delete"]')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects('/projects');

        $this->entityManager()->clear();
        self::assertNull($this->projects()->find($id));
    }

    public function testDeleteIgnoresAnInvalidToken(): void
    {
        $this->loginAsAdmin();
        $project = $this->createProject('Survivor project');
        $id = (string) $project->getId();

        $this->client->request('POST', sprintf('/projects/%s/delete', $id), ['_token' => 'invalid']);

        $this->assertResponseRedirects('/projects');
        $this->entityManager()->clear();
        self::assertNotNull($this->projects()->find($id));

        $this->removeProject($id);
    }

    public function testNewAutosaveReturnsCreatedWithLocationHeader(): void
    {
        $this->loginAsAdmin();

        // Autosave posts via fetch with the X-Autosave header and no CSRF token.
        $this->client->request('POST', '/projects/new', [
            'project' => ['title' => 'Autosaved project'],
        ], [], ['HTTP_X-Autosave' => '1']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertTrue($this->client->getResponse()->headers->has('X-Project-Location'));

        $project = $this->projects()->findOneBy(['title' => 'Autosaved project']);
        self::assertInstanceOf(Project::class, $project);

        $this->removeProject((string) $project->getId());
    }

    public function testNewAutosaveReturnsUnprocessableWhenInvalid(): void
    {
        $this->loginAsAdmin();

        // An empty title fails NotBlank, so autosave reports it without creating anything.
        $this->client->request('POST', '/projects/new', [
            'project' => ['title' => ''],
        ], [], ['HTTP_X-Autosave' => '1']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertNull($this->projects()->findOneBy(['title' => '']));
    }

    public function testEditAutosaveReturnsNoContent(): void
    {
        $this->loginAsAdmin();
        $project = $this->createProject('Autosave edit');
        $id = (string) $project->getId();

        $this->client->request('POST', sprintf('/projects/%s/edit', $id), [
            'project' => ['title' => 'Autosave edited'],
        ], [], ['HTTP_X-Autosave' => '1']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->removeProject($id);
    }

    public function testEditAutosaveReturnsUnprocessableWhenInvalid(): void
    {
        $this->loginAsAdmin();
        $project = $this->createProject('Autosave edit invalid');
        $id = (string) $project->getId();

        $this->client->request('POST', sprintf('/projects/%s/edit', $id), [
            'project' => ['title' => ''],
        ], [], ['HTTP_X-Autosave' => '1']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->removeProject($id);
    }

    private function createProject(string $title, ?Status $status = null): Project
    {
        $project = (new Project())->setTitle($title)->setStatus($status);
        $em = $this->entityManager();
        $em->persist($project);
        $em->flush();

        return $project;
    }

    private function removeProject(string $id): void
    {
        $this->entityManager()->clear();
        $project = $this->projects()->find($id);
        if (null !== $project) {
            $em = $this->entityManager();
            $em->remove($project);
            $em->flush();
        }
    }
}
