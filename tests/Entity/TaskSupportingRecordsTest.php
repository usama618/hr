<?php

namespace App\Tests\Entity;

use App\Entity\Task;
use App\Entity\TaskActivity;
use App\Entity\TaskDependency;
use App\Entity\TaskDocument;
use App\Entity\TaskProblem;
use App\Entity\TaskStatusHistory;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class TaskSupportingRecordsTest extends TestCase
{
    public function testTaskOwnsDocumentsAndMetadata(): void
    {
        $document = (new TaskDocument())
            ->setOriginalFilename('brief.pdf')->setStoredFilename('abc.pdf')
            ->setMimeType('application/pdf')->setFileSize(1234);
        $task = (new Task())->addDocument($document);

        self::assertSame($task, $document->getTask());
        self::assertTrue($task->getDocuments()->contains($document));
        self::assertSame(1234, $document->getFileSize());
    }

    public function testDependencyRejectsItself(): void
    {
        $task = new Task();
        $this->expectException(\DomainException::class);
        (new TaskDependency())->setTask($task)->setPrerequisite($task);
    }

    public function testProblemCanBeResolved(): void
    {
        $resolver = new User();
        $problem = (new TaskProblem())->setDescription('Blocked by access')->resolve($resolver);

        self::assertTrue($problem->isResolved());
        self::assertSame($resolver, $problem->getResolvedBy());
        self::assertNotNull($problem->getResolvedAt());
    }

    public function testHistoryAndActivityKeepStructuredContext(): void
    {
        $task = new Task();
        $history = (new TaskStatusHistory())->setTask($task)->setPreviousStatus('todo')->setNewStatus('in_progress');
        $activity = (new TaskActivity())->setTask($task)->setType('status_changed')->setSummary('Status changed')->setMetadata(['from' => 'todo']);

        self::assertSame('todo', $history->getPreviousStatus());
        self::assertSame('in_progress', $history->getNewStatus());
        self::assertSame(['from' => 'todo'], $activity->getMetadata());
        self::assertNotNull($history->getCreatedAt());
        self::assertNotNull($activity->getCreatedAt());
    }
}
