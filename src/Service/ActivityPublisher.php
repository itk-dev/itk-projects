<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Project;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Pushes a single Mercure payload describing a project change to every
 * connected client: a Turbo Stream that prepends the change to the live activity
 * feed and refreshes the dashboard's KPIs and visualisations. The aggregates are
 * recomputed here so the broadcast reflects the state after flush.
 */
final class ActivityPublisher
{
    /**
     * Must match the topic the dashboard subscribes to via turbo_stream_from().
     */
    public const string TOPIC = 'activities';

    public function __construct(
        private readonly HubInterface $hub,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly DashboardData $dashboardData,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function publish(string $action, Project $project, ?User $actor): void
    {
        // A deleted project has no page left to link to.
        $url = 'deleted' === $action
            ? null
            : $this->urlGenerator->generate('app_project_show', ['id' => $project->getId()]);

        $stream = $this->twig->render('activity/_broadcast.html.twig', [
            'action' => $action,
            'projectId' => (string) $project->getId(),
            'title' => $project->getTitle(),
            'url' => $url,
            'actor' => $actor?->getName(),
            'at' => new \DateTimeImmutable(),
            'viz' => $this->dashboardData->build(),
        ]);

        try {
            $this->hub->publish(new Update(self::TOPIC, $stream));
        } catch (\Throwable $e) {
            // A live-broadcast failure (e.g. the Mercure hub being unreachable) must
            // never break the underlying save — autosave is the primary save path.
            $this->logger->warning('Failed to publish activity update: {message}', [
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);
        }
    }
}
