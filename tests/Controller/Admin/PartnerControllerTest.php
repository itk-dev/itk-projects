<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Partner;
use App\Entity\Project;
use App\Tests\FunctionalTestCase;

final class PartnerControllerTest extends FunctionalTestCase
{
    public function testIndexIsAccessibleToEditors(): void
    {
        $this->loginAsEditor();
        $this->client->request('GET', '/admin/partners');

        $this->assertResponseIsSuccessful();
    }

    public function testIndexDeleteDialogLinksTheAffectedProjects(): void
    {
        $this->loginAsAdmin();
        $partner = $this->createPartner('Linked Partner '.uniqid());
        $project = $this->createProjectUsing($partner, 'Linked Project '.uniqid());

        $crawler = $this->client->request('GET', '/admin/partners');
        $this->assertResponseIsSuccessful();

        // The confirmation has to name what deleting would strip the partner off, and
        // link straight to it — the count alone doesn't tell the admin what breaks.
        $link = $crawler->filter(sprintf('dialog a[href="/projects/%s"]', $project->getId()));
        self::assertCount(1, $link);
        self::assertSame($project->getTitle(), trim($link->text()));

        $this->removeProject((string) $project->getId());
        $this->removePartner((string) $partner->getId());
    }

    public function testNewCreatesPartner(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/admin/partners/new');
        $this->assertResponseIsSuccessful();

        $name = 'Test Partner '.uniqid();
        $form = $crawler->filter('button.btn--primary')->form([
            'partner[name]' => $name,
            'partner[description]' => 'A partner created in a test.',
            'partner[website]' => 'https://example.com',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/partners');

        $partner = $this->partners()->findOneBy(['name' => $name]);
        self::assertInstanceOf(Partner::class, $partner);
        self::assertSame('A partner created in a test.', $partner->getDescription());
        self::assertSame('https://example.com', $partner->getWebsite());

        $this->removePartner((string) $partner->getId());
    }

    public function testNewRejectsADuplicateName(): void
    {
        $this->loginAsAdmin();
        $name = 'Duplicate Partner '.uniqid();
        $id = (string) $this->createPartner($name)->getId();

        $crawler = $this->client->request('GET', '/admin/partners/new');
        $form = $crawler->filter('button.btn--primary')->form(['partner[name]' => $name]);
        $this->client->submit($form);

        // UniqueEntity rejects the second one: the form redisplays with a 422
        // (Symfony's status for an invalid submitted form) and nothing is saved.
        $this->assertResponseStatusCodeSame(422);
        self::assertCount(1, $this->partners()->findBy(['name' => $name]));

        $this->removePartner($id);
    }

    public function testNewRejectsANonHttpWebsite(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/admin/partners/new');

        $name = 'Bad Website Partner '.uniqid();
        $form = $crawler->filter('button.btn--primary')->form([
            'partner[name]' => $name,
            'partner[website]' => 'javascript:alert(1)',
        ]);
        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->partners()->findBy(['name' => $name]));
    }

    public function testNewRejectsANameContainingAComma(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/admin/partners/new');

        // Comma is the separator of the free-tagging field on the project form,
        // so such a name would later be split into two partners.
        $name = 'Aarhus Kommune, Teknik og Miljø '.uniqid();
        $form = $crawler->filter('button.btn--primary')->form(['partner[name]' => $name]);
        $this->client->submit($form);

        $this->assertResponseStatusCodeSame(422);
        self::assertCount(0, $this->partners()->findBy(['name' => $name]));
    }

    public function testEditUpdatesPartner(): void
    {
        $this->loginAsAdmin();
        $id = (string) $this->createPartner('Editable Partner '.uniqid())->getId();

        $crawler = $this->client->request('GET', sprintf('/admin/partners/%s/edit', $id));
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('button.btn--primary')->form(['partner[name]' => 'Edited Partner '.uniqid()]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/partners');
        $this->removePartner($id);
    }

    public function testDeleteRemovesPartnerWithAValidToken(): void
    {
        $this->loginAsAdmin();
        $id = (string) $this->createPartner('Deletable Partner '.uniqid())->getId();

        $crawler = $this->client->request('GET', sprintf('/admin/partners/%s/edit', $id));
        $form = $crawler->filter('form[action$="/delete"]')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/partners');
        $this->entityManager()->clear();
        self::assertNull($this->partners()->find($id));
    }

    public function testDeleteDetachesThePartnerButKeepsTheProject(): void
    {
        $this->loginAsAdmin();
        $partner = $this->createPartner('Detachable Partner '.uniqid());
        $partnerId = (string) $partner->getId();
        $project = $this->createProjectUsing($partner, 'Surviving Project '.uniqid());
        $projectId = (string) $project->getId();

        $crawler = $this->client->request('GET', sprintf('/admin/partners/%s/edit', $partnerId));
        self::assertStringContainsString($project->getTitle(), (string) $this->client->getResponse()->getContent());

        $this->client->submit($crawler->filter('form[action$="/delete"]')->form());
        $this->assertResponseRedirects('/admin/partners');

        // The join table is cleared by its ON DELETE CASCADE rather than by Doctrine,
        // so pin both halves: the partner is gone, the project is not.
        $this->entityManager()->clear();
        self::assertNull($this->partners()->find($partnerId));
        $survivor = $this->projects()->find($projectId);
        self::assertInstanceOf(Project::class, $survivor);
        self::assertCount(0, $survivor->getPartners());

        $this->removeProject($projectId);
    }

    public function testDeleteIgnoresAnInvalidToken(): void
    {
        $this->loginAsAdmin();
        $id = (string) $this->createPartner('Surviving Partner '.uniqid())->getId();

        $this->client->request('POST', sprintf('/admin/partners/%s/delete', $id), ['_token' => 'invalid']);

        $this->assertResponseRedirects('/admin/partners');
        $this->entityManager()->clear();
        self::assertNotNull($this->partners()->find($id));
        $this->removePartner($id);
    }

    public function testProjectsAreSearchableByPartnerName(): void
    {
        $this->loginAsEditor();
        $partner = $this->createPartner('Searchable Partner '.uniqid());
        $project = $this->createProjectUsing($partner, 'Findable Project '.uniqid());

        $crawler = $this->client->request('GET', '/projects?q='.urlencode((string) $partner->getName()));

        $this->assertResponseIsSuccessful();
        self::assertStringContainsString((string) $project->getTitle(), $crawler->filter('#project-results')->text());

        $this->removeProject((string) $project->getId());
        $this->removePartner((string) $partner->getId());
    }

    public function testTheDeleteTriggerCannotSubmitOnItsOwn(): void
    {
        $this->loginAsAdmin();
        $id = (string) $this->createPartner('Guarded Partner '.uniqid())->getId();

        $crawler = $this->client->request('GET', sprintf('/admin/partners/%s/edit', $id));

        // The only submit lives inside the dialog, so a click that lands before
        // Stimulus has hydrated cannot delete anything.
        $buttons = $crawler->filter('form[action$="/delete"] button');
        self::assertSame('button', $buttons->eq(0)->attr('type'));
        self::assertCount(1, $crawler->filter('form[action$="/delete"] button[type="submit"]'));
        self::assertCount(1, $crawler->filter('form[action$="/delete"] dialog button[type="submit"]'));

        $this->removePartner($id);
    }

    private function createPartner(string $name): Partner
    {
        $partner = (new Partner())->setName($name);
        $em = $this->entityManager();
        $em->persist($partner);
        $em->flush();

        return $partner;
    }

    private function createProjectUsing(Partner $partner, string $title): Project
    {
        $project = (new Project())->setTitle($title);
        $project->addPartner($partner);
        $em = $this->entityManager();
        $em->persist($project);
        $em->flush();

        return $project;
    }

    private function removePartner(string $id): void
    {
        $this->entityManager()->clear();
        $partner = $this->partners()->find($id);
        if (null !== $partner) {
            $em = $this->entityManager();
            $em->remove($partner);
            $em->flush();
        }
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
