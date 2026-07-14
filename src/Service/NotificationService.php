<?php

namespace App\Service;

use App\Entity\Notification;
use App\Entity\Task;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $users,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /** @param iterable<User> $previousAssignees */
    public function notifyTaskAssigned(Task $task, ?User $actor = null, iterable $previousAssignees = []): void
    {
        $previousIds = [];
        foreach ($previousAssignees as $assignee) { if ($assignee->getId()) { $previousIds[$assignee->getId()] = true; } }
        $recipients = array_values(array_filter($task->getAssignees()->toArray(), static fn (User $assignee): bool => !$assignee->getId() || !isset($previousIds[$assignee->getId()])));

        $this->notifyRecipients(
            $recipients,
            $actor,
            $task,
            'task_assigned',
            'Task assigned to you',
            sprintf('%s was assigned to you.', $task->getTitle()),
        );
    }

    public function notifyEmployeeCreatedTask(Task $task, User $actor): void
    {
        $this->notifyRecipients(
            $this->users->findActiveSuperAdmins(),
            $actor,
            $task,
            'task_created',
            'New task created',
            sprintf('%s created the task %s.', $actor->getFullName(), $task->getTitle()),
        );

        $this->notifyTaskAssigned($task, $actor);
    }

    public function notifyTaskCommented(Task $task, User $actor): void
    {
        $recipients = array_merge($task->getAssignees()->toArray(), [$task->getCreatedBy()]);

        if ($actor->getRole() === User::ROLE_EMPLOYEE) {
            $recipients = array_merge($recipients, $this->users->findActiveSuperAdmins());
        }

        $this->notifyRecipients(
            $recipients,
            $actor,
            $task,
            'task_comment',
            'New task comment',
            sprintf('%s commented on %s.', $actor->getFullName(), $task->getTitle()),
        );
    }

    public function notifyTaskStatusChanged(Task $task, User $actor, string $previousStatus): void
    {
        if ($previousStatus === $task->getStatus()) {
            return;
        }

        $recipients = array_merge($task->getAssignees()->toArray(), [$task->getCreatedBy()]);

        if ($actor->getRole() === User::ROLE_EMPLOYEE || $task->getStatus() === Task::STATUS_COMPLETED) {
            $recipients = array_merge($recipients, $this->users->findActiveSuperAdmins());
        }

        $this->notifyRecipients(
            $recipients,
            $actor,
            $task,
            'task_status',
            'Task status changed',
            sprintf('%s changed %s to %s.', $actor->getFullName(), $task->getTitle(), $this->formatStatus($task->getStatus())),
        );
    }

    /**
     * @param array<int, User|null> $recipients
     */
    private function notifyRecipients(
        array $recipients,
        ?User $actor,
        Task $task,
        string $type,
        string $title,
        string $body,
    ): void {
        $seen = [];

        foreach ($recipients as $recipient) {
            if (!$recipient || !$recipient->getId() || !$recipient->isActive()) {
                continue;
            }

            if ($actor && $actor->getId() === $recipient->getId()) {
                continue;
            }

            if (isset($seen[$recipient->getId()])) {
                continue;
            }

            $seen[$recipient->getId()] = true;
            $this->createNotification($recipient, $actor, $task, $type, $title, $body);
        }
    }

    private function createNotification(User $recipient, ?User $actor, Task $task, string $type, string $title, string $body): void
    {
        $notification = (new Notification())
            ->setRecipient($recipient)
            ->setActor($actor)
            ->setTask($task)
            ->setType($type)
            ->setTitle($title)
            ->setBody($body)
            ->setUrl($this->getTaskUrl($recipient, $task));

        $this->entityManager->persist($notification);
    }

    private function getTaskUrl(User $recipient, Task $task): string
    {
        $route = $recipient->getRole() === User::ROLE_SUPER_ADMIN ? 'admin_task_show' : 'employee_task_show';

        return $this->urlGenerator->generate($route, ['id' => $task->getId()]);
    }

    private function formatStatus(string $status): string
    {
        return ucfirst(str_replace('_', ' ', $status));
    }
}
