<?php

namespace App\Controller;

use App\Entity\AttendanceEntry;
use App\Entity\BreakEntry;
use App\Entity\LeaveRequest;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\TaskComment;
use App\Entity\TaskTimeEntry;
use App\Entity\User;
use App\Form\EmployeeProfileFormType;
use App\Form\EmployeeTaskFormType;
use App\Form\LeaveRequestFormType;
use App\Repository\AttendanceEntryRepository;
use App\Repository\LeaveRequestRepository;
use App\Repository\TaskTimeEntryRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use App\Service\TaskHierarchyService;
use App\Service\TaskLifecycleService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/employee', name: 'employee_')]
#[IsGranted('ROLE_EMPLOYEE')]
class EmployeeController extends AbstractController
{
    private const TASK_STATUS_OPTIONS = [
        Task::STATUS_TODO => 'To do',
        Task::STATUS_IN_PROGRESS => 'In progress',
        Task::STATUS_PAUSED => 'Paused',
        Task::STATUS_COMPLETED => 'Completed',
    ];

    #[Route('', name: 'dashboard')]
    public function dashboard(
        Request $request,
        AttendanceEntryRepository $attendanceEntries,
        LeaveRequestRepository $leaveRequests,
        TaskTimeEntryRepository $taskTimeEntries,
        TaskRepository $tasks,
        UserRepository $users,
        TaskHierarchyService $hierarchy,
        EntityManagerInterface $entityManager,
    ): Response {
        $employee = $this->getEmployeeUser();
        $openAttendance = $attendanceEntries->findOpenForUser($employee);
        $activeBreak = $openAttendance ? $this->findActiveBreak($openAttendance) : null;
        $weekTimeline = $this->buildWeekTimeline(
            $attendanceEntries,
            $employee,
            $this->readTimelineDate($request->query->get('week')),
        );
        $leaveRequest = (new LeaveRequest())->setEmployee($employee);
        $leaveForm = $this->createForm(LeaveRequestFormType::class, $leaveRequest, [
            'action' => $this->generateUrl('employee_leave_apply'),
        ]);
        $employeeProjects = $this->getEmployeeProjectChoices($employee);
        $selectedTaskProject = null;
        $projectId = (int) $request->query->get('project', 0);
        foreach ($employeeProjects as $project) {
            if ($project->getId() === $projectId && $employee->getProjects()->contains($project)) {
                $selectedTaskProject = $project;
                break;
            }
        }
        $selectedTaskProject ??= $employeeProjects[0] ?? null;
        $workspaceTasks = $selectedTaskProject ? $tasks->findWorkspaceTasks($selectedTaskProject) : [];
        $selectedEmployeeTask = null;
        $taskId = (int) $request->query->get('task', 0);
        foreach ($workspaceTasks as $workspaceTask) {
            if ($workspaceTask->getId() === $taskId) {
                $selectedEmployeeTask = $workspaceTask;
                break;
            }
        }
        $taskForm = null;

        if ($employeeProjects !== []) {
            $taskDraft = (new Task())
                ->addAssignee($employee)
                ->setProject($selectedTaskProject)
                ->setEstimatedMinutes(0);
            $taskForm = $this->createForm(EmployeeTaskFormType::class, $taskDraft, [
                'action' => $this->generateUrl('employee_task_create'),
                'projects' => $employeeProjects,
                'employees' => $this->getActiveEmployeeChoices($users),
            ]);
        }

        return $this->render('employee/dashboard.html.twig', [
            'employee' => $employee,
            'projects' => $employee->getProjects(),
            'tasks' => $this->findVisibleTasks($entityManager, $employee),
            'open_attendance' => $openAttendance,
            'active_break' => $activeBreak,
            'active_task_entry' => $taskTimeEntries->findOpenForUser($employee),
            'recent_attendance' => $attendanceEntries->findRecentForUser($employee),
            'week_timeline' => $weekTimeline,
            'leave_form' => $leaveForm->createView(),
            'task_form' => $taskForm?->createView(),
            'leave_requests' => $leaveRequests->findRecentForUser($employee),
            'task_status_options' => self::TASK_STATUS_OPTIONS,
            'task_projects' => $employeeProjects,
            'selected_task_project' => $selectedTaskProject,
            'employee_task_rows' => $hierarchy->buildTree($workspaceTasks),
            'selected_employee_task' => $selectedEmployeeTask,
        ]);
    }

