<?php

declare(strict_types=1);

/**
 * ╔══════════════════════════════════════════════════════════════════════════════╗
 * ║                    SITE TOOL V6.0 ULTIMATE                                  ║
 * ╠══════════════════════════════════════════════════════════════════════════════╣
 * ║  Универсальный инструмент: клонирование сайтов + извлечение данных          ║
 * ║  ─────────────────────────────────────────────────────────────────────────  ║
 * ║  ✓ Полное клонирование (HTML, CSS, JS, изображения, шрифты и др.)           ║
 * ║  ✓ Извлечение телефонов, email, соцсетей, объявлений                         ║
 * ║  ✓ Парсинг медиа-файлов (изображения, видео, аудио, документы)              ║
 * ║  ✓ Визуализация структуры сайта (граф/дерево)                               ║
 * ║  ✓ Экспорт в CSV/JSON, создание ZIP-архива                                   ║
 * ║  ✓ Прокси, HTTP-авторизация, куки, кэширование                              ║
 * ╚══════════════════════════════════════════════════════════════════════════════╝
 * 
 * Рефакторинг: Модульная архитектура с использованием новых классов
 */

require_once __DIR__ . '/vendor/autoload.php';

use SiteTool\Core\Config;
use SiteTool\Core\LoggerAdapter;
use SiteTool\Http\HttpClient;
use SiteTool\Http\HttpRequest;
use SiteTool\Http\HttpResponse;
use SiteTool\Http\CurlMultiPool;
use SiteTool\Retry\RetryPolicy;
use SiteTool\Retry\CircuitBreaker;
use SiteTool\Retry\AdaptiveThrottle;
use SiteTool\Storage\CookieJar;
use SiteTool\Queue\QueueSqlite;
use SiteTool\Worker\Worker;
use SiteTool\Web\Panel;
use SiteTool\Security\CsrfProtection;
use SiteTool\Security\RateLimiter;
use SiteTool\Export\FileStorage;
use SiteTool\Export\ExportManager;
use SiteTool\Hooks\EventDispatcher;
use SiteTool\Metrics\MetricsCollector;

// =============================================================================
// ИНИЦИАЛИЗАЦИЯ
// =============================================================================

$logger = new LoggerAdapter();
$retryPolicy = new RetryPolicy();
$circuitBreaker = new CircuitBreaker();
$throttle = new AdaptiveThrottle();
$cookieJar = new CookieJar();
$queue = new QueueSqlite();
$storage = new FileStorage(Config::CLONES_DIR);
$exportManager = new ExportManager($logger, $storage);
$dispatcher = new EventDispatcher();
$metrics = new MetricsCollector();
$csrf = new CsrfProtection();
$rateLimiter = new RateLimiter();

$httpClient = new HttpClient(
    $logger,
    $retryPolicy,
    $circuitBreaker,
    $throttle,
    proxy: $_ENV['HTTP_PROXY'] ?? null,
    auth: $_ENV['HTTP_AUTH'] ?? null
);

$panel = new Panel(
    $logger,
    $csrf,
    $rateLimiter,
    $queue,
    $exportManager,
    $metrics
);

// =============================================================================
// CLI ИНТЕРФЕЙС
// =============================================================================

if (php_sapi_name() === 'cli') {
    $command = $argv[1] ?? 'help';

    switch ($command) {
        case 'worker':
            $worker = new Worker($logger, $queue, $dispatcher);
            
            $worker->registerHandler('clone_site', function ($payload, $job) use ($httpClient, $logger, $metrics) {
                $url = $payload['url'];
                $options = $payload['options'] ?? [];
                $sessionId = $payload['session_id'] ?? uniqid();
                
                $logger->info('Starting site clone', ['url' => $url, 'session' => $sessionId]);
                
                $request = new HttpRequest($url);
                $response = $httpClient->send($request);
                
                if (!$response->isSuccessful()) {
                    throw new \RuntimeException("Failed to fetch {$url}: {$response->getStatusCode()}");
                }
                
                $metrics->increment('pages.cloned', tags: ['session' => $sessionId]);
                
                return [
                    'url' => $url,
                    'status' => 'success',
                    'size' => $response->getSize(),
                ];
            });
            
            $maxJobs = isset($argv[2]) ? (int)$argv[2] : null;
            $worker->start($maxJobs);
            break;

        case 'queue:stats':
            $stats = $queue->getStats();
            echo json_encode($stats, JSON_PRETTY_PRINT) . PHP_EOL;
            break;

        case 'queue:clear':
            $queue->clear();
            echo "Queue cleared" . PHP_EOL;
            break;

        case 'metrics':
            echo json_encode($metrics->getAllMetrics(), JSON_PRETTY_PRINT) . PHP_EOL;
            break;

        case 'help':
        default:
            echo "Site Tool V6.0 Ultimate - CLI Interface\n\n";
            echo "Commands:\n";
            echo "  worker [max_jobs]    Start queue worker\n";
            echo "  queue:stats          Show queue statistics\n";
            echo "  queue:clear          Clear queue\n";
            echo "  metrics              Show metrics\n";
            echo "  help                 Show this help\n";
            break;
    }
    exit(0);
}

// =============================================================================
// WEB ИНТЕРФЕЙС
// =============================================================================

$panel->handleRequest();