# Site Tool V6.0 Ultimate

Универсальный инструмент для клонирования сайтов и извлечения данных.

## Возможности

- ✅ Полное клонирование сайтов (HTML, CSS, JS, изображения, шрифты и др.)
- ✅ Извлечение телефонов, email, соцсетей, объявлений
- ✅ Парсинг медиа-файлов (изображения, видео, аудио, документы)
- ✅ Визуализация структуры сайта (граф/дерево)
- ✅ Экспорт в CSV/JSON, создание ZIP-архивов
- ✅ Поддержка прокси, HTTP-авторизации, куки
- ✅ Адаптивная троттлинг и Circuit Breaker
- ✅ Очередь задач на SQLite
- ✅ CLI Worker для фоновой обработки
- ✅ JSON-логирование с ротацией
- ✅ Метрики и хуки/плагины
- ✅ Защита: CSRF, RBAC, Rate Limiting

## Требования

- PHP >= 8.1
- Расширения: curl, json, sqlite3, pcntl
- Composer

## Установка

### Клонирование репозитория

```bash
git clone https://github.com/yourusername/sitetool.git
cd sitetool
```

### Установка зависимостей

```bash
composer install
```

### Создание директорий

```bash
mkdir -p cloned_sites tool_sessions cookie_jars exports logs
```

## Использование

### Web интерфейс

Запустите встроенный PHP сервер:

```bash
php -S localhost:8080
```

Откройте в браузере: `http://localhost:8080`

### CLI интерфейс

#### Запуск worker

```bash
php graber.php worker
```

#### Запуск worker с ограничением задач

```bash
php graber.php worker 100
```

#### Статистика очереди

```bash
php graber.php queue:stats
```

#### Очистка очереди

```bash
php graber.php queue:clear
```

#### Просмотр метрик

```bash
php graber.php metrics
```

#### Справка

```bash
php graber.php help
```

## Конфигурация

### Переменные окружения

```bash
# Прокси
export HTTP_PROXY=http://proxy.example.com:8080

# HTTP авторизация
export HTTP_AUTH=username:password
```

### Настройка через Config

```php
use SiteTool\Core\Config;

// Загрузка пользовательской конфигурации
Config::load([
    'max_file_size' => 200 * 1024 * 1024, // 200 MB
    'curl_parallel_limit' => 20,
    'retry_attempts' => 5,
]);
```

## Архитектура

### Модули

- **Core**: Config, LoggerAdapter
- **Http**: HttpClient, HttpRequest, HttpResponse, CurlMultiPool
- **Retry**: RetryPolicy, CircuitBreaker, AdaptiveThrottle
- **Storage**: CookieJar
- **Queue**: QueueSqlite
- **Worker**: Worker
- **Web**: Panel
- **Security**: CsrfProtection, Rbac, RateLimiter
- **Export**: StorageInterface, FileStorage, ExportManager
- **Hooks**: EventDispatcher
- **Metrics**: MetricsCollector

## Тестирование

### Запуск всех тестов

```bash
composer test
```

### Запуск конкретного теста

```bash
vendor/bin/phpunit tests/Unit/RetryPolicyTest.php
```

### Статический анализ

```bash
composer analyze
```

## Деплой

### Docker

#### Сборка образа

```bash
docker build -t sitetool .
```

#### Запуск через docker-compose

```bash
docker-compose up -d
```

### Systemd сервис

#### Установка сервиса

```bash
sudo cp site_tool.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable site_tool
sudo systemctl start site_tool
```

#### Управление сервисом

```bash
sudo systemctl status site_tool
sudo systemctl stop site_tool
sudo systemctl restart site_tool
sudo journalctl -u site_tool -f
```

### Nginx

```bash
sudo cp nginx.conf /etc/nginx/sites-available/sitetool
sudo ln -s /etc/nginx/sites-available/sitetool /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## API

### Web API endpoints

#### Preflight проверка URL

```
GET /graber.php?action=preflight&url=https://example.com
```

#### Запуск клонирования

```
POST /graber.php?action=start
Content-Type: application/x-www-form-urlencoded

url=https://example.com&options={"images":true,"css":true}&csrf_token=TOKEN
```

#### Статус задачи

```
GET /graber.php?action=status&job_id=123
```

#### Скачивание экспорта

```
GET /graber.php?action=download&key=exports/file.zip
```

#### Метрики

```
GET /graber.php?action=metrics
```

## Безопасность

### CSRF защита

Все POST запросы должны содержать валидный CSRF токен:

```php
$csrf = new CsrfProtection();
$token = $csrf->getToken();
```

### Rate Limiting

Настройка ограничений:

```php
$rateLimiter = new RateLimiter(
    window: 60,        // 60 секунд
    maxRequests: 100   // максимум 100 запросов
);

if (!$rateLimiter->check($ip)) {
    http_response_code(429);
    echo "Rate limit exceeded";
}
```

### RBAC

Настройка прав доступа:

```php
$rbac = new Rbac();
$rbac->assignRole('user123', 'admin');

if ($rbac->can('user123', 'delete', 'site')) {
    // Разрешено
}
```

## Метрики

### Сбор метрик

```php
$metrics = new MetricsCollector();

// Счётчики
$metrics->increment('requests.total');
$metrics->increment('pages.cloned', tags: ['domain' => 'example.com']);

// Таймеры
$metrics->time('request.duration', function() {
    // код для замера
});

// Получение статистики
$stats = $metrics->getHistogramStats('request.duration');
```

## Хуки и плагины

### Регистрация обработчиков событий

```php
$dispatcher = new EventDispatcher();

$dispatcher->on('job.started', function($event, $data) {
    echo "Job started: " . $data['job']['id'] . "\n";
});

$dispatcher->on('job.completed', function($event, $data) {
    // Обработка завершения задачи
});

// Диспетчеризация события
$dispatcher->dispatch('custom.event', ['key' => 'value']);
```

## Логирование

### JSON формат (по умолчанию)

```json
{
  "timestamp": "2024-01-01T12:00:00+00:00",
  "level": "info",
  "message": "Request completed",
  "context": {
    "url": "https://example.com",
    "duration": 1.234
  }
}
```

### Текстовый формат

```php
$logger = new LoggerAdapter(jsonFormat: false);
```

## Лицензия

MIT License

## Поддержка

Для вопросов и предложений создайте issue на GitHub.