<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ProjectType;
use App\Form\ProjectTypeType;
use App\Repository\ProjectTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/project-types')]
#[IsGranted('ROLE_USER')]
class ProjectTypeController extends AbstractController
{
    #[Route('', name: 'admin_project_types', methods: ['GET'])]
    public function index(ProjectTypeRepository $types): Response
    {
        return $this->render('admin/project_types/index.html.twig', [
            'types' => $types->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'admin_project_type_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $type = new ProjectType();
        $form = $this->createForm(ProjectTypeType::class, $type);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($type);
            $entityManager->flush();
            $this->addFlash('success', 'flash.project_type.created');

            return $this->redirectToRoute('admin_project_types');
        }

        return $this->render('admin/project_types/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'admin_project_type_edit', requirements: ['id' => Requirement::ULID], methods: ['GET', 'POST'])]
    public function edit(Request $request, ProjectType $type, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProjectTypeType::class, $type);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'flash.project_type.updated');

            return $this->redirectToRoute('admin_project_types');
        }

        return $this->render('admin/project_types/edit.html.twig', [
            'form' => $form,
            'type' => $type,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_project_type_delete', requirements: ['id' => Requirement::ULID], methods: ['POST'])]
    public function delete(Request $request, ProjectType $type, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-project-type-'.$type->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($type);
            $entityManager->flush();
            $this->addFlash('success', 'flash.project_type.deleted');
        }

        return $this->redirectToRoute('admin_project_types');
    }
}
