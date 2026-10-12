<?php

declare(strict_types=1);

namespace slash197\eTransport\Tests\Unit;

use PHPUnit\Framework\TestCase;
use slash197\eTransport\PollSchedule;

final class PollScheduleTest extends TestCase
{
    public function testChecksComeInAShortBurstAndThenBackOff(): void
    {
        $schedule = new PollSchedule();
        $uploadedAt = new \DateTimeImmutable('2026-10-12 12:00:00');

        $times = [];
        for ($checks = 0; $checks < 11; $checks++) {
            $times[] = $schedule->nextCheck($uploadedAt, $checks)?->format('H:i:s');
        }

        self::assertSame([
            '12:00:05', '12:00:15', '12:00:35', '12:01:05',
            '12:02:05', '12:04:05', '12:09:05', '12:19:05', '12:34:05',
            '13:04:05', '13:34:05',
        ], $times);
    }

    public function testItGivesUpAfterADayAndStaysBelowAnafsDailyLimit(): void
    {
        $schedule = new PollSchedule();
        $uploadedAt = new \DateTimeImmutable('2026-10-12 12:00:00');

        $checks = 0;
        while ($schedule->nextCheck($uploadedAt, $checks) !== null) {
            $checks++;
        }

        self::assertSame(55, $checks);
        self::assertSame('2026-10-13 11:34:05', $schedule->nextCheck($uploadedAt, 54)?->format('Y-m-d H:i:s'));
        self::assertNull($schedule->nextCheck($uploadedAt, 500));
    }

    public function testACheckIsDueOnceItsTimeHasCome(): void
    {
        $schedule = new PollSchedule();
        $uploadedAt = new \DateTimeImmutable('2026-10-12 12:00:00');

        self::assertFalse($schedule->isDue($uploadedAt, 0, new \DateTimeImmutable('2026-10-12 12:00:04')));
        self::assertTrue($schedule->isDue($uploadedAt, 0, new \DateTimeImmutable('2026-10-12 12:00:05')));
        self::assertFalse($schedule->isDue($uploadedAt, 1, new \DateTimeImmutable('2026-10-12 12:00:05')));
        self::assertTrue($schedule->isDue($uploadedAt, 1, new \DateTimeImmutable('2026-10-14 12:00:00')));
        self::assertFalse($schedule->isDue($uploadedAt, 55, new \DateTimeImmutable('2026-10-14 12:00:00')));
    }

    public function testAScheduleOfItsOwnIsAccepted(): void
    {
        $schedule = new PollSchedule([2, 2], 3600, 7200);
        $uploadedAt = new \DateTimeImmutable('2026-10-12 12:00:00');

        self::assertSame('12:00:04', $schedule->nextCheck($uploadedAt, 1)?->format('H:i:s'));
        self::assertSame('13:00:04', $schedule->nextCheck($uploadedAt, 2)?->format('H:i:s'));
        self::assertNull($schedule->nextCheck($uploadedAt, 3));
    }

    public function testAScheduleThatWouldExceedAnafsDailyLimitIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('100 times a day');

        new PollSchedule([], 60);
    }

    public function testADelayOfNothingIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new PollSchedule([5, 0]);
    }
}
