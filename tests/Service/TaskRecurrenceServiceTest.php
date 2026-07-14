<?php
namespace App\Tests\Service;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Service\TaskRecurrenceService;
use PHPUnit\Framework\TestCase;

final class TaskRecurrenceServiceTest extends TestCase
{
    public function testCreatesOneNextOccurrenceWithAdvancedDates(): void
    {
        $assignee = new User();
        $task = (new Task())->setProject((new Project())->setName('P'))->setTitle('Weekly report')
            ->setDescription('Prepare')->setStatus(Task::STATUS_COMPLETED)->setPriority('high')
            ->setStartDate(new \DateTimeImmutable('2026-07-14'))->setDueDate(new \DateTimeImmutable('2026-07-15'))
            ->setReminderAt(new \DateTimeImmutable('2026-07-14 09:00'))->setRecurrence(Task::RECURRENCE_WEEKLY)
            ->setTags(['Reporting'])->addAssignee($assignee);

        $service = new TaskRecurrenceService();
        $next = $service->createNextOccurrence($task);

        self::assertNotNull($next);
        self::assertSame('2026-07-21', $next->getStartDate()?->format('Y-m-d'));
        self::assertSame('2026-07-22', $next->getDueDate()?->format('Y-m-d'));
        self::assertSame(Task::STATUS_TODO, $next->getStatus());
        self::assertTrue($next->isAssignedTo($assignee));
        self::assertSame($task, $next->getSourceOccurrence());
        self::assertSame($next, $task->getNextOccurrence());
        self::assertNull($service->createNextOccurrence($task));
    }
}
