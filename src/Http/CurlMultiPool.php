<?php

declare(strict_types=1);

namespace SiteTool\Http;

use SiteTool\Core\Config;
use SiteTool\Core\LoggerAdapter;

final class CurlMultiPool
{
    private LoggerAdapter $logger;
    private int $maxConcurrent;
    private array $handles = [];
    private $multiHandle;

    public function __construct(LoggerAdapter $logger, int $maxConcurrent = null)
    {
        $this->logger = $logger;
        $this->maxConcurrent = $maxConcurrent ?? Config::CURL_PARALLEL_LIMIT;
        $this->multiHandle = curl_multi_init();
    }

    public function addRequest(HttpRequest $request, callable $callback): void
    {
        if (count($this->handles) >= $this->maxConcurrent) {
            $this->execute();
        }

        $ch = $this->createHandle($request);
        $key = (string) $request->getUrl();

        $this->handles[$key] = [
            'handle' => $ch,
            'request' => $request,
            'callback' => $callback,
            'start_time' => microtime(true),
        ];

        curl_multi_add_handle($this->multiHandle, $ch);
    }

    public function execute(): void
    {
        if (empty($this->handles)) {
            return;
        }

        $active = null;
        do {
            $status = curl_multi_exec($this->multiHandle, $active);
            
            if ($status === CURLM_OK) {
                curl_multi_select($this->multiHandle, 1.0);
            }

            while ($info = curl_multi_info_read($this->multiHandle)) {
                $this->handleResult($info);
            }

        } while ($active > 0 && $status === CURLM_OK);

        $this->clearCompleted();
    }

    private function handleResult(array $info): void
    {
        $ch = $info['handle'];
        $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        $key = $this->findKeyByUrl($url);

        if ($key === null) {
            return;
        }

        $handleData = $this->handles[$key];
        $duration = microtime(true) - $handleData['start_time'];

        try {
            if ($info['result'] !== CURLE_OK) {
                throw new \RuntimeException('CURL error: ' . curl_error($ch));
            }

            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $response = curl_multi_getcontent($ch);

            $headerText = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);

            $headers = $this->parseHeaders($headerText);

            $response = new HttpResponse(
                $statusCode,
                $headers,
                $body,
                $url,
                $duration
            );

            call_user_func($handleData['callback'], $response, null);

        } catch (\Exception $e) {
            call_user_func($handleData['callback'], null, $e);
        }

        $handleData['completed'] = true;
    }

    private function clearCompleted(): void
    {
        foreach ($this->handles as $key => $data) {
            if ($data['completed'] ?? false) {
                curl_multi_remove_handle($this->multiHandle, $data['handle']);
                curl_close($data['handle']);
                unset($this->handles[$key]);
            }
        }
    }

    private function createHandle(HttpRequest $request)
    {
        $ch = curl_init();

        $headers = [];
        foreach ($request->getHeaders() as $name => $value) {
            $headers[] = "$name: $value";
        }

        curl_setopt_array($ch, [
            CURLOPT_URL => $request->getUrl(),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => Config::CONNECT_TIMEOUT,
            CURLOPT_TIMEOUT => Config::TRANSFER_TIMEOUT,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $request->getMethod(),
            CURLOPT_USERAGENT => $this->getUserAgent(),
        ]);

        if ($request->getBody() !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request->getBody());
        }

        if ($request->getCookieJar()) {
            curl_setopt($ch, CURLOPT_COOKIEJAR, $request->getCookieJar());
            curl_setopt($ch, CURLOPT_COOKIEFILE, $request->getCookieJar());
        }

        foreach ($request->getOptions() as $key => $value) {
            curl_setopt($ch, $key, $value);
        }

        return $ch;
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

    private function findKeyByUrl(string $url): ?string
    {
        foreach ($this->handles as $key => $data) {
            if ($data['request']->getUrl() === $url) {
                return $key;
            }
        }
        return null;
    }

    private function getUserAgent(): string
    {
        $agents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        ];

        return $agents[array_rand($agents)];
    }

    public function __destruct()
    {
        if ($this->multiHandle) {
            curl_multi_close($this->multiHandle);
        }
    }
}