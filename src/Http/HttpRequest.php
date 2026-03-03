<?php

declare(strict_types=1);

namespace SiteTool\Http;

final class HttpRequest
{
    private string $url;
    private string $method;
    private array $headers;
    private ?string $body;
    private array $options;
    private ?string $cookieJar;

    public function __construct(
        string $url,
        string $method = 'GET',
        array $headers = [],
        ?string $body = null,
        array $options = [],
        ?string $cookieJar = null
    ) {
        $this->url = $url;
        $this->method = strtoupper($method);
        $this->headers = $headers;
        $this->body = $body;
        $this->options = $options;
        $this->cookieJar = $cookieJar;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getBody(): ?string
    {
        return $this->body;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getCookieJar(): ?string
    {
        return $this->cookieJar;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function withOption(string $key, mixed $value): self
    {
        $clone = clone $this;
        $clone->options[$key] = $value;
        return $clone;
    }

    public function withCookieJar(string $cookieJar): self
    {
        $clone = clone $this;
        $clone->cookieJar = $cookieJar;
        return $clone;
    }
}
