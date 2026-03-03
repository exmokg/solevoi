<?php

declare(strict_types=1);

namespace SiteTool\Http;

final class HttpResponse
{
    private int $statusCode;
    private array $headers;
    private string $body;
    private ?string $effectiveUrl;
    private float $duration;
    private int $size;

    public function __construct(
        int $statusCode,
        array $headers,
        string $body,
        ?string $effectiveUrl = null,
        float $duration = 0.0,
        int $size = 0
    ) {
        $this->statusCode = $statusCode;
        $this->headers = $headers;
        $this->body = $body;
        $this->effectiveUrl = $effectiveUrl;
        $this->duration = $duration;
        $this->size = $size ?: strlen($body);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getEffectiveUrl(): ?string
    {
        return $this->effectiveUrl;
    }

    public function getDuration(): float
    {
        return $this->duration;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function isSuccessful(): bool
    {
        return $this->statusCode >= 200 && $this->statusCode < 300;
    }

    public function isRedirect(): bool
    {
        return $this->statusCode >= 300 && $this->statusCode < 400;
    }

    public function isClientError(): bool
    {
        return $this->statusCode >= 400 && $this->statusCode < 500;
    }

    public function isServerError(): bool
    {
        return $this->statusCode >= 500;
    }

    public function getContentType(): ?string
    {
        $contentType = $this->getHeader('content-type');
        if ($contentType === null) {
            return null;
        }

        return explode(';', $contentType)[0];
    }
}