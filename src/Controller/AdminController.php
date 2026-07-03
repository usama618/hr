<?php

namespace App\Controller;

use App\Entity\AttendanceEntry;
use App\Entity\LeaveRequest;
use App\Entity\Project;
use App\Entity\Task;
use App\Entity\TaskComment;
use App\Entity\TaskTimeEntry;
use App\Entity\User;
use App\Form\ProjectFormType;
use App\Form\TaskFormType;
use App\Form\UserFormType;
use App\Repository\AttendanceEntryRepository;
use App\Repository\LeaveRequestRepository;
use App\Repository\TaskTimeEntryRepository;
use App\Repository\UserRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin', name: 'admin_')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('', name: 'dashboard')]
    public function dashboard(EntityManagerInterface $entityManager): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'employee_count' => $entityManager->getRepository(User::class)->count(['role' => User::ROLE_EMPLOYEE]),
            'project_count' => $entityManager->getRepository(Project::class)->count([]),
            'task_count' => $entityManager->getRepository(Task::class)->count([]),
            'active_attendance_count' => $entityManager->getRepository(AttendanceEntry::class)->count(['checkOutAt' => null]),
        ]);
    }

    #[Route('/employees', name: 'employees')]
    public function employees(UserRepository $users): Response
    {
        return $this->render('admin/employees.html.twig', [
            'employees' => $users->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/employees/new', name: 'employee_new')]
    public function newEmployee(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $employee = new User();
        $form = $this->createForm(UserFormType::class, $employee, ['password_required' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $employee->setPassword($passwordHasher->hashPassword($employee, (string) $form->get('plainPassword')->getData()));
            $entityManager->persist($employee);
            $entityManager->flush();

            $this->addFlash('success', 'User added.');

            return $this->redirectToRoute('admin_employees');
        }

        return $this->render('admin/user_form.html.twig', [
            'form' => $form,
            'title' => 'Add User',
        ]);
    }

    #[Route('/employees/{id}/edit', name: 'employee_edit')]
    public function editEmployee(
        User $employee,
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $form = $this->createForm(UserFormType::class, $employee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if (is_string($plainPassword) && $plainPassword !== '') {
                $employee->setPassword($passwordHasher->hashPassword($employee, $plainPassword));
            }

            $entityManager->flush();
            $this->addFlash('success', 'User updated.');

            return $this->redirectToRoute('admin_employees');
        }

        return $this->render('admin/user_form.html.twig', [
            'form' => $form,
            'title' => 'Edit User',
        ]);
    }

    #[Route('/employees/{id}/activate', name: 'employee_activate', methods: ['POST'])]
    public function activateEmployee(User $employee, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->guardCsrf($request, 'employee_activate_'.$employee->getId());
        $employee->setIsActive(true);
        $entityManager->flush();
        $this->addFlash('success', 'User activated.');

        return $this->redirectToRoute('admin_employees');
    }

    #[Route('/employees/{id}/deactivate', name: 'employee_deactivate', methods: ['POST'])]
    public function deactivateEmployee(
        User $employee,
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $users,
    ): Response {
        $this->guardCsrf($request, 'employee_deactivate_'.$employee->getId());

        if ($this->isCurrentUser($employee)) {
            $this->addFlash('error', 'You cannot deactivate your own account.');

            return $this->redirectToRoute('admin_employees');
        }

        if ($this->isLastActiveSuperAdmin($employee, $users)) {
            $this->addFlash('error', 'You cannot deactivate the last active super admin.');

            return $this->redirectToRoute('admin_employees');
        }

        $employee->setIsActive(false);
        $entityManager->flush();
        $this->addFlash('success', 'User deactivated.');

        return $this->redirectToRoute('admin_employees');
    }

    #[Route('/employees/{id}/delete', name: 'employee_delete', methods: ['POST'])]
    public function deleteEmployee(
        User $employee,
        Request $request,
        EntityManagerInterface $entityManager,
        UserRepository $users,
        KernelInterface $kernel,
    ): Response {
        $this->guardCsrf($request, 'employee_delete_'.$employee->getId());

        if ($this->isCurrentUser($employee)) {
            $this->addFlash('error', 'You cannot remove your own account.');

            return $this->redirectToRoute('admin_employees');
        }

        if ($this->isLastActiveSuperAdmin($employee, $users)) {
            $this->addFlash('error', 'You cannot remove the last active super admin.');

            return $this->redirectToRoute('admin_employees');
        }

        $this->deleteProfileImageFile($employee, $kernel->getProjectDir());
        $entityManager->remove($employee);
        $entityManager->flush();
        $this->addFlash('success', 'User removed.');

        return $this->redirectToRoute('admin_employees');
    }

    #[Route('/projects', name: 'projects')]
    public function projects(EntityManagerInterface $entityManager): Response
    {
        return $this->render('admin/projects.html.twig', [
            'projects' => $entityManager->getRepository(Project::class)->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/projects/new', name: 'project_new')]
    public function newProject(Request $request, EntityManagerInterface $entityManager): Response
    {
        $project = new Project();
        $form = $this->createForm(ProjectFormType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($project);
            $entityManager->flush();
            $this->addFlash('success', 'Project added.');

            return $this->redirectToRoute('admin_projects');
        }

        return $this->render('admin/project_form.html.twig', [
            'form' => $form,
            'title' => 'Add Project',
        ]);
    }

    #[Route('/projects/{id}/edit', name: 'project_edit')]
    public function editProject(Project $project, Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ProjectFormType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Project updated.');

            return $this->redirectToRoute('admin_projects');
        }

        return $this->render('admin/project_form.html.twig', [
            'form' => $form,
            'title' => 'Edit Project',
        ]);
    }

    #[Route('/projects/{id}/delete', name: 'project_delete', methods: ['POST'])]
    public function deleteProject(Project $project, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->guardCsrf($request, 'project_delete_'.$project->getId());

        $entityManager->remove($project);
        $entityManager->flush();
        $this->addFlash('success', 'Project removed.');

        return $this->redirectToRoute('admin_projects');
    }

    #[Route('/tasks', name: 'tasks')]
    public function tasks(EntityManagerInterface $entityManager): Response
    {
        return $this->render('admin/tasks.html.twig', [
            'tasks' => $entityManager->getRepository(Task::class)->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/tasks/{id}', name: 'task_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showTask(Task $task): Response
    {
        return $this->render('task/show.html.twig', [
            'task' => $task,
            'back_path' => $this->generateUrl('admin_tasks'),
            'back_label' => 'Back to Tasks',
            'edit_path' => $this->generateUrl('admin_task_edit', ['id' => $task->getId()]),
            'comment_add_path' => $this->generateUrl('admin_task_comment', ['id' => $task->getId()]),
            'comment_edit_route' => 'admin_task_comment_edit',
            'comment_delete_route' => 'admin_task_comment_delete',
            'can_administer_comments' => true,
        ]);
    }

    #[Route('/tasks/new', name: 'task_new')]
    public function newTask(
        Request $request,
        EntityManagerInterface $entityManager,
        NotificationService $notifications,
    ): Response {
        $task = new Task();
        $form = $this->createForm(TaskFormType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->grantProjectAccessForAssignee($task);
            $entityManager->persist($task);
            $entityManager->flush();
            $notifications->notifyTaskAssigned($task, $this->getAuthenticatedUser());
            $entityManager->flush();
            $this->addFlash('success', 'Task added.');

            return $this->redirectToRoute('admin_tasks');
        }

        return $this->render('admin/task_form.html.twig', [
            'form' => $form,
            'title' => 'Add Task',
        ]);
    }

    #[Route('/tasks/{id}/edit', name: 'task_edit')]
    public function editTask(
        Task $task,
        Request $request,
        EntityManagerInterface $entityManager,
        NotificationService $notifications,
    ): Response {
        $previousAssigneeId = $task->getAssignedTo()?->getId();
        $previousStatus = $task->getStatus();
        $form = $this->createForm(TaskFormType::class, $task);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $actor = $this->getAuthenticatedUser();
            $this->grantProjectAccessForAssignee($task);
            if ($task->getAssignedTo()?->getId() !== $previousAssigneeId) {
                $notifications->notifyTaskAssigned($task, $actor);
            }
            $notifications->notifyTaskStatusChanged($task, $actor, $previousStatus);
            $entityManager->flush();
            $this->addFlash('success', 'Task updated.');

            return $this->redirectToRoute('admin_tasks');
        }

        return $this->render('admin/task_form.html.twig', [
            'form' => $form,
            'title' => 'Edit Task',
        ]);
    }

    #[Route('/tasks/{id}/comments', name: 'task_comment', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function addTaskComment(
        Task $task,
        Request $request,
        EntityManagerInterface $entityManager,
        NotificationService $notifications,
    ): Response {
        $this->guardCsrf($request, 'comment_'.$task->getId());

        $body = trim((string) $request->request->get('body'));
        if ($body === '') {
            $this->addFlash('error', 'Write a comment before submitting.');

            return $this->redirectToRoute('admin_task_show', ['id' => $task->getId()]);
        }

        $comment = (new TaskComment())
            ->setTask($task)
            ->setAuthor($this->getAuthenticatedUser())
            ->setBody($body);

        $entityManager->persist($comment);
        $notifications->notifyTaskCommented($task, $this->getAuthenticatedUser());
        $entityManager->flush();
        $this->addFlash('success', 'Comment added.');

        return $this->redirectToRoute('admin_task_show', ['id' => $task->getId()]);
    }

    #[Route('/task-comments/{id}/edit', name: 'task_comment_edit', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function editTaskComment(TaskComment $comment, Request $request, EntityManagerInterface $entityManager): Response
    {
        $task = $this->getCommentTask($comment);
        $this->guardCsrf($request, 'comment_edit_'.$comment->getId());

        $body = trim((string) $request->request->get('body'));
        if ($body === '') {
            $this->addFlash('error', 'Comment cannot be empty.');

            return $this->redirectToRoute('admin_task_show', ['id' => $task->getId()]);
        }

        $comment->setBody($body);
        $entityManager->flush();
        $this->addFlash('success', 'Comment updated.');

        return $this->redirectToRoute('admin_task_show', ['id' => $task->getId()]);
    }

    #[Route('/task-comments/{id}/delete', name: 'task_comment_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteTaskComment(TaskComment $comment, Request $request, EntityManagerInterface $entityManager): Response
    {
        $task = $this->getCommentTask($comment);
        $this->guardCsrf($request, 'comment_delete_'.$comment->getId());

        $entityManager->remove($comment);
        $entityManager->flush();
        $this->addFlash('success', 'Comment deleted.');

        return $this->redirectToRoute('admin_task_show', ['id' => $task->getId()]);
    }

    #[Route('/tasks/{id}/delete', name: 'task_delete', methods: ['POST'])]
    public function deleteTask(Task $task, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->guardCsrf($request, 'task_delete_'.$task->getId());

        $entityManager->remove($task);
        $entityManager->flush();
        $this->addFlash('success', 'Task removed.');

        return $this->redirectToRoute('admin_tasks');
    }

    #[Route('/reports', name: 'reports')]
    public function reports(
        Request $request,
        AttendanceEntryRepository $attendanceEntries,
        LeaveRequestRepository $leaveRequests,
        TaskTimeEntryRepository $taskTimeEntries,
        UserRepository $users,
        EntityManagerInterface $entityManager,
    ): Response {
        $allTaskTimeEntries = $entityManager->getRepository(TaskTimeEntry::class)->findAll();
        $projectTotals = [];

        foreach ($allTaskTimeEntries as $entry) {
            if (!$entry instanceof TaskTimeEntry || !$entry->getTask()?->getProject()) {
                continue;
            }

            $projectName = $entry->getTask()->getProject()->getName();
            $projectTotals[$projectName] = ($projectTotals[$projectName] ?? 0) + $entry->getSeconds();
        }

        arsort($projectTotals);

        $defaultRangeEnd = new \DateTimeImmutable('today');
        $rangeEnd = $this->readReportDate($request->query->get('to'), $defaultRangeEnd);
        $rangeStart = $this->readReportDate($request->query->get('from'), $rangeEnd->modify('-13 days'));

        if ($rangeStart > $rangeEnd) {
            [$rangeStart, $rangeEnd] = [$rangeEnd, $rangeStart];
        }

        $rangeEndExclusive = $rangeEnd->modify('+1 day');
        $reportDate = $rangeEnd;
        $reportDateEnd = $reportDate->modify('+1 day');
        $employees = $users->findBy(['role' => User::ROLE_EMPLOYEE], ['fullName' => 'ASC']);
        $attendanceRangeEntries = $attendanceEntries->findCompanyEntriesBetween($rangeStart, $rangeEndExclusive);
        $dailyAttendanceEntries = array_values(array_filter(
            $attendanceRangeEntries,
            static fn (AttendanceEntry $entry): bool => $entry->getCheckInAt() >= $reportDate && $entry->getCheckInAt() < $reportDateEnd,
        ));

        return $this->render('admin/reports.html.twig', [
            'attendance_day_rows' => $this->buildAttendanceDayRows($attendanceRangeEntries),
            'attendance_timelines' => $this->buildDailyAttendanceTimelines($employees, $dailyAttendanceEntries, $reportDate),
            'attendance_range_start' => $rangeStart,
            'attendance_range_end' => $rangeEnd,
            'report_date' => $reportDate,
            'leave_requests' => $leaveRequests->findRecentCompanyRequests(),
            'task_time_entries' => $taskTimeEntries->findRecentCompanyEntries(),
            'project_totals' => $projectTotals,
        ]);
    }

    #[Route('/leave-requests/{id}/{status}', name: 'leave_status', requirements: ['status' => 'approved|rejected'], methods: ['POST'])]
    public function updateLeaveStatus(
        LeaveRequest $leaveRequest,
        string $status,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        if (!$this->isCsrfTokenValid('leave_'.$leaveRequest->getId().'_'.$status, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $leaveRequest
            ->setStatus($status)
            ->setReviewedAt(new \DateTimeImmutable());
        $entityManager->flush();
        $this->addFlash('success', 'Leave request updated.');

        return $this->redirectToRoute('admin_reports');
    }

    private function grantProjectAccessForAssignee(Task $task): void
    {
        $project = $task->getProject();
        $assignee = $task->getAssignedTo();

        if ($project && $assignee && !$project->getEmployees()->contains($assignee)) {
            $project->addEmployee($assignee);
        }
    }

    private function getAuthenticatedUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
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

    private function isCurrentUser(User $employee): bool
    {
        $currentUser = $this->getUser();

        return $currentUser instanceof User && $currentUser->getId() === $employee->getId();
    }

    private function isLastActiveSuperAdmin(User $employee, UserRepository $users): bool
    {
        return $employee->getRole() === User::ROLE_SUPER_ADMIN
            && $employee->isActive()
            && $users->count([
                'role' => User::ROLE_SUPER_ADMIN,
                'isActive' => true,
            ]) <= 1;
    }

    private function deleteProfileImageFile(User $employee, string $projectDir): void
    {
        $profileImage = $employee->getProfileImage();
        if (!$profileImage) {
            return;
        }

        $path = $projectDir.'/public/uploads/profiles/'.basename($profileImage);
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function readReportDate(mixed $value, \DateTimeImmutable $fallback): \DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return $fallback->setTime(0, 0);
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));

        return $date instanceof \DateTimeImmutable ? $date : $fallback->setTime(0, 0);
    }

    /**
     * @param list<AttendanceEntry> $entries
     *
     * @return list<array<string, mixed>>
     */
    private function buildAttendanceDayRows(array $entries): array
    {
        $rows = [];

        foreach ($entries as $entry) {
            $employee = $entry->getEmployee();
            if (!$employee || !$employee->getId()) {
                continue;
            }

            $dateKey = $entry->getCheckInAt()->format('Y-m-d');
            $rowKey = $dateKey.'-'.$employee->getId();

            if (!isset($rows[$rowKey])) {
                $rows[$rowKey] = [
                    'date' => new \DateTimeImmutable($dateKey),
                    'employee' => $employee,
                    'sessions' => [],
                    'worked_seconds' => 0,
                    'break_seconds' => 0,
                    'first_check_in' => null,
                    'last_check_out' => null,
                    'has_open_entry' => false,
                    'status' => 'Completed',
                ];
            }

            $checkOutAt = $entry->getCheckOutAt();
            $rows[$rowKey]['sessions'][] = [
                'check_in' => $entry->getCheckInAt(),
                'check_out' => $checkOutAt,
                'worked_seconds' => $entry->getWorkedSeconds(),
                'break_seconds' => $entry->getBreakSeconds(),
            ];
            $rows[$rowKey]['worked_seconds'] += $entry->getWorkedSeconds();
            $rows[$rowKey]['break_seconds'] += $entry->getBreakSeconds();
            $rows[$rowKey]['first_check_in'] = $rows[$rowKey]['first_check_in']
                ? min($rows[$rowKey]['first_check_in'], $entry->getCheckInAt())
                : $entry->getCheckInAt();

            if ($checkOutAt) {
                $rows[$rowKey]['last_check_out'] = $rows[$rowKey]['last_check_out']
                    ? max($rows[$rowKey]['last_check_out'], $checkOutAt)
                    : $checkOutAt;
            } else {
                $rows[$rowKey]['has_open_entry'] = true;
                $rows[$rowKey]['status'] = 'Active';
            }
        }

        $dayRows = array_values($rows);
        usort($dayRows, static function (array $a, array $b): int {
            $dateCompare = $b['date'] <=> $a['date'];
            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return strcasecmp($a['employee']->getFullName(), $b['employee']->getFullName());
        });

        return $dayRows;
    }

    /**
     * @param list<User> $employees
     * @param list<AttendanceEntry> $entries
     *
     * @return list<array<string, mixed>>
     */
    private function buildDailyAttendanceTimelines(array $employees, array $entries, \DateTimeImmutable $dayStart): array
    {
        $dayEnd = $dayStart->modify('+1 day');
        $entriesByEmployee = [];

        foreach ($entries as $entry) {
            $employee = $entry->getEmployee();
            if (!$employee || !$employee->getId()) {
                continue;
            }

            $entriesByEmployee[$employee->getId()][] = $entry;
        }

        $timelines = [];
        foreach ($employees as $employee) {
            $employeeEntries = $entriesByEmployee[$employee->getId()] ?? [];
            $events = [];
            $workedSeconds = 0;
            $breakSeconds = 0;
            $firstCheckIn = null;
            $lastCheckOut = null;
            $hasOpenEntry = false;

            foreach ($employeeEntries as $entry) {
                $workedSeconds += $entry->getWorkedSeconds();
                $breakSeconds += $entry->getBreakSeconds();
                $firstCheckIn = $firstCheckIn ? min($firstCheckIn, $entry->getCheckInAt()) : $entry->getCheckInAt();

                $events[] = $this->buildAttendanceTimelineEvent('check-in', 'Check in', $entry->getCheckInAt(), $dayStart, $dayEnd);

                foreach ($entry->getBreaks() as $break) {
                    $events[] = $this->buildAttendanceTimelineEvent('break-start', 'Break start', $break->getStartedAt(), $dayStart, $dayEnd);

                    if ($break->getEndedAt()) {
                        $events[] = $this->buildAttendanceTimelineEvent('break-end', 'Break end', $break->getEndedAt(), $dayStart, $dayEnd);
                    }
                }

                if ($entry->getCheckOutAt()) {
                    $lastCheckOut = $lastCheckOut ? max($lastCheckOut, $entry->getCheckOutAt()) : $entry->getCheckOutAt();
                    $events[] = $this->buildAttendanceTimelineEvent('check-out', 'Check out', $entry->getCheckOutAt(), $dayStart, $dayEnd);
                } else {
                    $hasOpenEntry = true;
                }
            }

            usort($events, static fn (array $a, array $b): int => $a['timestamp'] <=> $b['timestamp']);

            $timelines[] = [
                'employee' => $employee,
                'events' => $events,
                'worked_seconds' => $workedSeconds,
                'break_seconds' => $breakSeconds,
                'first_check_in' => $firstCheckIn,
                'last_check_out' => $lastCheckOut,
                'has_open_entry' => $hasOpenEntry,
                'sessions' => count($employeeEntries),
                'status' => $hasOpenEntry ? 'Active' : ($employeeEntries ? 'Completed' : 'No entry'),
            ];
        }

        return $timelines;
    }

    /**
     * @return array{type: string, label: string, time: \DateTimeImmutable, time_label: string, position: float, timestamp: int}
     */
    private function buildAttendanceTimelineEvent(
        string $type,
        string $label,
        \DateTimeImmutable $time,
        \DateTimeImmutable $dayStart,
        \DateTimeImmutable $dayEnd,
    ): array {
        $daySeconds = $dayEnd->getTimestamp() - $dayStart->getTimestamp();
        $elapsedSeconds = $time->getTimestamp() - $dayStart->getTimestamp();
        $position = $daySeconds > 0 ? ($elapsedSeconds / $daySeconds) * 100 : 0;

        return [
            'type' => $type,
            'label' => $label,
            'time' => $time,
            'time_label' => $time->format('H:i'),
            'position' => max(0.0, min(100.0, $position)),
            'timestamp' => $time->getTimestamp(),
        ];
    }
}
