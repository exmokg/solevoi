# Список файлов проекта

## Исходный код (src/)

### Core (src/Core/)
- **Config.php** - Конфигурация приложения с константами и методами загрузки пользовательских настроек
- **LoggerAdapter.php** - Адаптер логирования с поддержкой JSON и текстового форматов, ротацией логов

### Http (src/Http/)
- **HttpRequest.php** - Класс для представления HTTP запроса с поддержкой иммутабельности
- **HttpResponse.php** - Класс для представления HTTP ответа с методами проверки статусов
- **HttpClient.php** - HTTP клиент с поддержкой retry policy, circuit breaker, adaptive throttle
- **CurlMultiPool.php** - Пул для параллельных запросов через curl_multi

### Retry (src/Retry/)
- **RetryPolicy.php** - Политика повторных попыток с экспоненциальным backoff и jitter
- **CircuitBreaker.php** - Circuit breaker для защиты от каскадных сбоев
- **AdaptiveThrottle.php** - Адаптивный троттлинг на основе успешных/неудачных запросов

### Storage (src/Storage/)
- **CookieJar.php** - Менеджер cookies с файловым хранилищем и поддержкой доменов/путей

### Queue (src/Queue/)
- **QueueSqlite.php** - Очередь задач на SQLite с поддержкой приоритетов, повторов и статусов

### Worker (src/Worker/)
- **Worker.php** - CLI worker для обработки задач из очереди с поддержкой сигналов

### Web (src/Web/)
- **Panel.php** - Web панель с HTML интерфейсом и API endpoints

### Security (src/Security/)
- **CsrfProtection.php** - Защита от CSRF атак с генерацией и валидацией токенов
- **Rbac.php** - Role-Based Access Control для управления правами доступа
- **RateLimiter.php** - Ограничение частоты запросов с хранением в файле

### Export (src/Export/)
- **StorageInterface.php** - Интерфейс для хранилища данных
- **FileStorage.php** - Реализация файлового хранилища
- **ExportManager.php** - Менеджер экспорта в CSV, JSON и ZIP

### Hooks (src/Hooks/)
- **EventDispatcher.php** - Диспетчер событий для реализации хуков и плагинов

### Metrics (src/Metrics/)
- **MetricsCollector.php** - Сборщик метрик (счётчики, таймеры, гистограммы)

## Тесты (tests/)

### Unit тесты (tests/Unit/)
- **RetryPolicyTest.php** - Тесты для RetryPolicy
- **CircuitBreakerTest.php** - Тесты для CircuitBreaker
- **CookieJarTest.php** - Тесты для CookieJar
- **QueueSqliteTest.php** - Тесты для QueueSqlite

## Деплой файлы

### Composer
- **composer.json** - Конфигурация Composer с зависимостями и автозагрузкой

### PHPUnit
- **phpunit.xml** - Конфигурация PHPUnit для запуска тестов

### GitHub Actions
- **.github/workflows/ci.yml** - CI/CD пайплайн для тестирования и линтинга

### Docker
- **Dockerfile** - Docker образ для запуска приложения
- **docker-compose.yml** - Docker Compose конфигурация для app и worker сервисов

### Nginx
- **nginx.conf** - Конфигурация Nginx для веб-сервера

### Systemd
- **site_tool.service** - Systemd сервис для запуска worker

## Документация

- **README.md** - Полная документация проекта с инструкциями по установке и использованию
- **FILES.md** - Этот файл с описанием структуры проекта

## Конфигурация

- **.gitignore** - Исключаемые файлы для Git

## Адаптер

- **graber.php** - Рефакторированный оригинальный файл, использующий новые модули

## Итого создано файлов: 35

### Структура директорий:
```
.
├── src/
│   ├── Core/
│   ├── Http/
│   ├── Retry/
│   ├── Storage/
│   ├── Queue/
│   ├── Worker/
│   ├── Web/
│   ├── Security/
│   ├── Export/
│   ├── Hooks/
│   └── Metrics/
├── tests/
│   └── Unit/
├── .github/
│   └── workflows/
├── composer.json
├── phpunit.xml
├── Dockerfile
├── docker-compose.yml
├── nginx.conf
├── site_tool.service
├── README.md
├── FILES.md
├── .gitignore
└── graber.php
```