<?php

declare(strict_types=1);

namespace SiteTool\Retry;

final class CircuitBreaker
{
    private int $failureThreshold;
    private int $successThreshold;
    private int $timeout;
    private array $states = [];
    private array $failureCounts = [];
    private array $successCounts = [];
    private array $lastFailureTime = [];

    public function __construct(
        int $failureThreshold = 5,
        int $successThreshold = 2,
        int $timeout = 60
    ) {
        $this->failureThreshold = $failureThreshold;
        $this->successThreshold = $successThreshold;
        $this->timeout = $timeout;
    }

    public function allow(string $key): bool
    {
        $state = $this->getState($key);

        if ($state === 'closed') {
            return true;
        }

        if ($state === 'open') {
            if ($this->shouldAttemptReset($key)) {
                $this->setState($key, 'half-open');
                return true;
            }
            return false;
        }

        return $state === 'half-open';
    }

    public function recordSuccess(string $key): void
    {
        $this->failureCounts[$key] = 0;
        $this->successCounts[$key] = ($this->successCounts[$key] ?? 0) + 1;

        if ($this->successCounts[$key] >= $this->successThreshold) {
            $this->setState($key, 'closed');
            $this->successCounts[$key] = 0;
        }
    }

    public function recordFailure(string $key): void
    {
        $this->failureCounts[$key] = ($this->failureCounts[$key] ?? 0) + 1;
        $this->successCounts[$key] = 0;
        $this->lastFailureTime[$key] = time();

        if ($this->failureCounts[$key] >= $this->failureThreshold) {
            $this->setState($key, 'open');
        }
    }

    private function getState(string $key): string
    {
        return $this->states[$key] ?? 'closed';
    }

    private function setState(string $key, string $state): void
    {
        $this->states[$key] = $state;
    }

    private function shouldAttemptReset(string $key): bool
    {
        $lastFailure = $this->lastFailureTime[$key] ?? 0;
        return (time() - $lastFailure) >= $this->timeout;
    }

    public function getStateInfo(string $key): array
    {
        return [
            'state' => $this->getState($key),
            'failure_count' => $this->failureCounts[$key] ?? 0,
            'success_count' => $this->successCounts[$key] ?? 0,
            'last_failure' => $this->lastFailureTime[$key] ?? null,
        ];
    }

    public function reset(string $key): void
    {
        unset($this->states[$key]);
        unset($this->failureCounts[$key]);
        unset($this->successCounts[$key]);
        unset($this->lastFailureTime[$key]);
    }
}