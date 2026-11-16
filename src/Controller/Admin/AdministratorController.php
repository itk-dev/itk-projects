<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Departments, areas and project types are curated by administrators only, so
 * everyone else needs to know whom to ask; this page lists them. It is the one
 * admin page about users that regular users may see.
 */
#[Route('/admin/administrators')]
#[IsGranted('ROLE_USER')]
class AdministratorController extends AbstractController
{
    #[Route('', name: 'admin_administrators', methods: ['GET'])]
    public function index(UserRepository $users): Response
    {
        return $this->render('admin/administrators/index.html.twig', [
            'admins' => $users->findAdmins(),
        ]);
    }
}
