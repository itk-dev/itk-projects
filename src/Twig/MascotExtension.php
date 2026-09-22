<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\Contact;
use App\Entity\Project;
use App\Entity\User;
use App\Repository\ContactRepository;
use App\Repository\ProjectRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Feeds the corner mascot the current user's own numbers: how many projects
 * they have created (to praise them) and their least-complete one (to nudge them
 * to finish it). Returns zeros/null when no user is logged in.
 */
class MascotExtension extends AbstractExtension
{
    public function __construct(
        private readonly Security $security,
        private readonly ProjectRepository $projects,
        private readonly ContactRepository $contacts,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('mascot_context', $this->context(...)),
        ];
    }

    /**
     * @return array{count: int, unfinished: Project|null, incompleteContact: Contact|null}
     */
    public function context(): array
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return ['count' => 0, 'unfinished' => null, 'incompleteContact' => null];
        }

        return [
            'count' => $this->projects->countByCreator($user),
            'unfinished' => $this->projects->findUnfinishedByCreator($user),
            'incompleteContact' => $this->contacts->findIncompleteByCreator($user),
        ];
    }
}
