<?php

namespace App\Tests\Service;

use App\Entity\AttendanceEntry;
use App\Entity\TaskTimeEntry;
use App\Service\EmployeeWeekViewBuilder;
use PHPUnit\Framework\TestCase;

final class EmployeeWeekViewBuilderTest extends TestCase
{
    public function testBuildsMondayThroughSundayMetricsAndDailyGroups(): void
    {
        $attendance = [
            $this->attendance('2026-07-13 08:00', '2026-07-13 12:00'),
            $this->attendance('2026-07-13 13:00', '2026-07-13 17:00'),
            $this->attendance('2026-07-14 09:00', '2026-07-14 11:00'),
        ];
        $taskTime = [$this->taskTime('2026-07-13 09:00', '2026-07-13 10:30')];

        $view = (new EmployeeWeekViewBuilder())->build($this->local('2026-07-15'), $attendance, $taskTime);

        self::assertSame('2026-07-13', $view['start']->format('Y-m-d'));
        self::assertSame('2026-07-19', $view['end']->format('Y-m-d'));
        self::assertSame('2026-07-20', $view['end_exclusive']->format('Y-m-d'));
        self::assertSame(2, $view['days_worked']);
        self::assertSame(36000, $view['attendance_seconds']);
        self::assertSame(5400, $view['task_seconds']);
        self::assertCount(7, $view['days']);
        self::assertCount(2, $view['days'][0]['attendance_entries']);
        self::assertCount(1, $view['days'][0]['task_time_entries']);
    }

    public function testRangeKeepsMondayInItsOwnWeek(): void
    {
        $range = (new EmployeeWeekViewBuilder())->range($this->local('2026-07-13'));

        self::assertSame('2026-07-13', $range['start']->format('Y-m-d'));
        self::assertSame('2026-W29', $range['week_value']);
    }

    private function attendance(string $start, string $end): AttendanceEntry
    {
        return (new AttendanceEntry())->setCheckInAt($this->local($start))->setCheckOutAt($this->local($end));
    }

    private function taskTime(string $start, string $end): TaskTimeEntry
    {
        return (new TaskTimeEntry())->setStartedAt($this->local($start))->setEndedAt($this->local($end));
    }

    private function local(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value, new \DateTimeZone('Europe/Berlin'));
    }
}
