<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Department;
use App\Form\DepartmentType;
use App\Repository\DepartmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/departments')]
#[IsGranted('ROLE_ADMIN')]
class DepartmentController extends AbstractController
{
    #[Route('', name: 'admin_departments', methods: ['GET'])]
    public function index(DepartmentRepository $departments): Response
    {
        return $this->render('admin/departments/index.html.twig', [
            'departments' => $departments->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'admin_department_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $department = new Department();
        $form = $this->createForm(DepartmentType::class, $department);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($department);
            $entityManager->flush();
            $this->addFlash('success', 'flash.department.created');

            return $this->redirectToRoute('admin_departments');
        }

        return $this->render('admin/departments/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'admin_department_edit', requirements: ['id' => Requirement::ULID], methods: ['GET', 'POST'])]
    public function edit(Request $request, Department $department, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(DepartmentType::class, $department);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'flash.department.updated');

            return $this->redirectToRoute('admin_departments');
        }

        return $this->render('admin/departments/edit.html.twig', [
            'form' => $form,
            'department' => $department,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_department_delete', requirements: ['id' => Requirement::ULID], methods: ['POST'])]
    public function delete(Request $request, Department $department, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-department-'.$department->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($department);
            $entityManager->flush();
            $this->addFlash('success', 'flash.department.deleted');
        }

        return $this->redirectToRoute('admin_departments');
    }
}
