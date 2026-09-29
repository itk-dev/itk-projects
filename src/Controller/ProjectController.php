<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Entity\User;
use App\Form\ProjectFilterType;
use App\Form\ProjectType;
use App\Model\ProjectFilter;
use App\Repository\ProjectRepository;
use App\Service\ActivityPublisher;
use App\Service\Paginator;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Writer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProjectController extends AbstractController
{
    #[Route('/projects', name: 'app_project_index', methods: ['GET'])]
    public function index(Request $request, ProjectRepository $projects, Paginator $paginator): Response
    {
        $filter = new ProjectFilter();
        $form = $this->createForm(ProjectFilterType::class, $filter);
        $form->handleRequest($request);

        $filter->sort = (string) $request->query->get('sort', 'createdAt');
        $filter->direction = (string) $request->query->get('direction', 'DESC');

        $pagination = $paginator->paginate(
            $projects->search($filter),
            $request->query->getInt('page', 1),
        );

        return $this->render('project/index.html.twig', [
            'form' => $form,
            'pagination' => $pagination,
            'sort' => $filter->sort,
            'direction' => $filter->direction,
        ]);
    }

    #[Route('/projects/export', name: 'app_project_export', methods: ['GET'])]
    public function export(Request $request, ProjectRepository $projects, TranslatorInterface $translator): StreamedResponse
    {
        $filter = new ProjectFilter();
        $this->createForm(ProjectFilterType::class, $filter)->handleRequest($request);
        $filter->sort = (string) $request->query->get('sort', 'createdAt');
        $filter->direction = (string) $request->query->get('direction', 'DESC');

        $rows = $projects->findForExport($filter);

        $translate = static fn (?object $enum): string => $enum instanceof \App\Enum\TranslatableEnum
            ? $translator->trans($enum->labelKey())
            : '';

        $response = new StreamedResponse(function () use ($rows, $translator, $translate): void {
            $csv = Writer::createFromStream(fopen('php://output', 'w'));
            $csv->insertOne([
                'id', $translator->trans('project.title'), $translator->trans('project.topic'),
                $translator->trans('project.status'),
                $translator->trans('project.area'), $translator->trans('project.project_type'),
                $translator->trans('project.organizational_anchoring'), $translator->trans('project.endorsement'),
                $translator->trans('project.endorsement_author'), $translator->trans('project.budget'),
                $translator->trans('project.funding'), $translator->trans('project.stakeholders'),
                $translator->trans('project.strategies'), $translator->trans('project.tags'),
                $translator->trans('project.time_period_start'), $translator->trans('project.time_period_end'),
                $translator->trans('project.contacts'), $translator->trans('project.partners'),
                $translator->trans('project.author'),
            ]);

            $names = static fn (iterable $items): string => implode(', ', array_map('strval', \is_array($items) ? $items : iterator_to_array($items)));

            foreach ($rows as $row) {
                $csv->insertOne([
                    (string) $row->getId(),
                    $row->getTitle(),
                    $row->getTopic(),
                    $translate($row->getStatus()),
                    $row->getArea()?->getName(),
                    $translate($row->getProjectType()),
                    $row->getOrganizationalAnchoring()?->getName(),
                    $row->isEndorsement() ? $translator->trans('filter.yes') : $translator->trans('filter.no'),
                    $translate($row->getEndorsementAuthor()),
                    $row->getBudget(),
                    implode(', ', array_map($translate, $row->getFunding())),
                    $names($row->getStakeholders()),
                    $names($row->getStrategies()),
                    $names($row->getTags()),
                    $row->getTimePeriodStart()?->format('Y-m-d'),
                    $row->getTimePeriodEnd()?->format('Y-m-d'),
                    $names($row->getContacts()),
                    $names($row->getPartners()),
                    ($creator = $row->getCreatedBy()) instanceof User ? $creator->getName() : null,
                ]);
            }
        });

        $filename = sprintf('projects-%s.csv', (new \DateTimeImmutable())->format('Y-m-d'));
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', sprintf('attachment; filename="%s"', $filename));

        return $response;
    }

    #[Route('/projects/new', name: 'app_project_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ActivityPublisher $activityPublisher): Response
    {
        // Autosave creates the project as soon as the form is valid; the page
        // then switches to editing it in place, so a new form saves like an edit.
        // Same guard as edit(): the mandatory X-Autosave header stands in for CSRF.
        $isAutosave = $request->headers->has('X-Autosave');

        $project = new Project();
        $form = $this->createForm(ProjectType::class, $project, [
            'csrf_protection' => !$isAutosave,
            'allow_extra_fields' => $isAutosave,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($project);
            $entityManager->flush();

            $activityPublisher->publish('created', $project, $this->currentUser());

            if ($isAutosave) {
                // Hand back the edit URL so the form keeps autosaving in place.
                $response = new Response(null, Response::HTTP_CREATED);
                $response->headers->set('X-Project-Location', $this->generateUrl('app_project_edit', ['id' => $project->getId()]));

                return $response;
            }

            $this->addFlash('success', 'flash.project.created');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        if ($isAutosave) {
            // Not valid yet (e.g. no title): report it without creating anything.
            return new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render('project/new.html.twig', [
            'form' => $form,
            'project' => $project,
        ]);
    }

    #[Route('/projects/{id}', name: 'app_project_show', requirements: ['id' => Requirement::ULID], methods: ['GET'])]
    public function show(Project $project): Response
    {
        return $this->render('project/show.html.twig', [
            'project' => $project,
        ]);
    }

    #[Route('/projects/{id}/edit', name: 'app_project_edit', requirements: ['id' => Requirement::ULID], methods: ['GET', 'POST'])]
    public function edit(Request $request, Project $project, EntityManagerInterface $entityManager, ActivityPublisher $activityPublisher): Response
    {
        // Autosave posts the same form via fetch; it expects to stay on the page.
        $isAutosave = $request->headers->has('X-Autosave');

        // A background fetch can't run the stateless-CSRF JS (no real submit), so the
        // sentinel token never resolves. Autosave is guarded instead by the mandatory
        // X-Autosave header — a cross-origin caller can't set it without a refused CORS
        // pre-flight — so we drop CSRF and ignore the now-stray _token field for it.
        $form = $this->createForm(ProjectType::class, $project, [
            'csrf_protection' => !$isAutosave,
            'allow_extra_fields' => $isAutosave,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            // Autosave is now the only save path on this form (the Save button is
            // gone), so it must drive the live feed and dashboard too. Repeated
            // autosaves of the same project don't flood the feed: each row is
            // keyed by id and bumped in place rather than stacked.
            $activityPublisher->publish('updated', $project, $this->currentUser());

            if ($isAutosave) {
                return new Response(null, Response::HTTP_NO_CONTENT);
            }

            $this->addFlash('success', 'flash.project.updated');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        if ($isAutosave) {
            // Report the failed validation without redrawing the form the user is editing.
            return new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render('project/edit.html.twig', [
            'form' => $form,
            'project' => $project,
        ]);
    }

    #[Route('/projects/{id}/delete', name: 'app_project_delete', requirements: ['id' => Requirement::ULID], methods: ['POST'])]
    public function delete(Request $request, Project $project, EntityManagerInterface $entityManager, ActivityPublisher $activityPublisher): Response
    {
        if ($this->isCsrfTokenValid('delete-project-'.$project->getId(), (string) $request->request->get('_token'))) {
            $actor = $this->currentUser();

            $entityManager->remove($project);
            $entityManager->flush();

            // Publish after removal so the live dashboard counts are already up to
            // date; the detached entity still holds its title for the feed line.
            $activityPublisher->publish('deleted', $project, $actor);

            $this->addFlash('success', 'flash.project.deleted');
        }

        return $this->redirectToRoute('app_project_index');
    }

    private function currentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user : null;
    }
}
