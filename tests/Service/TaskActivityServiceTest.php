<?php
namespace App\Tests\Service;

use App\Entity\Task;
use App\Entity\User;
use App\Service\TaskActivityService;
use PHPUnit\Framework\TestCase;

final class TaskActivityServiceTest extends TestCase
{
    public function testRecordAddsActivityToTask(): void
    {
        $task = new Task(); $actor = new User();
        $activity = (new TaskActivityService())->record($task, $actor, 'edited', 'Task updated', ['fields' => ['title']]);
        self::assertSame($task, $activity->getTask());
        self::assertSame($actor, $activity->getActor());
        self::assertTrue($task->getActivities()->contains($activity));
    }
}
