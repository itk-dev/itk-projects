<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ProjectCharacter;
use App\Form\ProjectCharacterType;
use App\Repository\ProjectCharacterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/project-characters')]
#[IsGranted('ROLE_USER')]
class ProjectCharacterController extends AbstractController
{
    #[Route('', name: 'admin_project_characters', methods: ['GET'])]
    public function index(ProjectCharacterRepository $characters): Response
    {
        return $this->render('admin/project_characters/index.html.twig', [
            'characters' => $characters->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'admin_project_character_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $character = new ProjectCharacter();
        $form = $this->createForm(ProjectCharacterType::class, $character);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($character);
            $entityManager->flush();
            $this->addFlash('success', 'flash.project_character.created');

            return $this->redirectToRoute('admin_project_characters');
        }

        return $this->render('admin/project_characters/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'admin_project_character_edit', requirements: ['id' => Requirement::ULID], methods: ['GET', 'POST'])]
    public function edit(Request $request, ProjectCharacter $character, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProjectCharacterType::class, $character);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'flash.project_character.updated');

            return $this->redirectToRoute('admin_project_characters');
        }

        return $this->render('admin/project_characters/edit.html.twig', [
            'form' => $form,
            'character' => $character,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_project_character_delete', requirements: ['id' => Requirement::ULID], methods: ['POST'])]
    public function delete(Request $request, ProjectCharacter $character, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-project-character-'.$character->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($character);
            $entityManager->flush();
            $this->addFlash('success', 'flash.project_character.deleted');
        }

        return $this->redirectToRoute('admin_project_characters');
    }
}
