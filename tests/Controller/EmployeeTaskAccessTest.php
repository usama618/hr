<?php
namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;

final class EmployeeTaskAccessTest extends TestCase
{
    public function testEmployeeFlowsUseAssigneeCollectionOnly(): void
    {
        $root = dirname(__DIR__, 2);
        $source = file_get_contents($root.'/src/Controller/EmployeeController.php')
            .file_get_contents($root.'/src/Form/EmployeeTaskFormType.php')
            .file_get_contents($root.'/templates/employee/dashboard.html.twig')
            .file_get_contents($root.'/templates/employee/project_show.html.twig')
            .file_get_contents($root.'/templates/task/show.html.twig');
        self::assertStringNotContainsString('assignedTo', $source);
        self::assertStringNotContainsString('getAssignedTo', $source);
        self::assertStringNotContainsString('setAssignedTo', $source);
        self::assertStringContainsString('assignees', $source);
        self::assertStringContainsString('isAssignedTo', $source);
    }
}
