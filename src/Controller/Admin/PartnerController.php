<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Partner;
use App\Form\PartnerType;
use App\Repository\PartnerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/partners')]
#[IsGranted('ROLE_USER')]
class PartnerController extends AbstractController
{
    #[Route('', name: 'admin_partners', methods: ['GET'])]
    public function index(PartnerRepository $partners): Response
    {
        $usage = $partners->findProjectUsage();

        // Pair each partner with its projects here rather than looking the usage
        // up per row, which would mean keying a Twig array by a Ulid object.
        $rows = [];
        foreach ($partners->findAllOrdered() as $partner) {
            $rows[] = [
                'partner' => $partner,
                'projects' => $usage[(string) $partner->getId()] ?? [],
            ];
        }

        return $this->render('admin/partners/index.html.twig', ['rows' => $rows]);
    }

    #[Route('/new', name: 'admin_partner_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $partner = new Partner();
        $form = $this->createForm(PartnerType::class, $partner);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($partner);
            $entityManager->flush();
            $this->addFlash('success', 'flash.partner.created');

            return $this->redirectToRoute('admin_partners');
        }

        return $this->render('admin/partners/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/edit', name: 'admin_partner_edit', requirements: ['id' => Requirement::ULID], methods: ['GET', 'POST'])]
    public function edit(Request $request, Partner $partner, EntityManagerInterface $entityManager, PartnerRepository $partners): Response
    {
        $form = $this->createForm(PartnerType::class, $partner);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'flash.partner.updated');

            return $this->redirectToRoute('admin_partners');
        }

        return $this->render('admin/partners/edit.html.twig', [
            'form' => $form,
            'partner' => $partner,
            'projects' => $partners->findProjectsUsing($partner),
        ]);
    }

    // Also detaches the partner from every project: the join table is cleared by
    // its ON DELETE CASCADE, which Doctrine never sees (unidirectional association).
    #[Route('/{id}/delete', name: 'admin_partner_delete', requirements: ['id' => Requirement::ULID], methods: ['POST'])]
    public function delete(Request $request, Partner $partner, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete-partner-'.$partner->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($partner);
            $entityManager->flush();
            $this->addFlash('success', 'flash.partner.deleted');
        }

        return $this->redirectToRoute('admin_partners');
    }
}
