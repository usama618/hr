<?php

namespace App\Tests\Entity;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class TaskTest extends TestCase
{
    public function testChildrenAndParentStayInSync(): void
    {
        $parent = new Task();
        $child = new Task();

        $parent->addChild($child);

        self::assertSame($parent, $child->getParent());
        self::assertTrue($parent->getChildren()->contains($child));

        $parent->removeChild($child);

        self::assertNull($child->getParent());
        self::assertFalse($parent->getChildren()->contains($child));
    }

    public function testTaskCannotBeItsOwnParent(): void
    {
        $task = new Task();

        $this->expectException(\DomainException::class);
        $task->setParent($task);
    }

    public function testAssigneesAreUniqueAndBidirectional(): void
    {
        $task = new Task();
        $user = new User();

        $task->addAssignee($user)->addAssignee($user);

        self::assertCount(1, $task->getAssignees());
        self::assertTrue($task->isAssignedTo($user));
        self::assertTrue($user->getTasks()->contains($task));

        $task->removeAssignee($user);

        self::assertFalse($task->isAssignedTo($user));
        self::assertFalse($user->getTasks()->contains($task));
    }

    public function testTagsAreTrimmedAndCaseInsensitivelyUnique(): void
    {
        $task = (new Task())->setTags([' Backend ', 'urgent', 'backend', '', 'URGENT']);

        self::assertSame(['Backend', 'urgent'], $task->getTags());
    }

    public function testDueDateCannotPrecedeStartDate(): void
    {
        $task = (new Task())->setStartDate(new \DateTimeImmutable('2026-07-20'));

        $this->expectException(\DomainException::class);
        $task->setDueDate(new \DateTimeImmutable('2026-07-19'));
    }

    public function testMetadataUsesConstrainedValues(): void
    {
        $task = (new Task())
            ->setRecurrence('fortnightly')
            ->setBillingType('free')
            ->setManagerNote('  Internal only  ')
            ->setReminderAt(new \DateTimeImmutable('2026-07-18 09:00:00'));

        self::assertNull($task->getRecurrence());
        self::assertSame(Task::BILLING_BILLABLE, $task->getBillingType());
        self::assertSame('Internal only', $task->getManagerNote());
        self::assertSame('2026-07-18 09:00:00', $task->getReminderAt()?->format('Y-m-d H:i:s'));
    }

    public function testCompletionUsesAllLeafDescendants(): void
    {
        $project = (new Project())->setName('Test');
        $root = (new Task())->setProject($project);
        $branch = (new Task())->setProject($project);
        $done = (new Task())->setProject($project)->setStatus(Task::STATUS_COMPLETED);
        $open = (new Task())->setProject($project)->setStatus(Task::STATUS_TODO);
        $root->addChild($branch);
        $branch->addChild($done);
        $root->addChild($open);

        self::assertSame(50, $root->getCompletionPercentage());
        self::assertSame(100, $done->getCompletionPercentage());
        self::assertSame(0, $open->getCompletionPercentage());
    }
}
