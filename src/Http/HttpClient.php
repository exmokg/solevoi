<?php

declare(strict_types=1);

namespace SiteTool\Http;

use SiteTool\Core\Config;
use SiteTool\Core\LoggerAdapter;
use SiteTool\Retry\RetryPolicy;
use SiteTool\Retry\CircuitBreaker;
use SiteTool\Retry\AdaptiveThrottle;

final class HttpClient
{
    private LoggerAdapter $logger;
    private RetryPolicy $retryPolicy;
    private CircuitBreaker $circuitBreaker;
    private AdaptiveThrottle $throttle;
    private ?string $proxy;
    private ?string $auth;
    private array $defaultHeaders;

    public function __construct(
        LoggerAdapter $logger,
        RetryPolicy $retryPolicy,
        CircuitBreaker $circuitBreaker,
        AdaptiveThrottle $throttle,
        ?string $proxy = null,
        ?string $auth = null,
        array $defaultHeaders = []
    ) {
        $this->logger = $logger;
        $this->retryPolicy = $retryPolicy;
        $this->circuitBreaker = $circuitBreaker;
        $this->throttle = $throttle;
        $this->proxy = $proxy;
        $this->auth = $auth;
        $this->defaultHeaders = $defaultHeaders;
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $this->throttle->wait($request->getUrl());

        if (!$this->circuitBreaker->allow($request->getUrl())) {
            throw new \RuntimeException('Circuit breaker is open for: ' . $request->getUrl());
        }

        $attempt = 0;
        $lastException = null;

        while ($attempt < $this->retryPolicy->getMaxAttempts()) {
            $attempt++;

            try {
                $response = $this->executeRequest($request);
                
                if ($this->retryPolicy->shouldRetry($response, $attempt)) {
                    $delay = $this->retryPolicy->getDelay($attempt);
                    $this->logger->warning("Retry attempt {$attempt} for {$request->getUrl()}", [
                        'status' => $response->getStatusCode(),
                        'delay' => $delay,
                    ]);
                    usleep($delay * 1000);
                    continue;
                }

                if ($response->isSuccessful()) {
                    $this->circuitBreaker->recordSuccess($request->getUrl());
                    $this->throttle->recordSuccess($request->getUrl());
                    return $response;
                }

                if ($response->isServerError()) {
                    $this->circuitBreaker->recordFailure($request->getUrl());
                    $this->throttle->recordFailure($request->getUrl());
                    throw new \RuntimeException("Server error: {$response->getStatusCode()}");
                }

                return $response;

            } catch (\Exception $e) {
                $lastException = $e;
                $this->circuitBreaker->recordFailure($request->getUrl());
                $this->throttle->recordFailure($request->getUrl());

                if ($attempt < $this->retryPolicy->getMaxAttempts()) {
                    $delay = $this->retryPolicy->getDelay($attempt);
                    $this->logger->error("Request failed, retrying in {$delay}ms", [
                        'url' => $request->getUrl(),
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                    ]);
                    usleep($delay * 1000);
                }
            }
        }

        throw $lastException ?? new \RuntimeException('Request failed after retries');
    }

    private function executeRequest(HttpRequest $request): HttpResponse
    {
        $ch = curl_init();

        $headers = array_merge($this->defaultHeaders, $request->getHeaders());
        $formattedHeaders = [];
        foreach ($headers as $name => $value) {
            $formattedHeaders[] = "$name: $value";
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $request->getUrl(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => Config::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => Config::TRANSFER_TIMEOUT,
            CURLOPT_DNS_CACHE_TIMEOUT => Config::DNS_CACHE_TIMEOUT,
            CURLOPT_HTTPHEADER => $formattedHeaders,
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_USERAGENT => $this->getUserAgent(),
        ]);

        if ($request->getBody() !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request->getBody());
        }

        if ($this->proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy);
        }

        if ($this->auth) {
            curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
            curl_setopt($ch, CURLOPT_USERPWD, $this->auth);
        }

        if ($request->getCookieJar()) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $request->getCookieJar());
            curl_setopt($ch, CURLOPT_COOKIEFILE, $request->getCookieJar());
        }

        foreach ($request->getOptions() as $key => $value) {
            curl_setopt($ch, $key, $value);
        }

        $startTime = microtime(true);
        $response = curl_exec($ch);
        $duration = microtime(true) - $startTime;

        if ($response === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("CURL error: $error");
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);

        $headerText = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        $headers = $this->parseHeaders($headerText);

        return new HttpResponse(
            $statusCode,
            $headers,
            $body,
            $effectiveUrl,
            $duration
        );
    }

    private function parseHeaders(string $headerText): array
    {
        $headers = [];
        $lines = explode("\r\n", trim($headerText));

        foreach ($lines as $line) {
            if (strpos($line, ':') === false) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        return $headers;
    }

    private function getUserAgent(): string
    {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ];

        return $agents[array_rand($agents)];
    }
}