<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Area;
use App\Form\AreaType;
use App\Repository\AreaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/areas')]
#[IsGranted('ROLE_ADMIN')]
class AreaController extends AbstractController
{
    #[Route('', name: 'admin_areas', methods: ['GET'])]
    public function index(AreaRepository $areas): Response
    {
        return $this->render('admin/areas/index.html.twig', [
            'areas' => $areas->findAllOrdered(),
        ]);
    }

    #[Route('/new', name: 'admin_area_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $area = new Area();
        $form = $this->createForm(AreaType::class, $area);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($area);
            $entityManager->flush();
            $this->addFlash('success', 'flash.area.created');

            return $this->redirectToRoute('admin_areas');
        }

        return $this->render('admin/areas/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'admin_area_edit', requirements: ['id' => Requirement::ULID], methods: ['GET', 'POST'])]
    public function edit(Request $request, Area $area, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(AreaType::class, $area);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'flash.area.updated');

            return $this->redirectToRoute('admin_areas');
        }

        return $this->render('admin/areas/edit.html.twig', [
            'form' => $form,
            'area' => $area,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_area_delete', requirements: ['id' => Requirement::ULID], methods: ['POST'])]
    public function delete(Request $request, Area $area, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-area-'.$area->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($area);
            $entityManager->flush();
            $this->addFlash('success', 'flash.area.deleted');
        }

        return $this->redirectToRoute('admin_areas');
    }
}
