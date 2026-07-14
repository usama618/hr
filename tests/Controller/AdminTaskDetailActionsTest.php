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
        foreach (['status', 'time-log', 'documents/upload', 'dependencies', 'problems', 'resolve'] as $route) {
            self::assertStringContainsString($route, $controller);
        }
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
    }
}
