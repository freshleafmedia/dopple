<?php

declare(strict_types=1);

namespace Dopple;

use Spatie\Crawler\Throttlers\Throttle;

/**
 * Adaptive throttle that ignores its own sleeps.
 *
 * The crawler sleeps inside the response handler, which blocks every other
 * in-flight transfer. Their measured transfer times then include that sleep,
 * which fed back into Spatie's AdaptiveThrottle and drove the delay to its
 * cap. Here the time each transfer overlapped with our own sleeps is
 * subtracted before the latency is used.
 *
 * Delay = how much slower recent responses are than the fastest seen,
 * clamped to [0, maxDelayMs].
 */
final class SelfAwareThrottle implements Throttle
{
    private const SMOOTHING = 0.2;

    private const BASELINE_DRIFT = 1.01;

    private const SLEEP_HISTORY_SECONDS = 300.0;

    private float $delaySeconds = 0.0;

    private ?float $smoothed = null;

    private ?float $baseline = null;

    /**
     * @var list<array{float, float}> [start, end] of each sleep, in seconds
     */
    private array $sleeps = [];

    public function __construct(
        private readonly int $maxDelayMs = 200,
    ) {}

    public function sleep(): void
    {
        if ($this->delaySeconds <= 0.0) {
            return;
        }

        $start = $this->now();
        usleep((int) ($this->delaySeconds * 1_000_000));
        $this->sleeps[] = [$start, $this->now()];
    }

    public function recordResponseTime(float $seconds): void
    {
        $end = $this->now();
        $latency = max(0.0, $seconds - $this->sleptBetween($end - $seconds, $end));

        $this->smoothed = $this->smoothed === null
            ? $latency
            : $this->smoothed + self::SMOOTHING * ($latency - $this->smoothed);

        // Fastest typical latency, allowed to creep up so one lucky response does not pin it forever
        $this->baseline = $this->baseline === null
            ? $latency
            : min($this->smoothed, $this->baseline * self::BASELINE_DRIFT);

        $this->delaySeconds = min($this->maxDelayMs / 1000, max(0.0, $this->smoothed - $this->baseline));

        $this->pruneSleeps($end);
    }

    private function sleptBetween(float $from, float $to): float
    {
        $total = 0.0;

        foreach ($this->sleeps as [$start, $end]) {
            $total += max(0.0, min($end, $to) - max($start, $from));
        }

        return $total;
    }

    private function pruneSleeps(float $now): void
    {
        $cutoff = $now - self::SLEEP_HISTORY_SECONDS;

        $this->sleeps = array_values(array_filter(
            $this->sleeps,
            static fn(array $sleep): bool => $sleep[1] >= $cutoff,
        ));
    }

    private function now(): float
    {
        return hrtime(true) / 1_000_000_000;
    }
}
