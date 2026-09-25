<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form\DataTransformer;

use App\Entity\Contact;
use App\Form\DataTransformer\ContactsTextTransformer;
use App\Repository\ContactRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Uid\Ulid;

final class ContactsTextTransformerTest extends TestCase
{
    public function testTransformOfNonIterableReturnsEmptyString(): void
    {
        self::assertSame('', $this->transformer()->transform(null));
    }

    public function testTransformJoinsContactIds(): void
    {
        $anne = (new Contact())->setName('Anne Jensen');
        $lars = (new Contact())->setName('Lars Holm');

        // Contacts are keyed on their id, never their name: two people can share one.
        self::assertSame(
            sprintf('%s, %s', $anne->getId(), $lars->getId()),
            $this->transformer()->transform([$anne, 'not a contact', $lars]),
        );
    }

    public function testReverseTransformOfNonStringReturnsEmptyCollection(): void
    {
        self::assertCount(0, $this->transformer()->reverseTransform(null));
    }

    public function testReverseTransformOfBlankReturnsEmptyCollection(): void
    {
        self::assertCount(0, $this->transformer()->reverseTransform('   '));
    }

    public function testReverseTransformResolvesEachIdOnce(): void
    {
        $anne = (new Contact())->setName('Anne Jensen');
        $lars = (new Contact())->setName('Lars Holm');
        $byId = [(string) $anne->getId() => $anne, (string) $lars->getId() => $lars];

        $repository = $this->createMock(ContactRepository::class);
        $repository->expects(self::exactly(2))
            ->method('find')
            ->willReturnCallback(static fn (mixed $id): ?Contact => $byId[(string) $id] ?? null);

        // The repeated id and the empty segment are skipped.
        $contacts = (new ContactsTextTransformer($repository))
            ->reverseTransform(sprintf('%s, %s, , %s', $anne->getId(), $lars->getId(), $anne->getId()));

        self::assertSame([$anne, $lars], $contacts->toArray());
    }

    public function testReverseTransformRejectsAnUnknownId(): void
    {
        $repository = $this->createStub(ContactRepository::class);
        $repository->method('find')->willReturn(null);

        $this->expectException(TransformationFailedException::class);
        (new ContactsTextTransformer($repository))->reverseTransform((string) new Ulid());
    }

    public function testReverseTransformRejectsAName(): void
    {
        // A typed name is turned into a contact by the picker through the contact
        // endpoint, so only ids reach the form; anything else is a bad request.
        $repository = $this->createMock(ContactRepository::class);
        $repository->expects(self::never())->method('find');

        $this->expectException(TransformationFailedException::class);
        (new ContactsTextTransformer($repository))->reverseTransform('Anne Jensen');
    }

    private function transformer(): ContactsTextTransformer
    {
        return new ContactsTextTransformer($this->createStub(ContactRepository::class));
    }
}
