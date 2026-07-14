<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;

final class EmployeeTaskWorkspaceTest extends TestCase
{
    public function testDashboardBuildsAProjectScopedTaskWorkspace(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/src/Controller/EmployeeController.php');
        self::assertIsString($source);

        foreach (['TaskRepository', 'TaskHierarchyService', "'task_projects'", "'selected_task_project'", "'employee_task_rows'", "'selected_employee_task'"] as $needle) {
            self::assertStringContainsString($needle, $source);
        }

        self::assertStringContainsString("query->get('project'", $source);
        self::assertStringContainsString("query->get('task'", $source);
        self::assertStringContainsString('getProjects()->contains', $source);
    }

    public function testTasksTabUsesProjectNavigatorAndRecursiveTree(): void
    {
        $root = dirname(__DIR__, 2);
        $workspacePath = $root.'/templates/employee/_task_workspace.html.twig';
        $rowsPath = $root.'/templates/employee/_task_tree_rows.html.twig';
        self::assertFileExists($workspacePath);
        self::assertFileExists($rowsPath);

        $templates = file_get_contents($root.'/templates/employee/dashboard.html.twig')
            .file_get_contents($workspacePath)
            .file_get_contents($rowsPath);
        self::assertIsString($templates);

        foreach (['task-project-nav', 'role="treegrid"', 'data-task-row-url', 'data-task-toggle', 'task-timer-control', 'employee_task_start', 'employee_task_pause'] as $needle) {
            self::assertStringContainsString($needle, $templates);
        }
    }
}
