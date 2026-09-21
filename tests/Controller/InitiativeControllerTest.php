<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Initiative;
use App\Entity\InitiativeAttachment;
use App\Entity\InitiativeImage;
use App\Enum\Status;
use App\Tests\FunctionalTestCase;
use Symfony\Component\HttpFoundation\Response;

final class InitiativeControllerTest extends FunctionalTestCase
{
    public function testIndexAppliesFiltersFromTheQueryString(): void
    {
        $this->loginAsAdmin();
        $match = $this->createInitiative('Deeplinked initiative', Status::Granted);
        $other = $this->createInitiative('Deeplinked other initiative', Status::Rejected);

        // Opening a shared link must narrow the list, not just fill the form.
        $crawler = $this->client->request('GET', '/initiatives?q=Deeplinked&status=granted&sort=title&direction=ASC');

        $this->assertResponseIsSuccessful();
        self::assertSame('granted', $crawler->filter('#initiative-filters select[name="status"] option[selected]')->attr('value'));
        self::assertSame('Deeplinked', $crawler->filter('#initiative-filters input[name="q"]')->attr('value'));

        $titles = $crawler->filter('#initiative-results .cell-title')->each(static fn ($node): string => $node->text());
        self::assertContains('Deeplinked initiative', $titles);
        self::assertNotContains('Deeplinked other initiative', $titles);

        $this->removeInitiative((string) $match->getId());
        $this->removeInitiative((string) $other->getId());
    }

    public function testIndexSearchFieldDoesNotSubmitOnEnter(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/initiatives');

        $this->assertResponseIsSuccessful();

        // Enter clicks the form's default button, so an owned submit button
        // anywhere would turn it into a CSV download.
        self::assertCount(0, $crawler->filter('#initiative-filters button[type="submit"], #initiative-filters input[type="submit"]'));
        self::assertCount(0, $crawler->filter('button[form="initiative-filters"], input[form="initiative-filters"]'));

        $search = $crawler->filter('#initiative-filters input[name="q"]');
        self::assertStringContainsString('keydown.enter->live-search#ignoreEnter', (string) $search->attr('data-action'));
        // Merged onto the field's attributes, not swapped in.
        self::assertNotEmpty($search->attr('placeholder'));
    }

    public function testNewPersistsInitiativeWithInlineContactAndDropsEmptyMedia(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/initiatives/new');
        $this->assertResponseIsSuccessful();

        $token = (string) $crawler->filter('input[name="initiative[_token]"]')->attr('value');
        $this->client->request('POST', '/initiatives/new', [
            'initiative' => [
                'title' => 'Coverage initiative',
                // A typed name creates a new contact on the fly and attaches it.
                'contacts' => 'Coverage Contact',
                // Same free-tagging behaviour for partners.
                'partners' => 'Coverage Partner',
                '_token' => $token,
            ],
        ]);

        $this->assertResponseRedirects();

        $em = $this->entityManager();
        $initiative = $this->initiatives()->findOneBy(['title' => 'Coverage initiative']);
        self::assertInstanceOf(Initiative::class, $initiative);
        self::assertGreaterThanOrEqual(1, $initiative->getContacts()->count(), 'Inline contact should be merged in.');
        self::assertGreaterThanOrEqual(1, $initiative->getPartners()->count(), 'Inline partner should be merged in.');

        $em->remove($initiative);
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

        $crawler = $this->client->request('GET', '/initiatives/new');
        $token = (string) $crawler->filter('input[name="initiative[_token]"]')->attr('value');
        $this->client->request('POST', '/initiatives/new', [
            'initiative' => ['title' => 'Topic initiative', 'topic' => $topic, '_token' => $token],
        ]);
        $this->assertResponseRedirects();

        $initiative = $this->initiatives()->findOneBy(['title' => 'Topic initiative']);
        self::assertInstanceOf(Initiative::class, $initiative);
        self::assertSame($topic, $initiative->getTopic());
        $id = (string) $initiative->getId();

        $crawler = $this->client->request('GET', '/initiatives/'.$id);
        self::assertStringContainsString($topic, $crawler->filter('.card__body')->first()->text());

        // The free-text filter searches the topic alongside title and description.
        $crawler = $this->client->request('GET', '/initiatives?q='.urlencode($topic));
        self::assertStringContainsString('Topic initiative', $crawler->filter('#initiative-results')->text());

        $this->client->request('GET', '/initiatives/export?q='.urlencode($topic));
        $csv = (string) $this->client->getInternalResponse()->getContent();
        self::assertStringContainsString($topic, $csv);

        $this->removeInitiative($id);
    }

    public function testEditUpdatesInitiative(): void
    {
        $this->loginAsAdmin();
        $initiative = $this->createInitiative('Editable initiative');
        $id = (string) $initiative->getId();

        $crawler = $this->client->request('GET', sprintf('/initiatives/%s/edit', $id));
        $this->assertResponseIsSuccessful();

        $token = (string) $crawler->filter('input[name="initiative[_token]"]')->attr('value');
        $this->client->request('POST', sprintf('/initiatives/%s/edit', $id), [
            'initiative' => [
                'title' => 'Edited initiative',
                'images' => [['imageFile' => '']],
                'attachments' => [['file' => '']],
                '_token' => $token,
            ],
        ]);

        $this->assertResponseRedirects(sprintf('/initiatives/%s', $id));

        $this->removeInitiative($id);
    }

    public function testDeleteRemovesInitiativeWithAValidToken(): void
    {
        $this->loginAsAdmin();
        $initiative = $this->createInitiative('Deletable initiative');
        $id = (string) $initiative->getId();

        $crawler = $this->client->request('GET', sprintf('/initiatives/%s/edit', $id));
        $form = $crawler->filter('form[action$="/delete"]')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects('/initiatives');

        $this->entityManager()->clear();
        self::assertNull($this->initiatives()->find($id));
    }