    #[Route('/profile', name: 'profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, EntityManagerInterface $entityManager, KernelInterface $kernel): Response
    {
        $employee = $this->getEmployeeUser();
        $form = $this->createForm(EmployeeProfileFormType::class, $employee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('profileImageFile')->getData();
            $croppedImage = $form->get('croppedProfileImage')->getData();
            $removeProfileImage = (bool) $form->get('removeProfileImage')->getData();
            $employee->setSkills($this->sanitizeProfileRichText($employee->getSkills()));

            if ($removeProfileImage) {
                $this->deleteProfileImage($employee, $kernel->getProjectDir());
            } elseif (is_string($croppedImage) && $croppedImage !== '') {
                $this->storeCroppedProfileImage($employee, $croppedImage, $kernel->getProjectDir());
            } elseif ($imageFile instanceof UploadedFile) {
                $this->storeProfileImage($employee, $imageFile, $kernel->getProjectDir());
            }

            $entityManager->flush();
            $this->addFlash('success', 'Profile updated.');

            return $this->redirectToRoute('employee_profile');
        }

        return $this->render('employee/profile.html.twig', [
            'employee' => $employee,
            'profile_form' => $form->createView(),
        ]);
    }

    #[Route('/leave/apply', name: 'leave_apply', methods: ['POST'])]
    public function applyLeave(Request $request, EntityManagerInterface $entityManager): Response
    {
        $employee = $this->getEmployeeUser();
        $leaveRequest = (new LeaveRequest())->setEmployee($employee);
        $form = $this->createForm(LeaveRequestFormType::class, $leaveRequest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($leaveRequest->getStartDate() && $leaveRequest->getEndDate() && $leaveRequest->getEndDate() < $leaveRequest->getStartDate()) {
                $this->addFlash('error', 'Leave end date cannot be before the start date.');

                return $this->redirect($this->generateUrl('employee_dashboard').'?tab=attendance');
            }

            $entityManager->persist($leaveRequest);
            $entityManager->flush();
            $this->addFlash('success', 'Leave request submitted.');

            return $this->redirect($this->generateUrl('employee_dashboard').'?tab=attendance');
        }

        $this->addFlash('error', 'Please check the leave request form.');

        return $this->redirect($this->generateUrl('employee_dashboard').'?tab=attendance');
    }

    #[Route('/tasks/{id}', name: 'task_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showTask(Task $task): Response
    {
        $employee = $this->getEmployeeUser();
        $this->denyUnlessTaskVisible($task, $employee);

        return $this->render('task/show.html.twig', [
            'task' => $task,
            'back_path' => $this->generateUrl('employee_dashboard').'?tab=tasks',
            'back_label' => 'Back to My Tasks',
            'edit_path' => null,
            'comment_add_path' => $this->generateUrl('employee_task_comment', ['id' => $task->getId()]),
            'comment_edit_route' => 'employee_task_comment_edit',
            'comment_delete_route' => 'employee_task_comment_delete',
            'can_administer_comments' => false,
            'can_update_status' => $this->canUpdateAssignedTask($task, $employee),
            'status_update_path' => $this->generateUrl('employee_task_status', ['id' => $task->getId()]),
            'task_status_options' => self::TASK_STATUS_OPTIONS,
        ]);
    }

