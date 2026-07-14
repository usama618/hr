<?php
namespace App\Service;

use App\Entity\Task;
use App\Entity\TaskActivity;
use App\Entity\User;

final class TaskActivityService
{
    public function record(Task $task, ?User $actor, string $type, string $summary, array $metadata = []): TaskActivity
    {
        $activity = (new TaskActivity())->setTask($task)->setActor($actor)->setType($type)->setSummary($summary)->setMetadata($metadata);
        $task->addActivity($activity);
        return $activity;
    }
}
