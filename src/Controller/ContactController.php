<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Contact;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Creates a name-only contact for the project form's contact picker.
 *
 * The picker identifies contacts by id, and the form autosaves on every change
 * without re-rendering, so a typed name has to become a persisted contact — with
 * an id the chip can carry — before the next autosave. Created on save instead,
 * the name would be posted again and again, creating the person once per save.
 * The rest of the details are filled in later under the contacts admin.
 */
class ContactController extends AbstractController
{
    #[Route('/contacts', name: 'app_contact_create', methods: ['POST'])]
    public function create(Request $request, EntityManagerInterface $entityManager, ValidatorInterface $validator): JsonResponse
    {
        // Same guard as the project form's autosave: the custom header stands in
        // for a CSRF token, as a cross-origin caller can't set it without a
        // refused CORS preflight.
        if (!$request->isXmlHttpRequest()) {
            throw new BadRequestHttpException('Contacts are created from the project form only.');
        }

        $contact = (new Contact())->setName(trim($request->getPayload()->getString('name')));

        $violations = $validator->validate($contact);
        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[] = (string) $violation->getMessage();
            }

            return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $entityManager->persist($contact);
        $entityManager->flush();

        return $this->json([
            'id' => (string) $contact->getId(),
            'label' => $contact->getLabel(),
        ], Response::HTTP_CREATED);
    }
}
