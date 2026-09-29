<?php

declare(strict_types=1);

namespace App\Form\DataTransformer;

use App\Entity\Contact;
use App\Repository\ContactRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Form\DataTransformerInterface;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Uid\Ulid;

/**
 * Bridges a comma-separated text input and a collection of {@see Contact}s.
 *
 * Unlike the term and partner fields, contacts are keyed on their id rather
 * than their name: people share names, so a name cannot say which "Anne Jensen"
 * is meant. Every token is the ULID of an existing contact; a typed name is
 * turned into a contact (and an id) by the picker itself, through
 * {@see \App\Controller\ContactController}, before the form is posted.
 *
 * @implements DataTransformerInterface<mixed, mixed>
 */
final readonly class ContactsTextTransformer implements DataTransformerInterface
{
    public function __construct(private ContactRepository $contactRepository)
    {
    }

    public function transform(mixed $value): string
    {
        if (!is_iterable($value)) {
            return '';
        }

        $ids = [];
        foreach ($value as $contact) {
            if ($contact instanceof Contact) {
                $ids[] = (string) $contact->getId();
            }
        }

        return implode(', ', $ids);
    }

    /**
     * @return Collection<int, Contact>
     */
    public function reverseTransform(mixed $value): Collection
    {
        $contacts = new ArrayCollection();

        if (!\is_string($value) || '' === trim($value)) {
            return $contacts;
        }

        $seen = [];
        foreach (explode(',', $value) as $token) {
            $token = trim($token);
            if ('' === $token) {
                continue;
            }
            if (!Ulid::isValid($token)) {
                throw new TransformationFailedException(sprintf('"%s" is not a contact id.', $token));
            }

            $id = Ulid::fromString($token);
            if (isset($seen[(string) $id])) {
                continue;
            }
            $seen[(string) $id] = true;

            $contact = $this->contactRepository->find($id);
            if (!$contact instanceof Contact) {
                throw new TransformationFailedException(sprintf('Unknown contact "%s".', $token));
            }

            $contacts->add($contact);
        }

        return $contacts;
    }
}
