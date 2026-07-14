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
}
