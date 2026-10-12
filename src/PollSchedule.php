<?php

declare(strict_types=1);

namespace slash197\eTransport;

/**
 * When to ask for the outcome of an upload: a few times shortly after it,
 * since ANAF usually answers within seconds, then less and less often.
 *
 * ANAF answers the status of one upload at most 100 times a day, and the
 * schedule stays below that.
 */
final readonly class PollSchedule
{
    private const DAILY_LIMIT = 100;

    /**
     * By default: 5, 15, 35 and 65 seconds after the upload, then after 2, 4,
     * 9, 19 and 34 minutes, then every 30 minutes until a day has passed.
     *
     * @param list<int> $delays       Seconds to wait before each of the first checks
     * @param int       $then         Seconds to wait before every check after those
     * @param int       $giveUpAfter  Seconds after the upload from which no check is made
     */
    public function __construct(
        private array $delays = [5, 10, 20, 30, 60, 120, 300, 600, 900],
        private int $then = 1800,
        private int $giveUpAfter = 86400,
    ) {
        if ($then < 1 || $giveUpAfter < 1 || array_filter($delays, static fn (int $delay): bool => $delay < 1) !== []) {
            throw new \InvalidArgumentException('Delays are whole seconds, at least one.');
        }

        $checks = 0;
        while ($this->secondsUntil($checks) !== null) {
            if (++$checks >= self::DAILY_LIMIT) {
                throw new \InvalidArgumentException('ANAF answers the status of an upload at most ' . self::DAILY_LIMIT . ' times a day.');
            }
        }
    }

    /**
     * When the next check is due, or null when it is time to give up.
     *
     * @param int $checksMade How many times the outcome was already asked for
     */
    public function nextCheck(\DateTimeInterface $uploadedAt, int $checksMade): ?\DateTimeImmutable
    {
        $seconds = $this->secondsUntil($checksMade);

        return $seconds === null
            ? null
            : \DateTimeImmutable::createFromInterface($uploadedAt)->add(new \DateInterval("PT{$seconds}S"));
    }

    /**
     * Whether a check is due now.
     */
    public function isDue(\DateTimeInterface $uploadedAt, int $checksMade, \DateTimeInterface $now): bool
    {
        $next = $this->nextCheck($uploadedAt, $checksMade);

        return $next !== null && $next <= $now;
    }

    /**
     * Seconds from the upload to the check after $checksMade checks.
     */
    private function secondsUntil(int $checksMade): ?int
    {
        $first = array_slice($this->delays, 0, max(0, $checksMade) + 1);
        $seconds = array_sum($first) + $this->then * (max(0, $checksMade) + 1 - count($first));

        return $seconds < $this->giveUpAfter ? $seconds : null;
    }
}
