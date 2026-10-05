<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\ProjectType;
use App\Tests\FunctionalTestCase;

final class ProjectTypeControllerTest extends FunctionalTestCase
{
    public function testIndexIsAccessibleToEditors(): void
    {
        $this->loginAsEditor();
        $this->client->request('GET', '/admin/project-types');

        $this->assertResponseIsSuccessful();
    }

    public function testNewCreatesType(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/admin/project-types/new');
        $this->assertResponseIsSuccessful();

        $name = 'Test Type '.uniqid();
        $form = $crawler->filter('button.btn--primary')->form(['project_type[name]' => $name]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/project-types');

        $type = $this->projectTypes()->findOneBy(['name' => $name]);
        self::assertInstanceOf(ProjectType::class, $type);
        $this->removeType((string) $type->getId());
    }

    public function testNewRejectsADuplicateName(): void
    {
        $this->loginAsAdmin();
        $name = 'Duplicate Type '.uniqid();
        $id = (string) $this->createType($name)->getId();

        $crawler = $this->client->request('GET', '/admin/project-types/new');
        $form = $crawler->filter('button.btn--primary')->form(['project_type[name]' => $name]);
        $this->client->submit($form);

        // UniqueEntity rejects the second one: the form redisplays with a 422
        // (Symfony's status for an invalid submitted form) and nothing is saved.
        $this->assertResponseStatusCodeSame(422);
        self::assertCount(1, $this->projectTypes()->findBy(['name' => $name]));

        $this->removeType($id);
    }

    public function testEditUpdatesType(): void
    {
        $this->loginAsAdmin();
        $id = (string) $this->createType('Editable Type '.uniqid())->getId();

        $crawler = $this->client->request('GET', sprintf('/admin/project-types/%s/edit', $id));
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('button.btn--primary')->form(['project_type[name]' => 'Edited Type '.uniqid()]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/project-types');
        $this->removeType($id);
    }

    public function testDeleteRemovesTypeWithAValidToken(): void
    {
        $this->loginAsAdmin();
        $id = (string) $this->createType('Deletable Type '.uniqid())->getId();

        $crawler = $this->client->request('GET', sprintf('/admin/project-types/%s/edit', $id));
        $form = $crawler->filter('form[action$="/delete"]')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/project-types');
        $this->entityManager()->clear();
        self::assertNull($this->projectTypes()->find($id));
    }

    public function testDeleteIgnoresAnInvalidToken(): void
    {
        $this->loginAsAdmin();
        $id = (string) $this->createType('Surviving Type '.uniqid())->getId();

        $this->client->request('POST', sprintf('/admin/project-types/%s/delete', $id), ['_token' => 'invalid']);

        $this->assertResponseRedirects('/admin/project-types');
        $this->entityManager()->clear();
        self::assertNotNull($this->projectTypes()->find($id));
        $this->removeType($id);
    }

    private function createType(string $name): ProjectType
    {
        $type = (new ProjectType())->setName($name);
        $em = $this->entityManager();
        $em->persist($type);
        $em->flush();

        return $type;
    }

    private function removeType(string $id): void
    {
        $this->entityManager()->clear();
        $type = $this->projectTypes()->find($id);
        if (null !== $type) {
            $em = $this->entityManager();
            $em->remove($type);
            $em->flush();
        }
    }
}