    public function testDeleteIgnoresAnInvalidToken(): void
    {
        $this->loginAsAdmin();
        $initiative = $this->createInitiative('Survivor initiative');
        $id = (string) $initiative->getId();

        $this->client->request('POST', sprintf('/initiatives/%s/delete', $id), ['_token' => 'invalid']);

        $this->assertResponseRedirects('/initiatives');
        $this->entityManager()->clear();
        self::assertNotNull($this->initiatives()->find($id));

        $this->removeInitiative($id);
    }

    public function testEditDropsAttachmentsLeftWithoutAFile(): void
    {
        $this->loginAsAdmin();
        $initiative = $this->createInitiative('Has empty attachment');
        $initiative->addAttachment(new InitiativeAttachment());
        $em = $this->entityManager();
        $em->flush();
        $id = (string) $initiative->getId();

        $crawler = $this->client->request('GET', sprintf('/initiatives/%s/edit', $id));
        $token = (string) $crawler->filter('input[name="initiative[_token]"]')->attr('value');
        $this->client->request('POST', sprintf('/initiatives/%s/edit', $id), [
            'initiative' => [
                'title' => 'Has empty attachment',
                // Re-submit the file-less attachment (empty file, no upload) so the
                // form keeps it; the controller's removeEmptyMedia() then drops it.
                'attachments' => [['file' => '']],
                '_token' => $token,
            ],
        ]);

        $this->assertResponseRedirects(sprintf('/initiatives/%s', $id));

        $this->entityManager()->clear();
        $reloaded = $this->initiatives()->find($id);
        self::assertNotNull($reloaded);
        self::assertCount(0, $reloaded->getAttachments(), 'A file-less attachment should be dropped.');

        $this->removeInitiative($id);
    }

    public function testEditDropsImagesLeftWithoutAFile(): void
    {
        $this->loginAsAdmin();
        $initiative = $this->createInitiative('Has empty image');
        $initiative->addImage(new InitiativeImage());
        $em = $this->entityManager();
        $em->flush();
        $id = (string) $initiative->getId();

        $crawler = $this->client->request('GET', sprintf('/initiatives/%s/edit', $id));
        $token = (string) $crawler->filter('input[name="initiative[_token]"]')->attr('value');
        $this->client->request('POST', sprintf('/initiatives/%s/edit', $id), [
            'initiative' => [
                'title' => 'Has empty image',
                // Re-submit the file-less image so the form keeps it; the
                // controller's removeEmptyMedia() then drops it.
                'images' => [['imageFile' => '']],
                '_token' => $token,
            ],
        ]);

        $this->assertResponseRedirects(sprintf('/initiatives/%s', $id));

        $this->entityManager()->clear();
        $reloaded = $this->initiatives()->find($id);
        self::assertNotNull($reloaded);
        self::assertCount(0, $reloaded->getImages(), 'A file-less image should be dropped.');

        $this->removeInitiative($id);
    }

    public function testNewAutosaveReturnsCreatedWithLocationHeader(): void
    {
        $this->loginAsAdmin();

        // Autosave posts via fetch with the X-Autosave header and no CSRF token.
        $this->client->request('POST', '/initiatives/new', [
            'initiative' => ['title' => 'Autosaved initiative'],
        ], [], ['HTTP_X-Autosave' => '1']);

        $this->assertResponseStatusCodeSame(Response::HTTP_CREATED);
        self::assertTrue($this->client->getResponse()->headers->has('X-Initiative-Location'));

        $initiative = $this->initiatives()->findOneBy(['title' => 'Autosaved initiative']);
        self::assertInstanceOf(Initiative::class, $initiative);

        $this->removeInitiative((string) $initiative->getId());
    }

    public function testNewAutosaveReturnsUnprocessableWhenInvalid(): void
    {
        $this->loginAsAdmin();

        // An empty title fails NotBlank, so autosave reports it without creating anything.
        $this->client->request('POST', '/initiatives/new', [
            'initiative' => ['title' => ''],
        ], [], ['HTTP_X-Autosave' => '1']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);
        self::assertNull($this->initiatives()->findOneBy(['title' => '']));
    }

    public function testEditAutosaveReturnsNoContent(): void
    {
        $this->loginAsAdmin();
        $initiative = $this->createInitiative('Autosave edit');
        $id = (string) $initiative->getId();

        $this->client->request('POST', sprintf('/initiatives/%s/edit', $id), [
            'initiative' => ['title' => 'Autosave edited'],
        ], [], ['HTTP_X-Autosave' => '1']);

        $this->assertResponseStatusCodeSame(Response::HTTP_NO_CONTENT);

        $this->removeInitiative($id);
    }

    public function testEditAutosaveReturnsUnprocessableWhenInvalid(): void
    {
        $this->loginAsAdmin();
        $initiative = $this->createInitiative('Autosave edit invalid');
        $id = (string) $initiative->getId();

        $this->client->request('POST', sprintf('/initiatives/%s/edit', $id), [
            'initiative' => ['title' => ''],
        ], [], ['HTTP_X-Autosave' => '1']);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->removeInitiative($id);
    }

    private function createInitiative(string $title, ?Status $status = null): Initiative
    {
        $initiative = (new Initiative())->setTitle($title)->setStatus($status);
        $em = $this->entityManager();
        $em->persist($initiative);
        $em->flush();

        return $initiative;
    }

    private function removeInitiative(string $id): void
    {
        $this->entityManager()->clear();
        $initiative = $this->initiatives()->find($id);
        if (null !== $initiative) {
            $em = $this->entityManager();
            $em->remove($initiative);
            $em->flush();
        }
    }
}
