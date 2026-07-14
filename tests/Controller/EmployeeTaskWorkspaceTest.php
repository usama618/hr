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

    public function testTasksTabUsesTheSameWideDesktopColumnsAsAdmin(): void
    {
        $workspace = file_get_contents(dirname(__DIR__, 2).'/templates/employee/_task_workspace.html.twig');
        self::assertIsString($workspace);

        self::assertStringContainsString('task-workspace--wide', $workspace);
        self::assertSame(8, substr_count($workspace, 'class="task-col task-col--'));
    }

    public function testEmployeesCanCreateRecursiveSubtasksFromTheWorkspace(): void
    {
        $root = dirname(__DIR__, 2);
        $dialogPath = $root.'/templates/employee/_task_create_dialog.html.twig';
        self::assertFileExists($dialogPath);

        $controller = file_get_contents($root.'/src/Controller/EmployeeController.php');
        $rows = file_get_contents($root.'/templates/employee/_task_tree_rows.html.twig');
        $dialog = file_get_contents($dialogPath);
        self::assertIsString($controller);
        self::assertIsString($rows);
        self::assertIsString($dialog);
        self::assertStringContainsString("request->request->get('parent_id'", $controller);
        self::assertStringContainsString('assertValidParent', $controller);
        self::assertStringContainsString('parent: task.id', $rows);
        self::assertStringContainsString('name="parent_id"', $dialog);
    }

    public function testEmployeeDetailPanelContainsSafeTaskFeatures(): void
    {
        $root = dirname(__DIR__, 2);
        $panelPath = $root.'/templates/employee/_task_detail_panel.html.twig';
        self::assertFileExists($panelPath);
        $panel = file_get_contents($panelPath);
        $controller = file_get_contents($root.'/src/Controller/EmployeeController.php');
        self::assertIsString($panel);
        self::assertIsString($controller);

        foreach (['Comments', 'Subtasks', 'Time Logs', 'Documents', 'Status Timeline', 'Problems', 'Activity'] as $label) {
            self::assertStringContainsString($label, $panel);
        }
        self::assertStringNotContainsString('managerNote', $panel);
        self::assertStringNotContainsString('Add Time Log', $panel);
        self::assertStringContainsString('task_document_download', $controller);
        self::assertStringContainsString('task_problem_add', $controller);
        self::assertStringContainsString('denyUnlessTaskParticipant', $controller);
    }

    public function testTaskActionsPreserveEmployeeWorkspaceContext(): void
    {
        $controller = file_get_contents(dirname(__DIR__, 2).'/src/Controller/EmployeeController.php');
        self::assertIsString($controller);
        self::assertStringContainsString("'workspace_list'", $controller);
        self::assertStringContainsString("'workspace_task'", $controller);
        self::assertStringContainsString('redirectToTaskWorkspace', $controller);
    }
}
