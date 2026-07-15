<?php

namespace App\Service;

use App\Entity\AttendanceEntry;
use App\Entity\TaskTimeEntry;

final class EmployeeWeekViewBuilder
{
    private readonly \DateTimeZone $timezone;

    public function __construct()
    {
        $this->timezone = new \DateTimeZone('Europe/Berlin');
    }

    /** @return array{start: \DateTimeImmutable, end: \DateTimeImmutable, end_exclusive: \DateTimeImmutable, selected_date: \DateTimeImmutable, week_value: string} */
    public function range(\DateTimeImmutable $selectedDate): array
    {
        $local = $selectedDate->setTimezone($this->timezone)->setTime(0, 0);
        $start = $local->modify(sprintf('-%d days', (int) $local->format('N') - 1));
        $endExclusive = $start->modify('+7 days');

        return [
            'start' => $start,
            'end' => $endExclusive->modify('-1 day'),
            'end_exclusive' => $endExclusive,
            'selected_date' => $local,
            'week_value' => $start->format('o-\WW'),
        ];
    }

    /**
     * @param list<AttendanceEntry> $attendanceEntries
     * @param list<TaskTimeEntry>   $taskTimeEntries
     *
     * @return array<string, mixed>
     */
    public function build(\DateTimeImmutable $selectedDate, array $attendanceEntries, array $taskTimeEntries): array
    {
        $range = $this->range($selectedDate);
        $days = [];
        for ($offset = 0; $offset < 7; ++$offset) {
            $date = $range['start']->modify(sprintf('+%d days', $offset));
            $days[$date->format('Y-m-d')] = [
                'date' => $date,
                'attendance_entries' => [],
                'task_time_entries' => [],
                'attendance_seconds' => 0,
                'task_seconds' => 0,
            ];
        }

        foreach ($attendanceEntries as $entry) {
            $key = $entry->getCheckInAt()->setTimezone($this->timezone)->format('Y-m-d');
            if (!isset($days[$key])) {
                continue;
            }
            $days[$key]['attendance_entries'][] = $entry;
            $days[$key]['attendance_seconds'] += $entry->getWorkedSeconds();
        }

        foreach ($taskTimeEntries as $entry) {
            $key = $entry->getStartedAt()->setTimezone($this->timezone)->format('Y-m-d');
            if (!isset($days[$key])) {
                continue;
            }
            $days[$key]['task_time_entries'][] = $entry;
            $days[$key]['task_seconds'] += $entry->getSeconds();
        }

        $dayRows = array_values($days);

        return $range + [
            'days_worked' => count(array_filter($dayRows, static fn (array $day): bool => $day['attendance_entries'] !== [])),
            'attendance_seconds' => array_sum(array_column($dayRows, 'attendance_seconds')),
            'task_seconds' => array_sum(array_column($dayRows, 'task_seconds')),
            'days' => $dayRows,
        ];
    }
}
