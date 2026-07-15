<?php

namespace App\Tests\Service;

use App\Entity\AttendanceEntry;
use App\Entity\BreakEntry;
use App\Entity\TaskTimeEntry;
use App\Service\TimeEntryCorrectionService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimeEntryCorrectionServiceTest extends TestCase
{
    public function testCorrectsCompletedAttendanceWhenBreaksRemainInsideRange(): void
    {
        $entry = $this->completedAttendance();
        $entry->addBreak((new BreakEntry())
            ->setStartedAt($this->local('2026-07-14 12:00'))
            ->setEndedAt($this->local('2026-07-14 12:30')));

        (new TimeEntryCorrectionService())->correctAttendance($entry, '2026-07-14T08:15', '2026-07-14T16:45');

        self::assertSame('08:15', $entry->getCheckInAt()->format('H:i'));
        self::assertSame('16:45', $entry->getCheckOutAt()?->format('H:i'));
    }

    public function testCorrectsCompletedTaskTimeAndNote(): void
    {
        $entry = (new TaskTimeEntry())
            ->setStartedAt($this->local('2026-07-14 09:00'))
            ->setEndedAt($this->local('2026-07-14 10:00'));

        (new TimeEntryCorrectionService())->correctTaskTime($entry, '2026-07-14T09:15', '2026-07-14T10:30', ' Corrected ');

        self::assertSame('09:15', $entry->getStartedAt()->format('H:i'));
        self::assertSame('10:30', $entry->getEndedAt()?->format('H:i'));
        self::assertSame('Corrected', $entry->getNote());
    }

    #[DataProvider('invalidRanges')]
    public function testRejectsInvalidRangesWithoutChangingAttendance(string $start, string $end): void
    {
        $entry = $this->completedAttendance();
        $originalStart = $entry->getCheckInAt();
        $originalEnd = $entry->getCheckOutAt();

        try {
            (new TimeEntryCorrectionService())->correctAttendance($entry, $start, $end);
            self::fail('Expected invalid range rejection.');
        } catch (\InvalidArgumentException) {
            self::assertSame($originalStart, $entry->getCheckInAt());
            self::assertSame($originalEnd, $entry->getCheckOutAt());
        }
    }

    public static function invalidRanges(): iterable
    {
        yield 'end before start' => ['2026-07-14T11:00', '2026-07-14T10:00'];
        yield 'cross day' => ['2026-07-14T23:00', '2026-07-15T01:00'];
        yield 'invalid format' => ['not-a-date', '2026-07-14T10:00'];
    }

    public function testRejectsOpenEntries(): void
    {
        $attendance = (new AttendanceEntry())->setCheckInAt($this->local('2026-07-14 08:00'));
        $this->expectException(\InvalidArgumentException::class);
        (new TimeEntryCorrectionService())->correctAttendance($attendance, '2026-07-14T08:00', '2026-07-14T17:00');
    }

    public function testRejectsOpenTaskTime(): void
    {
        $taskTime = (new TaskTimeEntry())->setStartedAt($this->local('2026-07-14 09:00'));

        $this->expectException(\InvalidArgumentException::class);
        (new TimeEntryCorrectionService())->correctTaskTime($taskTime, '2026-07-14T09:00', '2026-07-14T10:00', null);
    }

    public function testRejectsAttendanceRangeThatExcludesABreak(): void
    {
        $entry = $this->completedAttendance();
        $entry->addBreak((new BreakEntry())
            ->setStartedAt($this->local('2026-07-14 12:00'))
            ->setEndedAt($this->local('2026-07-14 12:30')));

        $this->expectException(\InvalidArgumentException::class);
        (new TimeEntryCorrectionService())->correctAttendance($entry, '2026-07-14T13:00', '2026-07-14T17:00');
    }

    private function completedAttendance(): AttendanceEntry
    {
        return (new AttendanceEntry())
            ->setCheckInAt($this->local('2026-07-14 08:00'))
            ->setCheckOutAt($this->local('2026-07-14 17:00'));
    }

    private function local(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value, new \DateTimeZone('Europe/Berlin'));
    }
}
