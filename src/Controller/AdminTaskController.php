<?php

namespace App\Controller;

use App\Entity\Task;
use App\Entity\TaskDependency;
use App\Entity\TaskDocument;
use App\Entity\TaskProblem;
use App\Entity\TaskStatusHistory;
use App\Entity\TaskTimeEntry;
use App\Entity\User;
use App\Repository\TaskTimeEntryRepository;
use App\Service\TaskActivityService;
use App\Service\TaskHierarchyService;
use App\Service\TaskLifecycleService;
use App\Service\TaskRecurrenceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/tasks', name: 'admin_task_detail_')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class AdminTaskController extends AbstractController
{
    private const ALLOWED_MIME_TYPES = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'image/jpeg', 'image/png', 'image/webp', 'text/plain'];

    #[Route('/{id}/status', name: 'status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function status(Task $task, Request $request, EntityManagerInterface $em, TaskLifecycleService $lifecycle, TaskTimeEntryRepository $taskTimeEntries): RedirectResponse
    {
        $this->guardCsrf($request, 'task_status_'.$task->getId());
        $status = (string) $request->request->get('status');
        if (!in_array($status, [Task::STATUS_TODO, Task::STATUS_IN_PROGRESS, Task::STATUS_PAUSED, Task::STATUS_COMPLETED], true)) {
            $this->addFlash('error', 'Choose a valid task status.'); return $this->workspaceRedirect($task);
        }
        if ($task->getStatus() !== $status) {
            $user = $this->getAuthenticatedUser();
            if ($status !== Task::STATUS_IN_PROGRESS) {
                $taskTimeEntries->findOpenForUserAndTask($user, $task)?->setEndedAt(new \DateTimeImmutable());
            }
            if ($next = $lifecycle->transition($task, $status, $user)) { $em->persist($next); }
            $em->flush();
        }
        return $this->workspaceRedirect($task);
    }

    #[Route('/{id}/time-log', name: 'time_log', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function timeLog(Task $task, Request $request, EntityManagerInterface $em, TaskActivityService $activity): RedirectResponse
    {
        $this->guardCsrf($request, 'task_time_'.$task->getId());
        $minutes = (int) $request->request->get('minutes', 0);
        $employee = $em->getRepository(User::class)->find((int) $request->request->get('employee_id', 0));
        if (!$employee instanceof User || $minutes < 1 || $minutes > 1440) { $this->addFlash('error', 'Choose an employee and enter 1–1440 minutes.'); return $this->workspaceRedirect($task); }
        $endedAt = new \DateTimeImmutable();
        $entry = (new TaskTimeEntry())->setTask($task)->setEmployee($employee)->setStartedAt($endedAt->modify('-'.$minutes.' minutes'))->setEndedAt($endedAt)->setNote((string) $request->request->get('note'));
        $em->persist($entry);
        $activity->record($task, $this->getAuthenticatedUser(), 'time_logged', $minutes.' minutes logged for '.$employee->getFullName().'.', ['minutes' => $minutes]);
        $em->flush();
        return $this->workspaceRedirect($task);
    }

    #[Route('/{id}/timer/start', name: 'timer_start', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function timerStart(Task $task, Request $request, TaskTimeEntryRepository $taskTimeEntries, EntityManagerInterface $em, TaskLifecycleService $lifecycle, TaskActivityService $activity): RedirectResponse
    {
        $this->guardCsrf($request, 'task_timer_'.$task->getId());
        if ($task->getStatus() === Task::STATUS_COMPLETED) {
            $this->addFlash('error', 'Reopen the task before starting its timer.');

            return $this->timerRedirect($task, $request);
        }

        $user = $this->getAuthenticatedUser();
        $openEntry = $taskTimeEntries->findOpenForUser($user);
        if ($openEntry?->getTask() === $task) {
            return $this->timerRedirect($task, $request);
        }

        $now = new \DateTimeImmutable();
        if ($openEntry) {
            $openEntry->setEndedAt($now);
            $previousTask = $openEntry->getTask();
            if ($previousTask && $previousTask->getStatus() !== Task::STATUS_COMPLETED) {
                if ($next = $lifecycle->transition($previousTask, Task::STATUS_PAUSED, $user)) { $em->persist($next); }
            }
        }

        $entry = (new TaskTimeEntry())->setTask($task)->setEmployee($user)->setStartedAt($now);
        $em->persist($entry);
        if ($next = $lifecycle->transition($task, Task::STATUS_IN_PROGRESS, $user)) { $em->persist($next); }
        $activity->record($task, $user, 'timer_started', 'Task timer started.');
        $em->flush();

        return $this->timerRedirect($task, $request);
    }

    #[Route('/{id}/timer/stop', name: 'timer_stop', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function timerStop(Task $task, Request $request, TaskTimeEntryRepository $taskTimeEntries, EntityManagerInterface $em, TaskLifecycleService $lifecycle, TaskActivityService $activity): RedirectResponse
    {
        $this->guardCsrf($request, 'task_timer_'.$task->getId());
        $user = $this->getAuthenticatedUser();
        $entry = $taskTimeEntries->findOpenForUserAndTask($user, $task);
        if ($entry) {
            $entry->setEndedAt(new \DateTimeImmutable());
            if ($task->getStatus() !== Task::STATUS_COMPLETED) {
                if ($next = $lifecycle->transition($task, Task::STATUS_PAUSED, $user)) { $em->persist($next); }
            }
            $activity->record($task, $user, 'timer_stopped', 'Task timer stopped.');
            $em->flush();
        }

        return $this->timerRedirect($task, $request);
    }

    #[Route('/{id}/documents/upload', name: 'document_upload', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function documentUpload(Task $task, Request $request, EntityManagerInterface $em, KernelInterface $kernel, TaskActivityService $activity): RedirectResponse
    {
        $this->guardCsrf($request, 'task_document_'.$task->getId());
        $file = $request->files->get('document');
        if (!$file instanceof UploadedFile || !$file->isValid()) { $this->addFlash('error', 'Choose a valid document.'); return $this->workspaceRedirect($task); }
        $mime = (string) ($file->getMimeType() ?: 'application/octet-stream');
        if (!in_array($mime, self::ALLOWED_MIME_TYPES, true) || ($file->getSize() !== false && $file->getSize() > 12 * 1024 * 1024)) { $this->addFlash('error', 'Use an allowed file type up to 12 MB.'); return $this->workspaceRedirect($task); }
        $original = $file->getClientOriginalName() ?: 'document';
        $extension = preg_replace('/[^a-z0-9]/', '', strtolower((string) ($file->guessExtension() ?: pathinfo($original, PATHINFO_EXTENSION)))) ?: 'bin';
        $stored = bin2hex(random_bytes(16)).'.'.$extension;
        $file->move($this->storageDir($kernel), $stored);
        $document = (new TaskDocument())->setTask($task)->setUploadedBy($this->getAuthenticatedUser())->setOriginalFilename($original)->setStoredFilename($stored)->setMimeType($mime)->setFileSize((int) $file->getSize());
        $task->addDocument($document); $em->persist($document);
        $activity->record($task, $this->getAuthenticatedUser(), 'document_added', 'Document '.$original.' uploaded.');
        $em->flush();
        return $this->workspaceRedirect($task);
    }

    #[Route('/documents/{id}/download', name: 'document_download', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function documentDownload(TaskDocument $document, KernelInterface $kernel): BinaryFileResponse
    {
        $path = $this->storageDir($kernel).'/'.$document->getStoredFilename();
        if (!is_file($path)) { throw $this->createNotFoundException('Document file is missing.'); }
        return $this->file($path, $document->getOriginalFilename(), ResponseHeaderBag::DISPOSITION_ATTACHMENT);
    }

    #[Route('/documents/{id}/delete', name: 'document_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function documentDelete(TaskDocument $document, Request $request, EntityManagerInterface $em, KernelInterface $kernel): RedirectResponse
    {
        $task = $document->getTask() ?? throw $this->createNotFoundException();
        $this->guardCsrf($request, 'task_document_delete_'.$document->getId());
        $path = $this->storageDir($kernel).'/'.$document->getStoredFilename(); if (is_file($path)) { unlink($path); }
        $em->remove($document); $em->flush();
        return $this->workspaceRedirect($task);
    }

    #[Route('/{id}/dependencies', name: 'dependency_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function dependencyAdd(Task $task, Request $request, EntityManagerInterface $em, TaskHierarchyService $hierarchy, TaskActivityService $activity): RedirectResponse
    {
        $this->guardCsrf($request, 'task_dependency_'.$task->getId());
        $prerequisite = $em->getRepository(Task::class)->find((int) $request->request->get('prerequisite_id', 0));
        if (!$prerequisite instanceof Task) { $this->addFlash('error', 'Choose a valid prerequisite.'); return $this->workspaceRedirect($task); }
        try { $hierarchy->assertValidDependency($task, $prerequisite); } catch (\DomainException $e) { $this->addFlash('error', $e->getMessage()); return $this->workspaceRedirect($task); }
        foreach ($task->getDependencies() as $existing) { if ($existing->getPrerequisite() === $prerequisite) { return $this->workspaceRedirect($task); } }
        $dependency = (new TaskDependency())->setTask($task)->setPrerequisite($prerequisite); $task->addDependency($dependency); $em->persist($dependency);
        $activity->record($task, $this->getAuthenticatedUser(), 'dependency_added', 'Dependency on '.$prerequisite->getTitle().' added.'); $em->flush();
        return $this->workspaceRedirect($task);
    }

    #[Route('/dependencies/{id}/delete', name: 'dependency_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function dependencyDelete(TaskDependency $dependency, Request $request, EntityManagerInterface $em): RedirectResponse
    {
        $task = $dependency->getTask() ?? throw $this->createNotFoundException(); $this->guardCsrf($request, 'task_dependency_delete_'.$dependency->getId()); $em->remove($dependency); $em->flush(); return $this->workspaceRedirect($task);
    }

    #[Route('/{id}/problems', name: 'problem_add', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function problemAdd(Task $task, Request $request, EntityManagerInterface $em, TaskActivityService $activity): RedirectResponse
    {
        $this->guardCsrf($request, 'task_problem_'.$task->getId()); $description = trim((string) $request->request->get('description'));
        if ($description === '') { $this->addFlash('error', 'Describe the problem first.'); return $this->workspaceRedirect($task); }
        $problem = (new TaskProblem())->setTask($task)->setAuthor($this->getAuthenticatedUser())->setDescription($description); $task->addProblem($problem); $em->persist($problem);
        $activity->record($task, $this->getAuthenticatedUser(), 'problem_added', 'A problem was reported.'); $em->flush(); return $this->workspaceRedirect($task);
    }

    #[Route('/problems/{id}/resolve', name: 'problem_resolve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function problemResolve(TaskProblem $problem, Request $request, EntityManagerInterface $em, TaskActivityService $activity): RedirectResponse
    {
        $task = $problem->getTask() ?? throw $this->createNotFoundException(); $this->guardCsrf($request, 'task_problem_resolve_'.$problem->getId()); $problem->resolve($this->getAuthenticatedUser());
        $activity->record($task, $this->getAuthenticatedUser(), 'problem_resolved', 'A problem was resolved.'); $em->flush(); return $this->workspaceRedirect($task);
    }

    private function workspaceRedirect(Task $task, bool $keepPanel = true): RedirectResponse { return $this->redirectToRoute('admin_tasks', ['project' => $task->getProject()?->getId(), 'task' => $keepPanel ? $task->getId() : null]); }
    private function timerRedirect(Task $task, Request $request): RedirectResponse { return $this->workspaceRedirect($task, $request->request->get('return_to') !== 'list'); }
    private function storageDir(KernelInterface $kernel): string { $dir = $kernel->getProjectDir().'/var/task-documents'; if (!is_dir($dir)) { mkdir($dir, 0775, true); } return $dir; }
    private function guardCsrf(Request $request, string $id): void { if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) { throw $this->createAccessDeniedException('Invalid CSRF token.'); } }
    private function getAuthenticatedUser(): User { $user = $this->getUser(); if (!$user instanceof User) { throw $this->createAccessDeniedException(); } return $user; }
}
