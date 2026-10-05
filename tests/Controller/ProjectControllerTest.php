<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Contact;
use App\Entity\Department;
use App\Entity\Project;
use App\Entity\ProjectType;
use App\Enum\Status;
use App\Tests\FunctionalTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Ulid;

final class ProjectControllerTest extends FunctionalTestCase
{
    public function testIndexAppliesFiltersFromTheQueryString(): void
    {
        $this->loginAsAdmin();
        $match = $this->createProject('Deeplinked project', Status::Granted);
        $other = $this->createProject('Deeplinked other project', Status::Rejected);

        // Opening a shared link must narrow the list, not just fill the form.
        $crawler = $this->client->request('GET', '/projects?q=Deeplinked&status=granted&sort=title&direction=ASC');

        $this->assertResponseIsSuccessful();
        self::assertSame('granted', $crawler->filter('#project-filters select[name="status"] option[selected]')->attr('value'));
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
                // A typed name creates a new partner on the fly and attaches it.
                'partners' => 'Coverage Partner',
                '_token' => $token,
            ],
        ]);

        $this->assertResponseRedirects();

        $em = $this->entityManager();
        $project = $this->projects()->findOneBy(['title' => 'Coverage project']);
        self::assertInstanceOf(Project::class, $project);
        self::assertGreaterThanOrEqual(1, $project->getPartners()->count(), 'Inline partner should be merged in.');

        $em->remove($project);
        $em->flush();

        foreach ($this->partners()->findBy(['name' => 'Coverage Partner']) as $partner) {
            $em->remove($partner);
        }
        $em->flush();
    }

    public function testContactPickerTellsNamesakesApartAndAttachesById(): void
    {
        $this->loginAsAdmin();
        $em = $this->entityManager();
        $name = 'Namesake '.uniqid();
        $first = (new Contact())->setName($name)->setEmail('first@example.com');
        $second = (new Contact())->setName($name)->setEmail('second@example.com');
        $em->persist($first);
        $em->persist($second);
        $em->flush();
        $firstId = (string) $first->getId();
        $secondId = (string) $second->getId();

        $crawler = $this->client->request('GET', '/projects/new');
        $this->assertResponseIsSuccessful();

        $input = $crawler->filter('[name="project[contacts]"]');
        $pool = json_decode((string) $input->attr('data-contact-pool'), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($pool);
        $labels = array_column($pool, 'label', 'id');
        // Two people sharing a name are two entries, told apart by their email.
        self::assertSame($name.' (first@example.com)', $labels[$firstId] ?? null);
        self::assertSame($name.' (second@example.com)', $labels[$secondId] ?? null);
        self::assertSame('/contacts', $input->attr('data-contact-create-url'));

        // Submitting an id attaches that very person, not the namesake.
        $title = 'Namesake project '.uniqid();
        $token = (string) $crawler->filter('input[name="project[_token]"]')->attr('value');
        $this->client->request('POST', '/projects/new', [
            'project' => ['title' => $title, 'contacts' => $secondId, '_token' => $token],
        ]);
        $this->assertResponseRedirects();

        $project = $this->projects()->findOneBy(['title' => $title]);
        self::assertInstanceOf(Project::class, $project);
        self::assertSame(
            [$secondId],
            array_map(static fn (Contact $contact): string => (string) $contact->getId(), $project->getContacts()->toArray()),
        );

        $em = $this->entityManager();
        $em->remove($project);
        foreach ([$firstId, $secondId] as $id) {
            $contact = $this->contacts()->find($id);
            self::assertInstanceOf(Contact::class, $contact);
            $em->remove($contact);
        }
        $em->flush();
    }

    public function testAnUnknownContactIdIsRejected(): void
    {
        $this->loginAsAdmin();
        $title = 'Unknown contact project '.uniqid();

        // Names are no longer accepted either: a typed name is created through
        // the contact endpoint, so the picker only ever posts ids.
        foreach ([(string) new Ulid(), 'Anne Jensen'] as $value) {
            $this->client->request('POST', '/projects/new', [
                'project' => ['title' => $title, 'contacts' => $value],
            ], [], ['HTTP_X_AUTOSAVE' => '1']);

            $this->assertResponseStatusCodeSame(422);
        }
        self::assertNull($this->projects()->findOneBy(['title' => $title]));
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

    public function testDepartmentsAreSavedShownFilteredAndExported(): void
    {
        $this->loginAsAdmin();
        $em = $this->entityManager();
        $first = (new Department())->setName('Dept first '.uniqid());
        $second = (new Department())->setName('Dept second '.uniqid());
        $em->persist($first);
        $em->persist($second);
        $em->flush();
        $unanchored = $this->createProject('Unanchored project '.uniqid());
        $title = 'Anchored project '.uniqid();

        $crawler = $this->client->request('GET', '/projects/new');
        self::assertCount(1, $crawler->filter('select[name="project[organizationalAnchoring][]"][multiple]'));
        $token = (string) $crawler->filter('input[name="project[_token]"]')->attr('value');
        $this->client->request('POST', '/projects/new', [
            'project' => [
                'title' => $title,
                'organizationalAnchoring' => [(string) $first->getId(), (string) $second->getId()],
                '_token' => $token,
            ],
        ]);
        $this->assertResponseRedirects();

        $project = $this->projects()->findOneBy(['title' => $title]);
        self::assertInstanceOf(Project::class, $project);
        self::assertCount(2, $project->getOrganizationalAnchoring());
        $id = (string) $project->getId();

        // Both departments are listed on the project page …
        $crawler = $this->client->request('GET', '/projects/'.$id);
        $details = $crawler->filter('.detail-list')->text();
        self::assertStringContainsString((string) $first->getName(), $details);
        self::assertStringContainsString((string) $second->getName(), $details);

        // … the department filter narrows the list to projects anchored there …
        $crawler = $this->client->request('GET', '/projects?organizationalAnchoring='.$first->getId());
        $results = $crawler->filter('#project-results')->text();
        self::assertStringContainsString($title, $results);
        self::assertStringContainsString($first->getName().', '.$second->getName(), $results);
        self::assertStringNotContainsString((string) $unanchored->getTitle(), $results);

        // … and the export lists every department of the project.
        $this->client->request('GET', '/projects/export?organizationalAnchoring='.$first->getId());
        $csv = (string) $this->client->getInternalResponse()->getContent();
        self::assertStringContainsString($first->getName().', '.$second->getName(), $csv);

        $this->removeProject($id);
        $this->removeProject((string) $unanchored->getId());
        $em = $this->entityManager();
        foreach ([$first->getId(), $second->getId()] as $departmentId) {
            $department = $this->departments()->find($departmentId);
            if (null !== $department) {
                $em->remove($department);
            }
        }
        $em->flush();
    }

    public function testTypesAreSavedShownFilteredAndExported(): void
    {
        $this->loginAsAdmin();
        $em = $this->entityManager();
        $first = (new ProjectType())->setName('Type first '.uniqid());
        $second = (new ProjectType())->setName('Type second '.uniqid());
        $em->persist($first);
        $em->persist($second);
        $em->flush();
        $plain = $this->createProject('Plain project '.uniqid());
        $title = 'Typed project '.uniqid();

        $crawler = $this->client->request('GET', '/projects/new');
        self::assertCount(1, $crawler->filter('select[name="project[types][]"][multiple][data-type-select]'));
        $token = (string) $crawler->filter('input[name="project[_token]"]')->attr('value');
        $this->client->request('POST', '/projects/new', [
            'project' => [
                'title' => $title,
                'types' => [(string) $first->getId(), (string) $second->getId()],
                '_token' => $token,
            ],
        ]);
        $this->assertResponseRedirects();

        $project = $this->projects()->findOneBy(['title' => $title]);
        self::assertInstanceOf(Project::class, $project);
        self::assertCount(2, $project->getTypes());
        $id = (string) $project->getId();

        // Both types are listed on the project page …
        $crawler = $this->client->request('GET', '/projects/'.$id);
        $details = $crawler->filter('.detail-list')->text();
        self::assertStringContainsString((string) $first->getName(), $details);
        self::assertStringContainsString((string) $second->getName(), $details);

        // … the type filter narrows the list to projects of that type …
        $crawler = $this->client->request('GET', '/projects?type='.$first->getId());
        $results = $crawler->filter('#project-results')->text();
        self::assertStringContainsString($title, $results);
        self::assertStringContainsString($first->getName().', '.$second->getName(), $results);
        self::assertStringNotContainsString((string) $plain->getTitle(), $results);

        // … and the export lists every type of the project.
        $this->client->request('GET', '/projects/export?type='.$first->getId());
        $csv = (string) $this->client->getInternalResponse()->getContent();
        self::assertStringContainsString($first->getName().', '.$second->getName(), $csv);

        $this->removeProject($id);
        $this->removeProject((string) $plain->getId());
        $em = $this->entityManager();
        foreach ([$first->getId(), $second->getId()] as $typeId) {
            $type = $this->projectTypes()->find($typeId);
            if (null !== $type) {
                $em->remove($type);
            }
        }
        $em->flush();
    }

    public function testSummaryAndDescriptionAreSavedShownAndSearchable(): void
    {
        $this->loginAsAdmin();
        $summary = 'Opsummering '.uniqid();
        $description = 'Ophæng i klimaplanen '.uniqid();

        $crawler = $this->client->request('GET', '/projects/new');
        self::assertStringContainsString('Opsummering', $crawler->filter('label[for="project_summary"]')->text());
        self::assertStringContainsString('Beskrivelse', $crawler->filter('label[for="project_description"]')->text());

        // The fuller description sits directly under the summary.
        $names = $crawler->filter('form textarea')->each(static fn (Crawler $node): string => (string) $node->attr('name'));
        $summaryAt = array_search('project[summary]', $names, true);
        self::assertIsInt($summaryAt);
        self::assertSame('project[description]', $names[$summaryAt + 1] ?? null);

        $token = (string) $crawler->filter('input[name="project[_token]"]')->attr('value');
        $this->client->request('POST', '/projects/new', [
            'project' => [
                'title' => 'Described project',
                'summary' => $summary,
                'description' => $description,
                '_token' => $token,
            ],
        ]);
        $this->assertResponseRedirects();

        $project = $this->projects()->findOneBy(['title' => 'Described project']);
        self::assertInstanceOf(Project::class, $project);
        self::assertSame($summary, $project->getSummary());
        self::assertSame($description, $project->getDescription());
        $id = (string) $project->getId();

        $crawler = $this->client->request('GET', '/projects/'.$id);
        $details = $crawler->filter('.card__body')->first()->text();
        self::assertStringContainsString($summary, $details);
        self::assertStringContainsString($description, $details);

        // Both texts are covered by the free-text filter.
        foreach ([$summary, $description] as $needle) {
            $crawler = $this->client->request('GET', '/projects?q='.urlencode($needle));
            self::assertStringContainsString('Described project', $crawler->filter('#project-results')->text());
        }

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
