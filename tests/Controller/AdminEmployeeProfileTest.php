<?php

namespace App\Tests\Controller;

use PHPUnit\Framework\TestCase;

final class AdminEmployeeProfileTest extends TestCase
{
    public function testRepositoriesExposeEmployeeScopedProfileQueries(): void
    {
        $root = dirname(__DIR__, 2);
        $timeRepository = file_get_contents($root.'/src/Repository/TaskTimeEntryRepository.php');
        $taskRepository = file_get_contents($root.'/src/Repository/TaskRepository.php');
        self::assertIsString($timeRepository);
        self::assertIsString($taskRepository);

        foreach (['findForUserBetween', 'sumSecondsByTaskForUser', "andWhere('t.employee = :employee')"] as $needle) {
            self::assertStringContainsString($needle, $timeRepository);
        }
        self::assertStringContainsString('findAssignedTo', $taskRepository);
        self::assertStringContainsString("andWhere(':employee MEMBER OF t.assignees')", $taskRepository);
    }

    public function testControllerAndTemplateExposeProfileAndCorrectionRoutes(): void
    {
        $root = dirname(__DIR__, 2);
        $controller = file_get_contents($root.'/src/Controller/AdminController.php');
        $templatePath = $root.'/templates/admin/employee_show.html.twig';
        self::assertIsString($controller);
        self::assertFileExists($templatePath);
        $template = file_get_contents($templatePath);
        self::assertIsString($template);

        foreach (['admin_employee_show', 'admin_employee_attendance_update', 'admin_employee_task_time_update', 'MapEntity', 'correctAttendance', 'correctTaskTime', 'attendance_correction_', 'task_time_correction_'] as $needle) {
            self::assertStringContainsString($needle, $controller.$template);
        }
        foreach (['Days worked', 'Attendance hours', 'Task hours', 'Assigned tasks', 'Daily time', 'type="week"', 'entry.checkOutAt', 'entry.endedAt'] as $needle) {
            self::assertStringContainsString($needle, $template);
        }
    }
}
