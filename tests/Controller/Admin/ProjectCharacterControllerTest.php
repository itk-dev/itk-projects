<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\ProjectCharacter;
use App\Tests\FunctionalTestCase;

final class ProjectCharacterControllerTest extends FunctionalTestCase
{
    public function testIndexIsAccessibleToEditors(): void
    {
        $this->loginAsEditor();
        $this->client->request('GET', '/admin/project-characters');

        $this->assertResponseIsSuccessful();
    }

    public function testNewCreatesCharacter(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/admin/project-characters/new');
        $this->assertResponseIsSuccessful();

        $name = 'Test Character '.uniqid();
        $form = $crawler->filter('button.btn--primary')->form(['project_character[name]' => $name]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/project-characters');

        $character = $this->projectCharacters()->findOneBy(['name' => $name]);
        self::assertInstanceOf(ProjectCharacter::class, $character);
        $this->removeCharacter((string) $character->getId());
    }

    public function testNewRejectsADuplicateName(): void
    {
        $this->loginAsAdmin();
        $name = 'Duplicate Character '.uniqid();
        $id = (string) $this->createCharacter($name)->getId();

        $crawler = $this->client->request('GET', '/admin/project-characters/new');
        $form = $crawler->filter('button.btn--primary')->form(['project_character[name]' => $name]);
        $this->client->submit($form);

        // UniqueEntity rejects the second one: the form redisplays with a 422
        // (Symfony's status for an invalid submitted form) and nothing is saved.
        $this->assertResponseStatusCodeSame(422);
        self::assertCount(1, $this->projectCharacters()->findBy(['name' => $name]));

        $this->removeCharacter($id);
    }

    public function testEditUpdatesCharacter(): void
    {
        $this->loginAsAdmin();
        $id = (string) $this->createCharacter('Editable Character '.uniqid())->getId();

        $crawler = $this->client->request('GET', sprintf('/admin/project-characters/%s/edit', $id));
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('button.btn--primary')->form(['project_character[name]' => 'Edited Character '.uniqid()]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/project-characters');
        $this->removeCharacter($id);
    }

    public function testDeleteRemovesCharacterWithAValidToken(): void
    {
        $this->loginAsAdmin();
        $id = (string) $this->createCharacter('Deletable Character '.uniqid())->getId();

        $crawler = $this->client->request('GET', sprintf('/admin/project-characters/%s/edit', $id));
        $form = $crawler->filter('form[action$="/delete"]')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/project-characters');
        $this->entityManager()->clear();
        self::assertNull($this->projectCharacters()->find($id));
    }

    public function testDeleteIgnoresAnInvalidToken(): void
    {
        $this->loginAsAdmin();
        $id = (string) $this->createCharacter('Surviving Character '.uniqid())->getId();

        $this->client->request('POST', sprintf('/admin/project-characters/%s/delete', $id), ['_token' => 'invalid']);

        $this->assertResponseRedirects('/admin/project-characters');
        $this->entityManager()->clear();
        self::assertNotNull($this->projectCharacters()->find($id));
        $this->removeCharacter($id);
    }

    private function createCharacter(string $name): ProjectCharacter
    {
        $character = (new ProjectCharacter())->setName($name);
        $em = $this->entityManager();
        $em->persist($character);
        $em->flush();

        return $character;
    }

    private function removeCharacter(string $id): void
    {
        $this->entityManager()->clear();
        $character = $this->projectCharacters()->find($id);
        if (null !== $character) {
            $em = $this->entityManager();
            $em->remove($character);
            $em->flush();
        }
    }
}
