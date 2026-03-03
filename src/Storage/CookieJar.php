<?php

declare(strict_types=1);

namespace SiteTool\Storage;

use SiteTool\Core\Config;

final class CookieJar
{
    private string $jarPath;
    private array $cookies = [];
    private bool $loaded = false;

    public function __construct(string $jarPath = null)
    {
        $this->jarPath = $jarPath ?? Config::COOKIE_JAR_DIR . '/default.jar';
    }

    public function load(): void
    {
        if ($this->loaded) {
            return;
        }

        if (!file_exists($this->jarPath)) {
            $this->loaded = true;
            return;
        }

        $content = file_get_contents($this->jarPath);
        if ($content === false) {
            return;
        }

        $data = json_decode($content, true);
        if (is_array($data)) {
            $this->cookies = $data;
        }

        $this->loaded = true;
    }

    public function save(): void
    {
        $dir = dirname($this->jarPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($this->jarPath, json_encode($this->cookies, JSON_PRETTY_PRINT));
    }

    public function set(string $domain, string $name, string $value, ?string $path = '/', ?int $expires = null, bool $secure = false, bool $httpOnly = false): void
    {
        $this->load();

        $key = $this->getKey($domain, $path, $name);
        $this->cookies[$key] = [
            'name' => $name,
            'value' => $value,
            'domain' => $domain,
            'path' => $path,
            'expires' => $expires,
            'secure' => $secure,
            'http_only' => $httpOnly,
        ];

        $this->save();
    }

    public function get(string $domain, string $name, ?string $path = '/'): ?string
    {
        $this->load();

        $key = $this->getKey($domain, $path, $name);
        return $this->cookies[$key]['value'] ?? null;
    }

    public function getAllForUrl(string $url): array
    {
        $this->load();

        $parsed = parse_url($url);
        $host = $parsed['host'] ?? '';
        $path = $parsed['path'] ?? '/';

        $cookies = [];
        foreach ($this->cookies as $cookie) {
            if (!$this->matchesDomain($host, $cookie['domain'])) {
                continue;
            }

            if (!$this->matchesPath($path, $cookie['path'])) {
                continue;
            }

            if ($cookie['expires'] !== null && $cookie['expires'] < time()) {
                continue;
            }

            $cookies[$cookie['name']] = $cookie['value'];
        }

        return $cookies;
    }

    public function getCurlCookieString(string $url): string
    {
        $cookies = $this->getAllForUrl($url);
        $pairs = [];
        foreach ($cookies as $name => $value) {
            $pairs[] = "$name=$value";
        }
        return implode('; ', $pairs);
    }

    public function delete(string $domain, string $name, ?string $path = '/'): void
    {
        $this->load();

        $key = $this->getKey($domain, $path, $name);
        unset($this->cookies[$key]);

        $this->save();
    }

    public function clear(): void
    {
        $this->cookies = [];
        $this->save();
    }

    public function clearExpired(): void
    {
        $this->load();

        $now = time();
        foreach ($this->cookies as $key => $cookie) {
            if ($cookie['expires'] !== null && $cookie['expires'] < $now) {
                unset($this->cookies[$key]);
            }
        }

        $this->save();
    }

    public function getAll(): array
    {
        $this->load();
        return $this->cookies;
    }

    private function getKey(string $domain, string $path, string $name): string
    {
        return strtolower($domain) . '|' . $path . '|' . $name;
    }

    private function matchesDomain(string $host, string $cookieDomain): bool
    {
        $cookieDomain = strtolower($cookieDomain);
        $host = strtolower($host);

        if ($cookieDomain[0] === '.') {
            $cookieDomain = substr($cookieDomain, 1);
        }

        if ($host === $cookieDomain) {
            return true;
        }

        if (str_ends_with($host, '.' . $cookieDomain)) {
            return true;
        }

        return false;
    }

    private function matchesPath(string $requestPath, string $cookiePath): bool
    {
        if ($cookiePath === '/') {
            return true;
        }

        return str_starts_with($requestPath, $cookiePath);
    }

    public function getJarPath(): string
    {
        return $this->jarPath;
    }
}
