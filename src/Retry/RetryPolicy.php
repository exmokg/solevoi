<?php

declare(strict_types=1);

namespace SiteTool\Retry;

use SiteTool\Http\HttpResponse;

final class RetryPolicy
{
    private int $maxAttempts;
    private int $baseDelay;
    private float $backoffMultiplier;
    private array $retryableStatusCodes;
    private bool $useJitter;

    public function __construct(
        int $maxAttempts = null,
        int $baseDelay = null,
        float $backoffMultiplier = 2.0,
        array $retryableStatusCodes = null,
        bool $useJitter = true
    ) {
        $this->maxAttempts = $maxAttempts ?? 3;
        $this->baseDelay = $baseDelay ?? 1000;
        $this->backoffMultiplier = $backoffMultiplier;
        $this->retryableStatusCodes = $retryableStatusCodes ?? [429, 500, 502, 503, 504];
        $this->useJitter = $useJitter;
    }

    public function getMaxAttempts(): int
    {
        return $this->maxAttempts;
    }

    public function shouldRetry(HttpResponse $response, int $attempt): bool
    {
        if ($attempt >= $this->maxAttempts) {
            return false;
        }

        return in_array($response->getStatusCode(), $this->retryableStatusCodes, true);
    }

    public function getDelay(int $attempt): int
    {
        $delay = $this->baseDelay * pow($this->backoffMultiplier, $attempt - 1);

        if ($this->useJitter) {
            $jitter = $delay * 0.1;
            $delay += (mt_rand(0, $jitter * 2) - $jitter);
        }

        return (int) max($this->baseDelay, $delay);
    }

    public function withMaxAttempts(int $maxAttempts): self
    {
        $clone = clone $this;
        $clone->maxAttempts = $maxAttempts;
        return $clone;
    }

    public function withBaseDelay(int $baseDelay): self
    {
        $clone = clone $this;
        $clone->baseDelay = $baseDelay;
        return $clone;
    }
}