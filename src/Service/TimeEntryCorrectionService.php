<?php

namespace App\Service;

use App\Entity\AttendanceEntry;
use App\Entity\TaskTimeEntry;

final class TimeEntryCorrectionService
{
    private const INPUT_FORMAT = 'Y-m-d\TH:i';

    public function correctAttendance(AttendanceEntry $entry, string $startedAt, string $endedAt): void
    {
        if ($entry->getCheckOutAt() === null) {
            throw new \InvalidArgumentException('Stop the active attendance session before correcting it.');
        }

        [$start, $end] = $this->parseRange($startedAt, $endedAt);
        foreach ($entry->getBreaks() as $break) {
            $breakEnd = $break->getEndedAt();
            if ($breakEnd === null || $break->getStartedAt() < $start || $breakEnd > $end) {
                throw new \InvalidArgumentException('The attendance range must contain every recorded break.');
            }
        }

        $entry->setCheckInAt($start)->setCheckOutAt($end);
    }

    public function correctTaskTime(TaskTimeEntry $entry, string $startedAt, string $endedAt, ?string $note): void
    {
        if ($entry->getEndedAt() === null) {
            throw new \InvalidArgumentException('Stop the active task timer before correcting it.');
        }

        [$start, $end] = $this->parseRange($startedAt, $endedAt);
        $entry->setStartedAt($start)->setEndedAt($end)->setNote($note);
    }

    /** @return array{0: \DateTimeImmutable, 1: \DateTimeImmutable} */
    private function parseRange(string $startedAt, string $endedAt): array
    {
        $start = $this->parseDateTime($startedAt);
        $end = $this->parseDateTime($endedAt);

        if ($end <= $start) {
            throw new \InvalidArgumentException('The end time must be after the start time.');
        }
        if ($start->format('Y-m-d') !== $end->format('Y-m-d')) {
            throw new \InvalidArgumentException('A corrected session must stay within one day.');
        }

        return [$start, $end];
    }

    private function parseDateTime(string $value): \DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat(
            '!'.self::INPUT_FORMAT,
            $value,
            new \DateTimeZone('Europe/Berlin'),
        );
        $errors = \DateTimeImmutable::getLastErrors();

        if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) || $date->format(self::INPUT_FORMAT) !== $value) {
            throw new \InvalidArgumentException('Enter valid start and end times.');
        }

        return $date;
    }
}
