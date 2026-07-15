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
}