    #[Route('/projects/{id}', name: 'project_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showProject(Project $project, EntityManagerInterface $entityManager): Response
    {
        $employee = $this->getEmployeeUser();
        $this->denyUnlessProjectMember($project, $employee);

        return $this->render('employee/project_show.html.twig', [
            'employee' => $employee,
            'project' => $project,
            'tasks' => $this->findProjectTasks($entityManager, $project),
            'task_status_options' => self::TASK_STATUS_OPTIONS,
        ]);
    }

    #[Route('/tasks/create', name: 'task_create', methods: ['POST'])]
    public function createTask(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $entityManager,
        NotificationService $notifications,
    ): Response {
        $employee = $this->getEmployeeUser();
        $task = (new Task())
            ->addAssignee($employee)
            ->setCreatedBy($employee)
            ->setStatus(Task::STATUS_TODO)
            ->setEstimatedMinutes(0);
        $form = $this->createForm(EmployeeTaskFormType::class, $task, [
            'action' => $this->generateUrl('employee_task_create'),
            'projects' => $this->getEmployeeProjectChoices($employee),
            'employees' => $this->getActiveEmployeeChoices($users),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $project = $task->getProject();
            if (!$project || !$employee->getProjects()->contains($project)) {
                $this->addFlash('error', 'Choose a project you have access to.');

                return $this->redirect($this->generateUrl('employee_dashboard').'?tab=tasks');
            }

            $assigneesAreValid = !$task->getAssignees()->isEmpty();
            foreach ($task->getAssignees() as $assignee) {
                $assigneesAreValid = $assigneesAreValid && $assignee->getRole() === User::ROLE_EMPLOYEE && $assignee->isActive();
            }
            if (!$assigneesAreValid) {
                $this->addFlash('error', 'Choose an active employee for the task.');

                return $this->redirect($this->generateUrl('employee_dashboard').'?tab=tasks');
            }

            $this->grantProjectAccessForAssignee($task);
            $entityManager->persist($task);
            $entityManager->flush();
            $notifications->notifyEmployeeCreatedTask($task, $employee);
            $entityManager->flush();
            $this->addFlash('success', 'Task created.');

            return $this->redirect($this->generateUrl('employee_dashboard').'?tab=tasks');
        }

        $this->addFlash('error', 'Please check the task form.');

        return $this->redirect($this->generateUrl('employee_dashboard').'?tab=tasks');
    }

    #[Route('/tasks/{id}/comments', name: 'task_comment', methods: ['POST'])]
    public function addTaskComment(
        Task $task,
        Request $request,
        EntityManagerInterface $entityManager,
        NotificationService $notifications,
    ): Response {
        $employee = $this->getEmployeeUser();
        $this->denyUnlessTaskVisible($task, $employee);
        $this->guardCsrf($request, 'comment_'.$task->getId());

        $body = trim((string) $request->request->get('body'));
        if ($body === '') {
            $this->addFlash('error', 'Write a comment before submitting.');

            return $this->redirectToRoute('employee_task_show', ['id' => $task->getId()]);
        }

        $comment = (new TaskComment())
            ->setTask($task)
            ->setAuthor($employee)
            ->setBody($body);

        $entityManager->persist($comment);
        $notifications->notifyTaskCommented($task, $employee);
        $entityManager->flush();
        $this->addFlash('success', 'Comment added.');

        return $this->redirectToRoute('employee_task_show', ['id' => $task->getId()]);
    }

    #[Route('/task-comments/{id}/edit', name: 'task_comment_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editTaskComment(TaskComment $comment, Request $request, EntityManagerInterface $entityManager): Response
    {
        $employee = $this->getEmployeeUser();
        $task = $this->getCommentTask($comment);
        $this->denyUnlessTaskVisible($task, $employee);
        $this->denyUnlessCommentOwner($comment, $employee);
        $this->guardCsrf($request, 'comment_edit_'.$comment->getId());

        $body = trim((string) $request->request->get('body'));
        if ($body === '') {
            $this->addFlash('error', 'Comment cannot be empty.');

            return $this->redirectToRoute('employee_task_show', ['id' => $task->getId()]);
        }

        $comment->setBody($body);
        $entityManager->flush();
        $this->addFlash('success', 'Comment updated.');

        return $this->redirectToRoute('employee_task_show', ['id' => $task->getId()]);
    }

    #[Route('/task-comments/{id}/delete', name: 'task_comment_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteTaskComment(TaskComment $comment, Request $request, EntityManagerInterface $entityManager): Response
    {
        $employee = $this->getEmployeeUser();
        $task = $this->getCommentTask($comment);
        $this->denyUnlessTaskVisible($task, $employee);
        $this->denyUnlessCommentOwner($comment, $employee);
        $this->guardCsrf($request, 'comment_delete_'.$comment->getId());

        $entityManager->remove($comment);
        $entityManager->flush();
        $this->addFlash('success', 'Comment deleted.');

        return $this->redirectToRoute('employee_task_show', ['id' => $task->getId()]);
    }

    #[Route('/check-in', name: 'check_in', methods: ['POST'])]
    public function checkIn(Request $request, AttendanceEntryRepository $attendanceEntries, EntityManagerInterface $entityManager): Response
    {
        $this->guardCsrf($request, 'attendance');
        $employee = $this->getEmployeeUser();

        if (!$attendanceEntries->findOpenForUser($employee)) {
            $entry = (new AttendanceEntry())->setEmployee($employee);
            $entityManager->persist($entry);
            $entityManager->flush();
            $this->addFlash('success', 'Checked in.');
        }

        return $this->redirectToRoute('employee_dashboard');
    }

    #[Route('/check-out', name: 'check_out', methods: ['POST'])]
    public function checkOut(
        Request $request,
        AttendanceEntryRepository $attendanceEntries,
        TaskTimeEntryRepository $taskTimeEntries,
        EntityManagerInterface $entityManager,
        TaskLifecycleService $lifecycle,
    ): Response {
        $this->guardCsrf($request, 'attendance');
        $employee = $this->getEmployeeUser();
        $entry = $attendanceEntries->findOpenForUser($employee);

        if ($entry) {
            $now = new \DateTimeImmutable();
            $this->endActiveBreak($entry, $now);
            $this->pauseOpenTask($taskTimeEntries, $employee, $now);
            $entry->setCheckOutAt($now);
            $entityManager->flush();
            $this->addFlash('success', 'Checked out.');
        }

        return $this->redirectToRoute('employee_dashboard');
    }

    #[Route('/break/start', name: 'break_start', methods: ['POST'])]
    public function startBreak(
        Request $request,
        AttendanceEntryRepository $attendanceEntries,
        TaskTimeEntryRepository $taskTimeEntries,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->guardCsrf($request, 'attendance');
        $employee = $this->getEmployeeUser();
        $entry = $attendanceEntries->findOpenForUser($employee);

        if (!$entry) {
            $this->addFlash('error', 'Check in before starting a break.');

            return $this->redirectToRoute('employee_dashboard');
        }

        if (!$this->findActiveBreak($entry)) {
            $now = new \DateTimeImmutable();
            $this->pauseOpenTask($taskTimeEntries, $employee, $now);
            $break = (new BreakEntry())
                ->setEmployee($employee)
                ->setStartedAt($now);
            $entry->addBreak($break);
            $entityManager->persist($break);
            $entityManager->flush();
            $this->addFlash('success', 'Break started.');
        }

        return $this->redirectToRoute('employee_dashboard');
    }

    #[Route('/break/resume', name: 'break_resume', methods: ['POST'])]
    public function resumeWork(Request $request, AttendanceEntryRepository $attendanceEntries, EntityManagerInterface $entityManager): Response
    {
        $this->guardCsrf($request, 'attendance');
        $entry = $attendanceEntries->findOpenForUser($this->getEmployeeUser());

        if ($entry) {
            $this->endActiveBreak($entry, new \DateTimeImmutable());
            $entityManager->flush();
            $this->addFlash('success', 'Work resumed.');
        }

        return $this->redirectToRoute('employee_dashboard');
    }

    #[Route('/tasks/{id}/start', name: 'task_start', methods: ['POST'])]
    public function startTask(
        Task $task,
        Request $request,
        AttendanceEntryRepository $attendanceEntries,
        TaskTimeEntryRepository $taskTimeEntries,
        EntityManagerInterface $entityManager,
        TaskLifecycleService $lifecycle,
    ): Response {
        $this->guardCsrf($request, 'task_'.$task->getId());
        $employee = $this->getEmployeeUser();
        $this->denyUnlessAssigned($task, $employee);
        $openAttendance = $attendanceEntries->findOpenForUser($employee);

        if (!$openAttendance) {
            $this->addFlash('error', 'Check in before starting task time.');

            return $this->redirectToRoute('employee_dashboard');
        }

        if ($this->findActiveBreak($openAttendance)) {
            $this->addFlash('error', 'Resume work before starting a task.');

            return $this->redirectToRoute('employee_dashboard');
        }

        $now = new \DateTimeImmutable();
        $this->pauseOpenTask($taskTimeEntries, $employee, $now);

        $timeEntry = (new TaskTimeEntry())
            ->setEmployee($employee)
            ->setTask($task)
            ->setStartedAt($now);
        if ($next = $lifecycle->transition($task, Task::STATUS_IN_PROGRESS, $employee)) { $entityManager->persist($next); }
        $entityManager->persist($timeEntry);
        $entityManager->flush();
        $this->addFlash('success', 'Task timer started.');

        return $this->redirectToRoute('employee_dashboard');
    }

    #[Route('/tasks/{id}/pause', name: 'task_pause', methods: ['POST'])]
    public function pauseTask(
        Task $task,
        Request $request,
        TaskTimeEntryRepository $taskTimeEntries,
        EntityManagerInterface $entityManager,
        TaskLifecycleService $lifecycle,
    ): Response {
        $this->guardCsrf($request, 'task_'.$task->getId());
        $employee = $this->getEmployeeUser();
        $this->denyUnlessAssigned($task, $employee);

        $entry = $taskTimeEntries->findOpenForUserAndTask($employee, $task);
        if ($entry) {
            $entry->setEndedAt(new \DateTimeImmutable());
            if ($next = $lifecycle->transition($task, Task::STATUS_PAUSED, $employee)) { $entityManager->persist($next); }
            $entityManager->flush();
            $this->addFlash('success', 'Task timer paused.');
        }

        return $this->redirectToRoute('employee_dashboard');
    }

    #[Route('/tasks/{id}/status', name: 'task_status', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function updateTaskStatus(
        Task $task,
        Request $request,
        TaskTimeEntryRepository $taskTimeEntries,
        EntityManagerInterface $entityManager,
        NotificationService $notifications,
        TaskLifecycleService $lifecycle,
    ): Response {
        $this->guardCsrf($request, 'task_status_'.$task->getId());
        $employee = $this->getEmployeeUser();
        $this->denyUnlessAssigned($task, $employee);

        $status = (string) $request->request->get('status');
        if (!array_key_exists($status, self::TASK_STATUS_OPTIONS)) {
            $this->addFlash('error', 'Choose a valid task status.');

            return $this->redirectAfterTaskStatusUpdate($task, $request);
        }

        $previousStatus = $task->getStatus();
        if ($previousStatus === $status) {
            return $this->redirectAfterTaskStatusUpdate($task, $request);
        }

        if ($status !== Task::STATUS_IN_PROGRESS) {
            $entry = $taskTimeEntries->findOpenForUserAndTask($employee, $task);
            if ($entry) {
                $entry->setEndedAt(new \DateTimeImmutable());
            }
        }

        if ($next = $lifecycle->transition($task, $status, $employee)) { $entityManager->persist($next); }
        $notifications->notifyTaskStatusChanged($task, $employee, $previousStatus);
        $entityManager->flush();
        $this->addFlash('success', 'Task status updated.');

        return $this->redirectAfterTaskStatusUpdate($task, $request);
    }

