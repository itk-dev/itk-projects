<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Area;
use App\Tests\FunctionalTestCase;

final class AreaControllerTest extends FunctionalTestCase
{
    public function testIndexIsForbiddenForNonAdmins(): void
    {
        $this->loginAsEditor();
        $this->client->request('GET', '/admin/areas');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testNewCreatesArea(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/admin/areas/new');
        $this->assertResponseIsSuccessful();

        $name = 'Test Area '.uniqid();
        $form = $crawler->filter('button.btn--primary')->form(['area[name]' => $name]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/areas');

        $area = $this->areas()->findOneBy(['name' => $name]);
        self::assertInstanceOf(Area::class, $area);
        $this->removeArea((string) $area->getId());
    }

    public function testNewRejectsADuplicateName(): void
    {
        $this->loginAsAdmin();
        $name = 'Duplicate Area '.uniqid();
        $id = (string) $this->createArea($name)->getId();

        $crawler = $this->client->request('GET', '/admin/areas/new');
        $form = $crawler->filter('button.btn--primary')->form(['area[name]' => $name]);
        $this->client->submit($form);

        // UniqueEntity rejects the second one: the form redisplays with a 422
        // (Symfony's status for an invalid submitted form) and nothing is saved.
        $this->assertResponseStatusCodeSame(422);
        self::assertCount(1, $this->areas()->findBy(['name' => $name]));

        $this->removeArea($id);
    }

    public function testEditUpdatesArea(): void
    {
        $this->loginAsAdmin();
        $id = (string) $this->createArea('Editable Area '.uniqid())->getId();

        $crawler = $this->client->request('GET', sprintf('/admin/areas/%s/edit', $id));
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('button.btn--primary')->form(['area[name]' => 'Edited Area '.uniqid()]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/areas');
        $this->removeArea($id);
    }

    public function testDeleteRemovesAreaWithAValidToken(): void
    {
        $this->loginAsAdmin();
        $id = (string) $this->createArea('Deletable Area '.uniqid())->getId();

        $crawler = $this->client->request('GET', sprintf('/admin/areas/%s/edit', $id));
        $form = $crawler->filter('form[action$="/delete"]')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/areas');
        $this->entityManager()->clear();
        self::assertNull($this->areas()->find($id));
    }

    public function testDeleteIgnoresAnInvalidToken(): void
    {
        $this->loginAsAdmin();
        $id = (string) $this->createArea('Surviving Area '.uniqid())->getId();

        $this->client->request('POST', sprintf('/admin/areas/%s/delete', $id), ['_token' => 'invalid']);

        $this->assertResponseRedirects('/admin/areas');
        $this->entityManager()->clear();
        self::assertNotNull($this->areas()->find($id));
        $this->removeArea($id);
    }

    private function createArea(string $name): Area
    {
        $area = (new Area())->setName($name);
        $em = $this->entityManager();
        $em->persist($area);
        $em->flush();

        return $area;
    }

    private function removeArea(string $id): void
    {
        $this->entityManager()->clear();
        $area = $this->areas()->find($id);
        if (null !== $area) {
            $em = $this->entityManager();
            $em->remove($area);
            $em->flush();
        }
    }
}
