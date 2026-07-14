<?php
namespace App\Service;

use App\Entity\Task;

final class TaskRecurrenceService
{
    public function createNextOccurrence(Task $task): ?Task
    {
        if ($task->getStatus() !== Task::STATUS_COMPLETED || !$task->getRecurrence() || $task->getNextOccurrence()) { return null; }
        $modifier = match ($task->getRecurrence()) {
            Task::RECURRENCE_DAILY => '+1 day',
            Task::RECURRENCE_WEEKLY => '+1 week',
            Task::RECURRENCE_MONTHLY => '+1 month',
            default => null,
        };
        if (!$modifier) { return null; }
        $next = (new Task())
            ->setProject($task->getProject())->setParent($task->getParent())->setTitle($task->getTitle())
            ->setDescription($task->getDescription())->setPriority($task->getPriority())->setStatus(Task::STATUS_TODO)
            ->setEstimatedMinutes($task->getEstimatedMinutes())->setTags($task->getTags())
            ->setRecurrence($task->getRecurrence())->setBillingType($task->getBillingType())
            ->setManagerNote($task->getManagerNote())->setCreatedBy($task->getCreatedBy());
        if ($task->getStartDate()) { $next->setStartDate($task->getStartDate()->modify($modifier)); }
        if ($task->getDueDate()) { $next->setDueDate($task->getDueDate()->modify($modifier)); }
        if ($task->getReminderAt()) { $next->setReminderAt($task->getReminderAt()->modify($modifier)); }
        foreach ($task->getAssignees() as $assignee) { $next->addAssignee($assignee); }
        $next->setSourceOccurrence($task);
        return $next;
    }
}