    #[Route('/tasks/{id}/complete', name: 'task_complete', methods: ['POST'])]
    public function completeTask(
        Task $task,
        Request $request,
        TaskTimeEntryRepository $taskTimeEntries,
        EntityManagerInterface $entityManager,
        NotificationService $notifications,
        TaskLifecycleService $lifecycle,
    ): Response {
        $this->guardCsrf($request, 'task_'.$task->getId());
        $employee = $this->getEmployeeUser();
        $this->denyUnlessAssigned($task, $employee);
        $previousStatus = $task->getStatus();

        $entry = $taskTimeEntries->findOpenForUserAndTask($employee, $task);
        if ($entry) {
            $entry->setEndedAt(new \DateTimeImmutable());
        }

        if ($next = $lifecycle->transition($task, Task::STATUS_COMPLETED, $employee)) { $entityManager->persist($next); }
        $notifications->notifyTaskStatusChanged($task, $employee, $previousStatus);
        $entityManager->flush();
        $this->addFlash('success', 'Task completed.');

        return $this->redirectToRoute('employee_dashboard');
    }

    private function getEmployeeUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function findActiveBreak(AttendanceEntry $attendance): ?BreakEntry
    {
        foreach ($attendance->getBreaks() as $break) {
            if (!$break->getEndedAt()) {
                return $break;
            }
        }

        return null;
    }

