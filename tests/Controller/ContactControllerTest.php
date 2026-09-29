<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Contact;
use App\Tests\FunctionalTestCase;

final class ContactControllerTest extends FunctionalTestCase
{
    private const array XHR = ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'];

    public function testCreateReturnsTheNewContactsIdAndLabel(): void
    {
        $this->loginAsEditor();
        $name = 'Picker Contact '.uniqid();

        $this->client->jsonRequest('POST', '/contacts', ['name' => "  $name  "], self::XHR);

        $this->assertResponseStatusCodeSame(201);
        $payload = $this->payload();
        self::assertIsString($payload['id'] ?? null);

        $contact = $this->contacts()->find($payload['id']);
        self::assertInstanceOf(Contact::class, $contact);
        self::assertSame($name, $contact->getName(), 'The name is trimmed.');
        self::assertSame($name, $payload['label'] ?? null, 'A name-only contact has no email to show.');

        $em = $this->entityManager();
        $em->remove($contact);
        $em->flush();
    }

    public function testCreateRejectsABlankName(): void
    {
        $this->loginAsEditor();

        $this->client->jsonRequest('POST', '/contacts', ['name' => '   '], self::XHR);

        $this->assertResponseStatusCodeSame(422);
        self::assertNotEmpty($this->payload()['errors'] ?? null);
    }

    public function testCreateRequiresTheXmlHttpRequestHeader(): void
    {
        $this->loginAsEditor();
        $name = 'Cross-site Contact '.uniqid();

        // The header stands in for a CSRF token; a plain cross-origin POST lacks it.
        $this->client->jsonRequest('POST', '/contacts', ['name' => $name]);

        $this->assertResponseStatusCodeSame(400);
        self::assertCount(0, $this->contacts()->findBy(['name' => $name]));
    }

    public function testCreateRequiresASignedInUser(): void
    {
        $name = 'Anonymous Contact '.uniqid();

        $this->client->jsonRequest('POST', '/contacts', ['name' => $name], self::XHR);

        $this->assertResponseRedirects();
        self::assertStringEndsWith('/login', (string) $this->client->getResponse()->headers->get('Location'));
        self::assertCount(0, $this->contacts()->findBy(['name' => $name]));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        $payload = json_decode((string) $this->client->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        return $payload;
    }
}
