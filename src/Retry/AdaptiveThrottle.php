<?php

declare(strict_types=1);

namespace SiteTool\Retry;

use SiteTool\Core\Config;

final class AdaptiveThrottle
{
    private int $minDelay;
    private int $maxDelay;
    private int $threshold;
    private array $delays = [];
    private array $failureCounts = [];
    private array $successCounts = [];

    public function __construct(
        int $minDelay = null,
        int $maxDelay = null,
        int $threshold = null
    ) {
        $this->minDelay = $minDelay ?? Config::MIN_REQUEST_DELAY;
        $this->maxDelay = $maxDelay ?? Config::MAX_REQUEST_DELAY;
        $this->threshold = $threshold ?? Config::ADAPTIVE_THROTTLE_THRESHOLD;
    }

    public function wait(string $key): void
    {
        $delay = $this->delays[$key] ?? $this->minDelay;
        if ($delay > 0) {
            usleep($delay * 1000);
        }
    }

    public function recordSuccess(string $key): void
    {
        $this->successCounts[$key] = ($this->successCounts[$key] ?? 0) + 1;
        $this->failureCounts[$key] = 0;

        $currentDelay = $this->delays[$key] ?? $this->minDelay;
        
        if ($this->successCounts[$key] >= $this->threshold) {
            $newDelay = max($this->minDelay, (int)($currentDelay * 0.8));
            $this->delays[$key] = $newDelay;
            $this->successCounts[$key] = 0;
        }
    }

    public function recordFailure(string $key): void
    {
        $this->failureCounts[$key] = ($this->failureCounts[$key] ?? 0) + 1;
        $this->successCounts[$key] = 0;

        $currentDelay = $this->delays[$key] ?? $this->minDelay;
        
        if ($this->failureCounts[$key] >= $this->threshold) {
            $newDelay = min($this->maxDelay, (int)($currentDelay * 1.5));
            $this->delays[$key] = $newDelay;
            $this->failureCounts[$key] = 0;
        }
    }

    public function getDelay(string $key): int
    {
        return $this->delays[$key] ?? $this->minDelay;
    }

    public function reset(string $key): void
    {
        unset($this->delays[$key]);
        unset($this->failureCounts[$key]);
        unset($this->successCounts[$key]);
    }

    public function getStats(string $key): array
    {
        return [
            'current_delay' => $this->delays[$key] ?? $this->minDelay,
            'failure_count' => $this->failureCounts[$key] ?? 0,
            'success_count' => $this->successCounts[$key] ?? 0,
        ];
    }
}
