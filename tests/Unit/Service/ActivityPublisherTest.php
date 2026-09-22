<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Project;
use App\Repository\DepartmentRepository;
use App\Repository\ProjectRepository;
use App\Service\ActivityPublisher;
use App\Service\DashboardData;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

final class ActivityPublisherTest extends TestCase
{
    public function testPublishSwallowsHubFailuresAndLogsThem(): void
    {
        $hub = $this->createStub(HubInterface::class);
        $hub->method('publish')->willThrowException(new \RuntimeException('hub unreachable'));

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturn('<turbo-stream></turbo-stream>');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/projects/1');

        // DashboardData is final (can't be doubled), so build a real one from stubs.
        $projects = $this->createStub(ProjectRepository::class);
        $projects->method('dashboardRows')->willReturn([]);
        $departments = $this->createStub(DepartmentRepository::class);
        $departments->method('findAllOrdered')->willReturn([]);
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);
        $dashboardData = new DashboardData($projects, $departments, $translator);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning');

        $publisher = new ActivityPublisher($hub, $twig, $urlGenerator, $dashboardData, $logger);

        // An unreachable hub must be swallowed and logged, never bubbled up — the
        // underlying save (autosave) is the primary path and must still succeed.
        $publisher->publish('created', (new Project())->setTitle('Broadcast me'), null);
    }
}
