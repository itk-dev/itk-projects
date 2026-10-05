<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Contact;
use App\Entity\Department;
use App\Entity\Project;
use App\Entity\ProjectType;
use App\Enum\FundingRate;
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
        $onlySecond = (new Project())->setTitle('Second-only project '.uniqid())->addOrganizationalAnchoring($second);
        $em->persist($onlySecond);
        $em->flush();
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

        // … the department filter is a multiselect that narrows the list to
        // projects anchored in the chosen department …
        $crawler = $this->client->request('GET', '/projects?organizationalAnchoring[]='.$first->getId());
        self::assertCount(1, $crawler->filter('#project-filters select[name="organizationalAnchoring[]"][multiple][data-department-select]'));
        $results = $crawler->filter('#project-results')->text();
        self::assertStringContainsString($title, $results);
        self::assertStringContainsString($first->getName().', '.$second->getName(), $results);
        self::assertStringNotContainsString((string) $onlySecond->getTitle(), $results);
        self::assertStringNotContainsString((string) $unanchored->getTitle(), $results);

        // … or in any of several departments, which the form shows as selected …
        $crawler = $this->client->request('GET', sprintf('/projects?organizationalAnchoring[]=%s&organizationalAnchoring[]=%s', $first->getId(), $second->getId()));
        self::assertCount(2, $crawler->filter('#project-filters select[name="organizationalAnchoring[]"] option[selected]'));
        $results = $crawler->filter('#project-results')->text();
        self::assertStringContainsString($title, $results);
        self::assertStringContainsString((string) $onlySecond->getTitle(), $results);
        self::assertStringNotContainsString((string) $unanchored->getTitle(), $results);

        // … a link saved while the filter was a single select still narrows …
        $results = $this->client->request('GET', '/projects?organizationalAnchoring='.$second->getId())->filter('#project-results')->text();
        self::assertStringContainsString((string) $onlySecond->getTitle(), $results);
        self::assertStringNotContainsString((string) $unanchored->getTitle(), $results);
        $results = $this->client->request('GET', '/projects?organizationalAnchoring=')->filter('#project-results')->text();
        self::assertStringContainsString((string) $unanchored->getTitle(), $results);

        // … and the export applies the same filter and lists every department
        // of the project.
        $this->client->request('GET', '/projects/export?organizationalAnchoring[]='.$first->getId());
        $csv = (string) $this->client->getInternalResponse()->getContent();
        self::assertStringContainsString($first->getName().', '.$second->getName(), $csv);
        self::assertStringNotContainsString((string) $onlySecond->getTitle(), $csv);

        $this->removeProject($id);
        $this->removeProject((string) $unanchored->getId());
        $this->removeProject((string) $onlySecond->getId());
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

    public function testEconomyFieldsAndLinkNotesAreSavedShownSearchableAndExported(): void
    {
        $this->loginAsAdmin();
        $title = 'Economy project '.uniqid();
        $remainder = 'Egenfinansiering fra driftsbudgettet '.uniqid();

        $crawler = $this->client->request('GET', '/projects/new');
        self::assertCount(1, $crawler->filter('select[name="project[fundingRate]"]'));
        // A link row is a url plus a note.
        $prototype = (string) $crawler->filter('[data-collection]')->attr('data-prototype');
        self::assertStringContainsString('project[links][__name__][url]', $prototype);
        self::assertStringContainsString('project[links][__name__][note]', $prototype);

        $token = (string) $crawler->filter('input[name="project[_token]"]')->attr('value');
        $this->client->request('POST', '/projects/new', [
            'project' => [
                'title' => $title,
                'amountApplied' => '800000',
                'budget' => '1000000',
                'budgetItk' => '250000',
                'fundingRate' => '75',
                'coFinancing' => '1',
                'remainingFunding' => $remainder,
                'links' => [
                    ['url' => 'https://www.aarhus.dk', 'note' => 'Aarhus Kommune'],
                    ['url' => 'https://example.com/plain', 'note' => ''],
                    // A note without a url is not a link and is dropped.
                    ['url' => '', 'note' => 'Just a note'],
                ],
                '_token' => $token,
            ],
        ]);
        $this->assertResponseRedirects();

        $project = $this->projects()->findOneBy(['title' => $title]);
        self::assertInstanceOf(Project::class, $project);
        self::assertSame(800000, $project->getAmountApplied());
        self::assertSame(1000000, $project->getBudget());
        self::assertSame(250000, $project->getBudgetItk());
        self::assertSame(FundingRate::ThreeQuarters, $project->getFundingRate());
        self::assertTrue($project->isCoFinancing());
        self::assertSame($remainder, $project->getRemainingFunding());
        self::assertSame([
            ['url' => 'https://www.aarhus.dk', 'note' => 'Aarhus Kommune'],
            ['url' => 'https://example.com/plain', 'note' => null],
        ], $project->getLinks());
        $id = (string) $project->getId();

        // The project page shows the amounts and the rate, and names a link by its note.
        $crawler = $this->client->request('GET', '/projects/'.$id);
        $details = $crawler->filter('.detail-list')->text();
        self::assertStringContainsString('800.000 kr.', $details);
        self::assertStringContainsString('1.000.000 kr.', $details);
        self::assertStringContainsString('250.000 kr.', $details);
        self::assertStringContainsString('75 %', $details);
        self::assertStringContainsString($remainder, $crawler->filter('.card__body')->first()->text());
        $links = $crawler->filter('.link-list a');
        self::assertSame('Aarhus Kommune', $links->first()->text());
        self::assertSame('https://www.aarhus.dk', $links->first()->attr('href'));
        self::assertSame('https://example.com/plain', $links->last()->text());

        // The free-text filter covers the remainder text and the funding rate label.
        foreach ([$remainder, '75 %'] as $needle) {
            $crawler = $this->client->request('GET', '/projects?q='.urlencode($needle));
            self::assertStringContainsString($title, $crawler->filter('#project-results')->text());
        }

        $this->client->request('GET', '/projects/export?q='.urlencode($remainder));
        $csv = (string) $this->client->getInternalResponse()->getContent();
        // fputcsv encloses a field that contains a space, hence the quoted rate.
        self::assertStringContainsString('800000,1000000,250000,"75 %",Ja,', $csv);
        self::assertStringContainsString($remainder, $csv);

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

    public function testTheProjectDefinitionIsShownOnCreateAndBehindALinkOnTheList(): void
    {
        $this->loginAsAdmin();

        // Collapsed on the create page, above the form.
        $crawler = $this->client->request('GET', '/projects/new');
        $this->assertResponseIsSuccessful();
        $callout = $crawler->filter('details.definition-callout');
        self::assertCount(1, $callout);
        self::assertNull($callout->attr('open'));
        self::assertCount(4, $callout->filter('.definition__criteria li'));
        self::assertCount(3, $callout->filter('.definition__examples li'));
        self::assertTrue($callout->filter('.definition-callout__summary')->nextAll()->first()->matches('.definition'));

        // On the list it sits in a dialog, opened from the subtitle and from the
        // empty state.
        $crawler = $this->client->request('GET', '/projects?q='.uniqid('no-such-project-', true));
        $this->assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('.page__subtitle button[data-action="dialog#open"]'));
        // The header's default content (the action buttons) survives the named subtitle block.
        self::assertCount(1, $crawler->filter('.page__actions a.btn--primary[href="/projects/new"]'));
        self::assertCount(1, $crawler->filter('.empty-state button[data-action="dialog#open"]'));
        $dialog = $crawler->filter('.page[data-controller~="dialog"] dialog.dialog[data-dialog-target="dialog"]');
        self::assertCount(1, $dialog);
        self::assertCount(4, $dialog->filter('.definition__criteria li'));
        self::assertCount(1, $dialog->filter('button[data-action="dialog#close"]'));
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
