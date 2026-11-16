<?php

declare(strict_types=1);

namespace App\Tests\Controller\Admin;

use App\Entity\Department;
use App\Tests\FunctionalTestCase;

final class DepartmentControllerTest extends FunctionalTestCase
{
    public function testIndexIsForbiddenForNonAdmins(): void
    {
        $this->loginAsEditor();
        $this->client->request('GET', '/admin/departments');

        $this->assertResponseStatusCodeSame(403);
    }

    public function testNewCreatesDepartment(): void
    {
        $this->loginAsAdmin();
        $crawler = $this->client->request('GET', '/admin/departments/new');
        $this->assertResponseIsSuccessful();

        $name = 'Test Department '.uniqid();
        $form = $crawler->filter('button.btn--primary')->form(['department[name]' => $name]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/departments');

        $department = $this->departments()->findOneBy(['name' => $name]);
        self::assertInstanceOf(Department::class, $department);
        $this->removeDepartment((string) $department->getId());
    }

    public function testNewRejectsADuplicateName(): void
    {
        $this->loginAsAdmin();
        $name = 'Duplicate Department '.uniqid();
        $id = (string) $this->createDepartment($name)->getId();

        $crawler = $this->client->request('GET', '/admin/departments/new');
        $form = $crawler->filter('button.btn--primary')->form(['department[name]' => $name]);
        $this->client->submit($form);

        // UniqueEntity rejects the second one: the form redisplays with a 422
        // (Symfony's status for an invalid submitted form) and nothing is saved.
        $this->assertResponseStatusCodeSame(422);
        self::assertCount(1, $this->departments()->findBy(['name' => $name]));

        $this->removeDepartment($id);
    }

    public function testEditUpdatesDepartment(): void
    {
        $this->loginAsAdmin();
        $id = (string) $this->createDepartment('Editable Department '.uniqid())->getId();

        $crawler = $this->client->request('GET', sprintf('/admin/departments/%s/edit', $id));
        $this->assertResponseIsSuccessful();

        $form = $crawler->filter('button.btn--primary')->form(['department[name]' => 'Edited Department '.uniqid()]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/departments');
        $this->removeDepartment($id);
    }

    public function testDeleteRemovesDepartmentWithAValidToken(): void
    {
        $this->loginAsAdmin();
        $id = (string) $this->createDepartment('Deletable Department '.uniqid())->getId();

        $crawler = $this->client->request('GET', sprintf('/admin/departments/%s/edit', $id));
        $form = $crawler->filter('form[action$="/delete"]')->form();
        $this->client->submit($form);

        $this->assertResponseRedirects('/admin/departments');
        $this->entityManager()->clear();
        self::assertNull($this->departments()->find($id));
    }

    public function testDeleteIgnoresAnInvalidToken(): void
    {
        $this->loginAsAdmin();
        $id = (string) $this->createDepartment('Surviving Department '.uniqid())->getId();

        $this->client->request('POST', sprintf('/admin/departments/%s/delete', $id), ['_token' => 'invalid']);

        $this->assertResponseRedirects('/admin/departments');
        $this->entityManager()->clear();
        self::assertNotNull($this->departments()->find($id));
        $this->removeDepartment($id);
    }

    private function createDepartment(string $name): Department
    {
        $department = (new Department())->setName($name);
        $em = $this->entityManager();
        $em->persist($department);
        $em->flush();

        return $department;
    }

    private function removeDepartment(string $id): void
    {
        $this->entityManager()->clear();
        $department = $this->departments()->find($id);
        if (null !== $department) {
            $em = $this->entityManager();
            $em->remove($department);
            $em->flush();
        }
    }
}
