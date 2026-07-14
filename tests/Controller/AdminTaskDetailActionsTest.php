<?php
namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;

final class AdminTaskDetailActionsTest extends TestCase
{
    public function testControllerDefinesProtectedDetailActions(): void
    {
        $path = dirname(__DIR__, 2).'/src/Controller/AdminTaskController.php';
        self::assertFileExists($path);
        $controller = file_get_contents($path);
        foreach (['status', 'time-log', 'timer/start', 'timer/stop', 'documents/upload', 'dependencies', 'problems', 'resolve'] as $route) {
            self::assertStringContainsString($route, $controller);
        }
        self::assertStringContainsString('findOpenForUser', $controller);
        self::assertStringContainsString('findOpenForUserAndTask', $controller);
        self::assertStringContainsString('$status !== Task::STATUS_IN_PROGRESS', $controller);
        self::assertStringContainsString('guardCsrf', $controller);
        self::assertStringContainsString('var/task-documents', $controller);
        self::assertStringContainsString('12 * 1024 * 1024', $controller);
    }

    public function testPanelContainsAllTaskSections(): void
    {
        $path = dirname(__DIR__, 2).'/templates/admin/_task_detail_panel.html.twig';
        self::assertFileExists($path);
        $panel = file_get_contents($path);
        foreach (['Comments', 'Subtasks', 'Time Logs', 'Documents', 'Dependencies', 'Status Timeline', 'Problems', 'Activity'] as $label) {
            self::assertStringContainsString($label, $panel);
        }
        self::assertStringContainsString('task-timer-control', $panel);
        self::assertStringContainsString('admin_task_detail_timer_start', $panel);
        self::assertStringContainsString('admin_task_detail_timer_stop', $panel);
    }
}
