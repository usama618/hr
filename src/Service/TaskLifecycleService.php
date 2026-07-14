<?php
namespace App\Service;

use App\Entity\Task;
use App\Entity\TaskStatusHistory;
use App\Entity\User;

final class TaskLifecycleService
{
    public function __construct(private readonly TaskActivityService $activity, private readonly TaskRecurrenceService $recurrence) {}

    public function transition(Task $task, string $status, ?User $actor): ?Task
    {
        $previous = $task->getStatus();
        if ($previous === $status) { return null; }
        $task->setStatus($status);
        $task->addStatusHistory((new TaskStatusHistory())->setActor($actor)->setPreviousStatus($previous)->setNewStatus($task->getStatus()));
        $this->activity->record($task, $actor, 'status_changed', 'Status changed from '.str_replace('_', ' ', $previous).' to '.str_replace('_', ' ', $task->getStatus()).'.', ['from' => $previous, 'to' => $task->getStatus()]);
        return $this->recurrence->createNextOccurrence($task);
    }
}
