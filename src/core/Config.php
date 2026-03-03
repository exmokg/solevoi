<?php

declare(strict_types=1);

namespace SiteTool\Core;

final class Config
{
    public const MAX_FILE_SIZE = 100 * 1024 * 1024;          // 100 MB
    public const MAX_TOTAL_SIZE = 5 * 1024 * 1024 * 1024;    // 5 GB
    public const MAX_FILES_COUNT = 50000;

    public const CURL_PARALLEL_LIMIT = 15;
    public const HTML_PARALLEL_LIMIT = 5;
    public const RETRY_ATTEMPTS = 3;
    public const RETRY_BASE_DELAY = 1000; // ms

    public const CONNECT_TIMEOUT = 15;
    public const TRANSFER_TIMEOUT = 120;
    public const DNS_CACHE_TIMEOUT = 300;

    public const CLONES_DIR = __DIR__ . '/../../cloned_sites';
    public const SESSIONS_DIR = __DIR__ . '/../../tool_sessions';
    public const COOKIE_JAR_DIR = __DIR__ . '/../../cookie_jars';
    public const EXPORTS_DIR = __DIR__ . '/../../exports';
    public const TEMPLATES_DIR = __DIR__ . '/../../templates';
    public const HISTORY_FILE = __DIR__ . '/../../tool_history.json';
    public const QUEUE_DB = __DIR__ . '/../../queue.sqlite';

    public const DEFAULT_REQUEST_DELAY = 100;
    public const MIN_REQUEST_DELAY = 50;
    public const MAX_REQUEST_DELAY = 5000;
    public const ADAPTIVE_THROTTLE_THRESHOLD = 3;

    public const CSRF_TOKEN_LENGTH = 32;
    public const SESSION_LIFETIME = 86400; // 24 hours

    public const LOG_FILE = __DIR__ . '/../../app.log';
    public const LOG_MAX_SIZE = 10 * 1024 * 1024; // 10 MB
    public const LOG_BACKUP_COUNT = 5;

    public const RATE_LIMIT_WINDOW = 60; // seconds
    public const RATE_LIMIT_MAX_REQUESTS = 100;

    private static ?array $customConfig = null;

    public static function load(array $config): void
    {
        self::$customConfig = $config;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$customConfig[$key] ?? $default;
    }

    public static function all(): array
    {
        return self::$customConfig ?? [];
    }
}
