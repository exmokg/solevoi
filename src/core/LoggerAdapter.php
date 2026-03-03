<?php

declare(strict_types=1);

namespace SiteTool\Core;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;

final class LoggerAdapter implements LoggerInterface
{
    private string $logFile;
    private int $maxSize;
    private int $backupCount;
    private bool $jsonFormat;

    public function __construct(
        string $logFile = null,
        int $maxSize = null,
        int $backupCount = null,
        bool $jsonFormat = true
    ) {
        $this->logFile = $logFile ?? Config::LOG_FILE;
        $this->maxSize = $maxSize ?? Config::LOG_MAX_SIZE;
        $this->backupCount = $backupCount ?? Config::LOG_BACKUP_COUNT;
        $this->jsonFormat = $jsonFormat;
    }

    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, string|Stringable $message, array $context = []): void
    {
        $this->rotateIfNeeded();

        $entry = $this->jsonFormat 
            ? $this->formatJson($level, $message, $context)
            : $this->formatText($level, $message, $context);

        file_put_contents($this->logFile, $entry . PHP_EOL, FILE_APPEND | LOCK_EX);
    }

    private function formatJson(string $level, string|Stringable $message, array $context): string
    {
        $data = [
            'timestamp' => date('c'),
            'level' => $level,
            'message' => (string) $message,
            'context' => $context,
        ];

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function formatText(string $level, string|Stringable $message, array $context): string
    {
        $contextStr = $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        return sprintf(
            '[%s] %s: %s%s',
            date('Y-m-d H:i:s'),
            strtoupper($level),
            (string) $message,
            $contextStr
        );
    }

    private function rotateIfNeeded(): void
    {
        if (!file_exists($this->logFile)) {
            return;
        }

        if (filesize($this->logFile) < $this->maxSize) {
            return;
        }

        for ($i = $this->backupCount - 1; $i >= 1; $i--) {
            $oldFile = $this->logFile . '.' . $i;
            $newFile = $this->logFile . '.' . ($i + 1);
            if (file_exists($oldFile)) {
                rename($oldFile, $newFile);
            }
        }

        rename($this->logFile, $this->logFile . '.1');
    }
}