<?php
namespace App\Tests\Service;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Service\TaskActivityService;
use App\Service\TaskLifecycleService;
use App\Service\TaskRecurrenceService;
use PHPUnit\Framework\TestCase;

final class TaskLifecycleServiceTest extends TestCase
{
    public function testTransitionRecordsHistoryActivityAndRecurrenceOnce(): void
    {
        $actor = new User();
        $task = (new Task())->setProject((new Project())->setName('P'))->setTitle('Weekly')->setRecurrence(Task::RECURRENCE_WEEKLY);
        $service = new TaskLifecycleService(new TaskActivityService(), new TaskRecurrenceService());

        $next = $service->transition($task, Task::STATUS_COMPLETED, $actor);

        self::assertSame(Task::STATUS_COMPLETED, $task->getStatus());
        self::assertCount(1, $task->getStatusHistory());
        self::assertCount(1, $task->getActivities());
        self::assertNotNull($next);
        self::assertNull($service->transition($task, Task::STATUS_COMPLETED, $actor));
        self::assertCount(1, $task->getStatusHistory());
    }
}