    private function endActiveBreak(AttendanceEntry $attendance, \DateTimeImmutable $endedAt): void
    {
        $activeBreak = $this->findActiveBreak($attendance);
        if ($activeBreak) {
            $activeBreak->setEndedAt($endedAt);
        }
    }

    private function pauseOpenTask(TaskTimeEntryRepository $taskTimeEntries, User $employee, \DateTimeImmutable $endedAt): void
    {
        $openTask = $taskTimeEntries->findOpenForUser($employee);
        if ($openTask) {
            $openTask->setEndedAt($endedAt);
            $openTask->getTask()?->setStatus(Task::STATUS_PAUSED);
        }
    }

    /**
     * @return list<Task>
     */
    private function findVisibleTasks(EntityManagerInterface $entityManager, User $employee): array
    {
        return $entityManager->getRepository(Task::class)
            ->createQueryBuilder('t')
            ->leftJoin('t.project', 'p')
            ->addSelect('p')
            ->leftJoin('t.assignees', 'assignee')
            ->addSelect('assignee')
            ->leftJoin('t.createdBy', 'creator')
            ->addSelect('creator')
            ->andWhere('assignee = :employee OR t.createdBy = :employee')
            ->setParameter('employee', $employee)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Task>
     */
    private function findProjectTasks(EntityManagerInterface $entityManager, Project $project): array
    {
        return $entityManager->getRepository(Task::class)
            ->createQueryBuilder('t')
            ->leftJoin('t.assignees', 'assignee')
            ->addSelect('assignee')
            ->leftJoin('t.createdBy', 'creator')
            ->addSelect('creator')
            ->andWhere('t.project = :project')
            ->setParameter('project', $project)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Project>
     */
    private function getEmployeeProjectChoices(User $employee): array
    {
        return array_values($employee->getProjects()->toArray());
    }

    /**
     * @return list<User>
     */
    private function getActiveEmployeeChoices(UserRepository $users): array
    {
        return $users->findBy([
            'role' => User::ROLE_EMPLOYEE,
            'isActive' => true,
        ], ['fullName' => 'ASC']);
    }

    private function grantProjectAccessForAssignee(Task $task): void
    {
        $project = $task->getProject();
        foreach ($task->getAssignees() as $assignee) {
            if ($project && !$project->getEmployees()->contains($assignee)) {
                $project->addEmployee($assignee);
            }
        }
    }

    private function denyUnlessAssigned(Task $task, User $employee): void
    {
        if (!$task->isAssignedTo($employee) || !$task->getProject()?->getEmployees()->contains($employee)) {
            throw $this->createAccessDeniedException();
        }
    }

    private function denyUnlessProjectMember(Project $project, User $employee): void
    {
        if (!$project->getEmployees()->contains($employee)) {
            throw $this->createAccessDeniedException();
        }
    }

    private function canUpdateAssignedTask(Task $task, User $employee): bool
    {
        return $task->isAssignedTo($employee)
            && $task->getProject()?->getEmployees()->contains($employee);
    }

    private function denyUnlessTaskVisible(Task $task, User $employee): void
    {
        $isAssignee = $task->isAssignedTo($employee);
        $isCreator = $task->getCreatedBy()?->getId() === $employee->getId();

        if (!$isAssignee && !$isCreator) {
            throw $this->createAccessDeniedException();
        }
    }

    private function redirectAfterTaskStatusUpdate(Task $task, Request $request): Response
    {
        return match ((string) $request->request->get('return_to')) {
            'task' => $this->redirectToRoute('employee_task_show', ['id' => $task->getId()]),
            'project' => $this->redirectToRoute('employee_project_show', ['id' => $task->getProject()?->getId()]),
            default => $this->redirect($this->generateUrl('employee_dashboard').'?tab=tasks'),
        };
    }

    private function denyUnlessCommentOwner(TaskComment $comment, User $employee): void
    {
        if ($comment->getAuthor()?->getId() !== $employee->getId()) {
            throw $this->createAccessDeniedException();
        }
    }

    private function getCommentTask(TaskComment $comment): Task
    {
        $task = $comment->getTask();
        if (!$task) {
            throw $this->createNotFoundException('Task comment is not attached to a task.');
        }

        return $task;
    }

    private function guardCsrf(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function storeProfileImage(User $employee, UploadedFile $imageFile, string $projectDir): void
    {
        $uploadDir = $this->getProfileUploadDir($projectDir);
        try {
            $extension = (string) ($imageFile->guessExtension() ?: $imageFile->getClientOriginalExtension() ?: 'bin');
        } catch (\LogicException) {
            $extension = (string) ($imageFile->getClientOriginalExtension() ?: 'bin');
        }

        $extension = strtolower($extension);
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin';
        $filename = sprintf('employee-%d-%s.%s', $employee->getId(), bin2hex(random_bytes(6)), $extension);
        $previousImage = $employee->getProfileImage();

        $imageFile->move($uploadDir, $filename);
        $employee->setProfileImage($filename);

        if ($previousImage) {
            $this->removeProfileImageFile($uploadDir, $previousImage);
        }
    }

    private function storeCroppedProfileImage(User $employee, string $dataUrl, string $projectDir): void
    {
        if (!preg_match('/^data:image\/(?P<type>png|jpeg|webp);base64,(?P<data>.+)$/', $dataUrl, $matches)) {
            return;
        }

        $binary = base64_decode($matches['data'], true);
        if ($binary === false || strlen($binary) > 5 * 1024 * 1024 || !getimagesizefromstring($binary)) {
            return;
        }

        $uploadDir = $this->getProfileUploadDir($projectDir);
        $extension = $matches['type'] === 'jpeg' ? 'jpg' : $matches['type'];
        $filename = sprintf('employee-%d-%s.%s', $employee->getId(), bin2hex(random_bytes(6)), $extension);
        $previousImage = $employee->getProfileImage();

        file_put_contents($uploadDir.'/'.$filename, $binary);
        $employee->setProfileImage($filename);

        if ($previousImage) {
            $this->removeProfileImageFile($uploadDir, $previousImage);
        }
    }

    private function deleteProfileImage(User $employee, string $projectDir): void
    {
        $previousImage = $employee->getProfileImage();
        if (!$previousImage) {
            return;
        }

        $employee->setProfileImage(null);
        $this->removeProfileImageFile($this->getProfileUploadDir($projectDir), $previousImage);
    }

    private function getProfileUploadDir(string $projectDir): string
    {
        $uploadDir = $projectDir.'/public/uploads/profiles';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        return $uploadDir;
    }

    private function removeProfileImageFile(string $uploadDir, string $filename): void
    {
        $path = $uploadDir.'/'.$filename;
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function sanitizeProfileRichText(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $clean = preg_replace('#<(script|style)[^>]*>.*?</\1>#is', '', $html) ?? '';
        $clean = strip_tags($clean, '<p><br><strong><b><em><i><u><ul><ol><li>');
        $clean = preg_replace('/<([a-z][a-z0-9]*)\b[^>]*>/i', '<$1>', $clean) ?? '';
        $clean = preg_replace('/\s+/', ' ', $clean) ?? '';

        return trim(strip_tags($clean)) === '' ? null : trim($clean);
    }

    /**
     * @return array{start: \DateTimeImmutable, end: \DateTimeImmutable, selected_date: \DateTimeImmutable, days: list<array<string, mixed>>}
     */
    private function buildWeekTimeline(
        AttendanceEntryRepository $attendanceEntries,
        User $employee,
        \DateTimeImmutable $selectedDate,
    ): array
    {
        $today = new \DateTimeImmutable('today');
        $weekStart = $selectedDate->modify(sprintf('-%d days', (int) $selectedDate->format('w')))->setTime(0, 0);
        $weekEnd = $weekStart->modify('+6 days');
        $entries = $attendanceEntries->findForUserBetween($employee, $weekStart, $weekStart->modify('+7 days'));

        $entriesByDate = [];
        foreach ($entries as $entry) {
            $key = $entry->getCheckInAt()->format('Y-m-d');
            $entriesByDate[$key][] = $entry;
        }

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->modify(sprintf('+%d days', $i));
            $key = $date->format('Y-m-d');
            $dayEntries = $entriesByDate[$key] ?? [];
            $workedSeconds = 0;
            $breakSeconds = 0;
            $firstCheckIn = null;
            $lastCheckOut = null;
            $hasOpenEntry = false;

            foreach ($dayEntries as $entry) {
                $workedSeconds += $entry->getWorkedSeconds();
                $breakSeconds += $entry->getBreakSeconds();
                $firstCheckIn = $firstCheckIn
                    ? min($firstCheckIn, $entry->getCheckInAt())
                    : $entry->getCheckInAt();

                if ($entry->getCheckOutAt()) {
                    $lastCheckOut = $lastCheckOut
                        ? max($lastCheckOut, $entry->getCheckOutAt())
                        : $entry->getCheckOutAt();
                } else {
                    $hasOpenEntry = true;
                }
            }

            $isWeekend = in_array((int) $date->format('w'), [0, 6], true);
            $days[] = [
                'date' => $date,
                'key' => $key,
                'is_today' => $key === $today->format('Y-m-d'),
                'is_weekend' => $isWeekend,
                'entries' => $dayEntries,
                'worked_seconds' => $workedSeconds,
                'break_seconds' => $breakSeconds,
                'first_check_in' => $firstCheckIn,
                'last_check_out' => $lastCheckOut,
                'has_open_entry' => $hasOpenEntry,
                'status' => $hasOpenEntry ? 'Active' : ($dayEntries ? 'Completed' : ($isWeekend ? 'Weekend' : 'No entry')),
            ];
        }

        return [
            'start' => $weekStart,
            'end' => $weekEnd,
            'selected_date' => $selectedDate,
            'days' => $days,
        ];
    }

    private function readTimelineDate(mixed $value): \DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return new \DateTimeImmutable('today');
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));

        return $date instanceof \DateTimeImmutable ? $date : new \DateTimeImmutable('today');
    }
}
