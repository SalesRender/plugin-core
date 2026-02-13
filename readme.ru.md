# Plugin Core

> Базовый фреймворк для всех плагинов SalesRender

## Обзор

`salesrender/plugin-core` -- фундаментальный фреймворк, на котором строится каждый плагин SalesRender. Он предоставляет
две точки входа в приложение:

- **Web-приложение** (Slim 4) -- обрабатывает все HTTP-запросы к плагину (регистрация, настройки, пакетные операции,
  загрузка файлов, информация, автодополнение и т.д.)
- **Консольное приложение** (Symfony Console) -- выполняет CLI-задачи: cron-планирование, обработка очередей пакетных
  операций, управление базой данных, переводы, диспетчеризация специальных запросов.

Пакет устанавливает стандартизированный шаблон конфигурации через bootstrap, благодаря чему каждый плагин, вне
зависимости от типа (macros, logistic, chat, PBX, geocoder и т.д.), следует единой последовательности инициализации
и предоставляет унифицированный HTTP/CLI-интерфейс для платформы SalesRender.

## Установка

```bash
composer require salesrender/plugin-core
```

**Требования:**
- PHP >= 7.4
- Расширения: `ext-json`

> **Примечание:** На практике плагины не зависят от `plugin-core` напрямую. Вместо этого они зависят от
> типоспецифичного пакета ядра (например, `salesrender/plugin-core-macros`, `salesrender/plugin-core-logistic`,
> `salesrender/plugin-core-chat`, `salesrender/plugin-core-pbx`), который, в свою очередь, зависит от `plugin-core`.
> Такие типоспецифичные пакеты расширяют `WebAppFactory` и `ConsoleAppFactory` из данного пакета маршрутами и
> командами, специфичными для каждого типа плагинов.

## Архитектура

### Два типа приложений

| Приложение | Базовый класс | Фреймворк | Точка входа |
|---|---|---|---|
| Web (HTTP) | `WebAppFactory` | Slim 4 | `public/index.php` |
| Console (CLI) | `ConsoleAppFactory` | Symfony Console | `console.php` |

Обе фабрики наследуют абстрактный класс `AppFactory`, который отвечает за:

1. Загрузку переменных окружения из файла `.env` (через `vlucas/phpdotenv`)
2. Валидацию обязательных переменных (`LV_PLUGIN_PHP_BINARY`, `LV_PLUGIN_DEBUG`, `LV_PLUGIN_QUEUE_LIMIT`,
   `LV_PLUGIN_SELF_URI`)
3. Подключение `bootstrap.php` из корня проекта -- центрального файла конфигурации каждого плагина

### Пространство имён

Все классы находятся в пространстве имён `SalesRender\Plugin\Core\`:

```
SalesRender\Plugin\Core\
    Actions\             -- обработчики HTTP-запросов (реализации ActionInterface)
        Batch\           -- подготовка, запуск, формы и параметры пакетных операций
        Settings\        -- чтение/запись настроек, middleware доступа
        Upload\          -- обработка загрузки файлов
    Commands\            -- команды Symfony Console (CronCommand, MutexCommand)
    Components\          -- ErrorHandler
    Factories\           -- AppFactory, WebAppFactory, ConsoleAppFactory
    Helpers\             -- PathHelper (директории temp, public, upload)
    Middleware\           -- ProtectedMiddleware (JWT), LanguageMiddleware
```

## Начало работы

### Структура проекта

Типичный плагин SalesRender имеет следующую структуру каталогов:

```
my-plugin/
    bootstrap.php          # Конфигурация плагина (БД, переводы, информация, настройки, batch и т.д.)
    console.php            # Точка входа CLI
    .env                   # Переменные окружения
    cron.txt               # (опционально) Дополнительные cron-задачи
    composer.json
    db/
        database.db        # База данных SQLite (создаётся автоматически)
    public/
        index.php          # Точка входа Web
        icon.png           # Иконка плагина (128x128 px, прозрачный фон, обязательна)
        uploaded/          # Каталог загруженных файлов
        output/            # Каталог выходных файлов
    runtime/
        *.mutex            # Файлы блокировок mutex
    lang/                  # Файлы переводов
    src/                   # Исходный код плагина
    vendor/
```

### Конфигурация bootstrap

Каждый плагин должен содержать файл `bootstrap.php` в корне проекта. Этот файл автоматически подключается
классом `AppFactory` при запуске как web-, так и консольного приложения. В bootstrap-файле настраиваются все
компоненты плагина в стандартизированной последовательности.

Каноничный шаблон из `bootstrap.example.php`:

```php
<?php
use SalesRender\Plugin\Components\Batch\BatchContainer;
use SalesRender\Plugin\Components\Db\Components\Connector;
use SalesRender\Plugin\Components\Form\Autocomplete\AutocompleteRegistry;
use SalesRender\Plugin\Components\Form\TableView\TablePreviewRegistry;
use SalesRender\Plugin\Components\Info\Developer;
use SalesRender\Plugin\Components\Info\Info;
use SalesRender\Plugin\Components\Info\PluginType;
use SalesRender\Plugin\Components\Settings\Settings;
use SalesRender\Plugin\Components\Translations\Translator;
use SalesRender\Plugin\Core\Actions\Upload\LocalUploadAction;
use SalesRender\Plugin\Core\Actions\Upload\UploadersContainer;
use Medoo\Medoo;
use XAKEPEHOK\Path\Path;

# 1. Настройка БД (для SQLite файл *.db и родительский каталог должны быть доступны для записи)
Connector::config(new Medoo([
    'database_type' => 'sqlite',
    'database_file' => Path::root()->down('db/database.db')
]));

# 2. Установка языка по умолчанию
Translator::config('ru_RU');

# 3. Настройка допустимых расширений файлов и максимальных размеров (в байтах)
UploadersContainer::addDefaultUploader(new LocalUploadAction([
    'jpg' => 100 * 1024,       // Макс. 100 КБ для *.jpg
    'zip' => 10 * 1024 * 1024, // Макс. 10 МБ для *.zip
]));

# 4. Настройка информации о плагине
Info::config(
    new PluginType(PluginType::MACROS),
    fn() => Translator::get('info', 'Plugin name'),
    fn() => Translator::get('info', 'Plugin markdown description'),
    [],
    new Developer(
        'Your (company) name',
        'support.for.plugin@example.com',
        'example.com',
    )
);

# 5. Настройка формы настроек
Settings::setForm(fn(array $context) => new Form($context));

# 6. Настройка автодополнений форм (опционально)
AutocompleteRegistry::config(function (string $name) {
    // switch ($name) {
    //     case 'status': return new StatusAutocomplete();
    //     default: return null;
    // }
});

# 7. Настройка табличных предпросмотров (опционально)
TablePreviewRegistry::config(function (string $name) {
    // switch ($name) {
    //     case 'excel': return new ExcelTablePreview();
    //     default: return null;
    // }
});

# 8. Настройка форм и обработчика пакетных операций (опционально)
BatchContainer::config(
    function (int $number, array $context) {
        // switch ($number) {
        //     case 1: return new Form($context);
        //     default: return null;
        // }
    },
    new BatchHandlerInterface()
);
```

### Web-приложение (HTTP)

Точка входа для web создаёт экземпляр `WebAppFactory`, собирает Slim 4 приложение и запускает его:

```php
<?php
// public/index.php
use SalesRender\Plugin\Core\Macros\Factories\WebAppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$factory = new WebAppFactory();
$application = $factory->build();
$application->run();
```

> **Примечание:** Плагины используют типоспецифичный подкласс `WebAppFactory` (например,
> `SalesRender\Plugin\Core\Macros\Factories\WebAppFactory`), а не базовый класс напрямую. Типоспецифичная фабрика
> добавляет маршруты, уникальные для данного типа плагина (например, batch-действия для macros, действия с накладными
> для logistic).

#### Как работает WebAppFactory

При создании экземпляра `WebAppFactory` вызывает `createBaseApp()`, который:

1. Создаёт новое приложение Slim 4
2. Добавляет middleware маршрутизации
3. Добавляет `LanguageMiddleware` (применяется глобально ко всем запросам)
4. Регистрирует базовые маршруты: `GET /info`, `PUT /registration`, `GET /robots.txt`
5. Добавляет маршруты настроек (форма, чтение/запись данных)
6. Добавляет маршруты автодополнения, табличного предпросмотра, markdown-предпросмотра
7. Добавляет маршруты загрузки файлов (если настроены)
8. Устанавливает базовый путь приложения из `LV_PLUGIN_SELF_URI`

Типоспецифичные подклассы затем добавляют дополнительные маршруты через методы `addBatchActions()`,
`addSpecialRequestAction()`, `addCors()`, `addProcessAction()` и др.

Метод `build()` завершает конфигурацию приложения, добавляя middleware обработки ошибок и настраивая `ErrorHandler`.

### Консольное приложение (CLI)

```php
#!/usr/bin/env php
<?php
// console.php
use SalesRender\Plugin\Core\Macros\Factories\ConsoleAppFactory;

require __DIR__ . '/vendor/autoload.php';

$factory = new ConsoleAppFactory();
$application = $factory->build();
$application->run();
```

#### Как работает ConsoleAppFactory

При создании экземпляра `ConsoleAppFactory` вызывает `createBaseApp()`, который регистрирует следующие команды:

| Команда | Пакет-источник | Описание |
|---|---|---|
| `cron:run` | plugin-core | Запуск cron-задач из `cron.txt` и автоматически зарегистрированных задач |
| `directory:clean` | [plugin-component-directory-cleaner](https://github.com/SalesRender/plugin-component-directory-cleaner) | Очистка временных каталогов |
| `db:create-tables` | [plugin-component-db](https://github.com/SalesRender/plugin-component-db) | Создание необходимых таблиц в базе данных |
| `db:clean-tables` | [plugin-component-db](https://github.com/SalesRender/plugin-component-db) | Очистка старых записей в таблицах |
| `lang:add` | [plugin-component-translations](https://github.com/SalesRender/plugin-component-translations) | Добавление нового языка |
| `lang:update` | [plugin-component-translations](https://github.com/SalesRender/plugin-component-translations) | Обновление файлов переводов |
| `specialRequest:queue` | [plugin-component-special-request](https://github.com/SalesRender/plugin-component-special-request) | Обработка очереди специальных запросов |
| `specialRequest:handle` | [plugin-component-special-request](https://github.com/SalesRender/plugin-component-special-request) | Обработка одного специального запроса |

Если настроен обработчик пакетных операций, фабрика также автоматически регистрирует cron-задачи:
- `* * * * *` -- `batch:queue` (если `BatchContainer` имеет обработчик)
- `* * * * *` -- `specialRequest:queue` (всегда)

Типоспецифичные подклассы добавляют дополнительные команды (например, `batch:queue` и `batch:handle` через
`addBatchCommands()`).

#### Cron-система

Команда `CronCommand` (`cron:run`) объединяет задачи из двух источников:
1. Файл `cron.txt` в корне проекта (по одной задаче на строку, стандартный cron-формат)
2. Задачи, зарегистрированные программно через `CronCommand::addTask()`

Задачи выполняются параллельно с помощью Symfony Process. Команда проверяет cron-выражение каждой задачи и
запускает её, если подошло время выполнения.

#### MutexCommand

`MutexCommand` -- абстрактный базовый класс для консольных команд, которые не должны запускаться одновременно.
Он предоставляет метод `withMutex(callable $function)`, использующий файловую блокировку (`runtime/*.mutex`)
для гарантии того, что одновременно работает только один экземпляр команды.

## Стандартные HTTP-эндпоинты

### Публичные эндпоинты (без аутентификации)

| Метод | Путь | Action | Описание |
|---|---|---|---|
| `GET` | `/info` | `InfoAction` | Возвращает метаданные плагина (тип, название, описание, разработчик). Требует наличия файла `public/icon.png`. |
| `PUT` | `/registration` | `RegistrationAction` | Регистрирует плагин для конкретной компании. Принимает JWT-токен в теле запроса. |
| `GET` | `/robots.txt` | `RobotsActions` | Возвращает `User-agent: *\nDisallow: /` для блокировки индексации поисковыми системами. |
| `GET` | `/process` | `ProcessAction` | Возвращает статус batch-процесса по параметру `?id=`. |

### Защищённые эндпоинты (JWT-аутентификация через `ProtectedMiddleware`)

Все маршруты `/protected/*` требуют HTTP-заголовок `X-PLUGIN-TOKEN` с валидным JWT-токеном.

#### Настройки

| Метод | Путь | Action | Описание |
|---|---|---|---|
| `GET` | `/protected/forms/settings` | `FormAction` | Возвращает определение формы настроек в формате JSON. |
| `GET` | `/protected/data/settings` | `GetSettingsDataAction` | Возвращает текущие данные настроек. Поля паролей маскируются (boolean). |
| `PUT` | `/protected/data/settings` | `PutSettingsDataAction` | Сохраняет данные настроек. Валидирует по форме, обрабатывает пароли, удаляет избыточные данные. |

Маршруты настроек дополнительно защищены `SettingsAccessMiddleware`, который проверяет claim `settings` в JWT-токене.
Доступ отклоняется с кодом HTTP 403, если claim отсутствует или равен `false`.

#### Пакетные операции (Batch)

| Метод | Путь | Action | Описание |
|---|---|---|---|
| `POST` | `/protected/batch/prepare` | `BatchPrepareAction` | Создаёт новый batch с фильтрами, сортировкой и аргументами. Возвращает 409, если batch уже существует. |
| `GET` | `/protected/forms/batch/{number}` | `GetBatchFormAction` | Возвращает форму шага batch по номеру (1-10). Возвращает 425, если предыдущий шаг не завершён. |
| `PUT` | `/protected/data/batch/{number}` | `PutBatchOptionsAction` | Сохраняет параметры шага batch. Валидирует данные формы, возвращает 400 при ошибках. |
| `POST` | `/protected/batch/run` | `BatchRunAction` | Запускает выполнение batch. В режиме отладки выполняется синхронно; иначе ставится в очередь. |

#### Автодополнение

| Метод | Путь | Action | Описание |
|---|---|---|---|
| `GET` | `/protected/autocomplete/{name}` | `AutocompleteAction` | Возвращает варианты автодополнения. Принимает параметры `?query=`, `?dep=`, `?context=`. Если `query` -- массив, возвращает значения; иначе -- результаты поиска. |

#### Табличный предпросмотр

| Метод | Путь | Action | Описание |
|---|---|---|---|
| `GET` | `/protected/preview/table/{name}` | `TablePreviewAction` | Возвращает отрендеренные табличные данные. Принимает параметры `?dep=` и `?context=`. |

#### Markdown-предпросмотр

| Метод | Путь | Action | Описание |
|---|---|---|---|
| `GET` | `/protected/preview/markdown/{name}` | `MarkdownPreviewAction` | Возвращает отрендеренный markdown-контент. Принимает параметры `?dep=` и `?context=`. |

#### Загрузка файлов

| Метод | Путь | Action | Описание |
|---|---|---|---|
| `POST` | `/protected/upload` | `UploadAction` (по умолчанию) | Загружает файл. Валидирует расширение и размер по настроенным разрешениям. Возвращает URI загруженного файла. |
| `POST` | `/protected/upload/{name}` | `UploadAction` (именованный) | Именованный загрузчик для определённых категорий файлов (например, `image`, `voice`). |

#### Специальные запросы

| Метод | Путь | Action | Описание |
|---|---|---|---|
| `POST` | `/special/{name}` | `SpecialRequestAction` | Обработка типоспецифичных специальных запросов. Каждый action самостоятельно верифицирует JWT и регистрацию плагина. |

## Middleware

### ProtectedMiddleware

`SalesRender\Plugin\Core\Middleware\ProtectedMiddleware`

Применяется ко всем маршрутам `/protected/*`. Выполняет следующее:

1. Извлекает JWT из HTTP-заголовка `X-PLUGIN-TOKEN`
2. Создаёт экземпляр `GraphqlInputToken` и устанавливает его как singleton
3. Устанавливает ссылку коннектора БД (`Connector::setReference()`) из ссылки на плагин в токене
4. Проверяет, что плагин зарегистрирован для данной компании (вызов `Registration::find()`)
5. Возвращает HTTP 401 при отсутствии заголовка, HTTP 403 при невалидном токене или незарегистрированном плагине

После успешной аутентификации все нижестоящие обработчики могут получить токен через
`GraphqlInputToken::getInstance()`.

### LanguageMiddleware

`SalesRender\Plugin\Core\Middleware\LanguageMiddleware`

Применяется глобально ко всем HTTP-запросам. Выполняет следующее:

1. Читает HTTP-заголовок `Accept-Language`
2. Извлекает коды локалей в формате `xx_XX` (например, `ru_RU`, `en_US`)
3. Фильтрует по доступным языкам из `Translator::getLanguages()`
4. Устанавливает активный язык через `Translator::setLang()`, либо использует язык по умолчанию

### SettingsAccessMiddleware

`SalesRender\Plugin\Core\Actions\Settings\SettingsAccessMiddleware`

Применяется только к маршрутам настроек (формы и данные). Проверяет claim `settings` в JWT-токене. Возвращает
HTTP 403, если вызывающая сторона не имеет доступа к настройкам.

## Actions (обработчики запросов)

Все обработчики реализуют интерфейс `ActionInterface`:

```php
interface ActionInterface
{
    public function __invoke(ServerRequest $request, Response $response, array $args): Response;
}
```

### RegistrationAction

Обрабатывает `PUT /registration`. Парсит JWT из параметра тела запроса `registration`, извлекает идентификатор
компании и ссылку на плагин, удаляет существующую регистрацию (если есть) и создаёт новую запись `Registration`.

### InfoAction

Обрабатывает `GET /info`. Возвращает метаданные плагина, настроенные через `Info::config()`. Возвращает HTTP 510,
если отсутствует обязательный файл `public/icon.png`.

### FormAction

Универсальный обработчик для эндпоинтов определения форм. Принимает callable, возвращающий объект `Form`.
Передаёт в callable query-параметр `context` (декодированный из JSON). Возвращает 404, если callable вернул `null`.

### FormDataAction

Универсальный обработчик для чтения данных формы. Маскирует поля паролей (заменяет значения на `boolean`,
указывающий наличие пароля). Используется в `GetSettingsDataAction`.

### GetSettingsDataAction

Обрабатывает `GET /protected/data/settings`. Наследует `FormDataAction`, настроен на чтение из
`Settings::getForm()` и `Settings::find()->getData()`.

### PutSettingsDataAction

Обрабатывает `PUT /protected/data/settings`. Валидирует отправленные данные по форме настроек, сохраняет
неизменённые поля паролей из существующих данных, удаляет избыточные поля, не определённые в форме, и сохраняет
настройки.

### BatchPrepareAction

Обрабатывает `POST /protected/batch/prepare`. Создаёт новый объект `Batch` с переданными фильтрами, порядком
сортировки, языком и аргументами. Возвращает HTTP 409, если batch уже существует (одновременно допускается
только один batch на регистрацию плагина).

### BatchRunAction

Обрабатывает `POST /protected/batch/run`. Создаёт новую запись `Process` и либо запускает обработчик batch
синхронно (в режиме отладки), либо ставит его в очередь для асинхронной обработки через систему cron-очередей.

### GetBatchFormAction

Обрабатывает `GET /protected/forms/batch/{number}`. Возвращает определение формы для заданного шага batch.
Проверяет, что номер шага от 1 до 10, что batch существует, и что все предыдущие шаги завершены.

### PutBatchOptionsAction

Обрабатывает `PUT /protected/data/batch/{number}`. Валидирует и сохраняет данные формы для заданного шага batch.
Использует ту же логику защиты, что и `GetBatchFormAction`.

### ProcessAction

Обрабатывает `GET /process`. Возвращает текущее состояние batch-процесса по его идентификатору (передаётся как
query-параметр `?id=`). Возвращает 404, если процесс не найден.

### AutocompleteAction

Обрабатывает `GET /protected/autocomplete/{name}`. Разрешает обработчик автодополнения по имени из
`AutocompleteRegistry`. Если параметр `query` является массивом, вызывает `values()` для получения конкретных
значений; в противном случае вызывает `query()` для поиска.

### TablePreviewAction

Обрабатывает `GET /protected/preview/table/{name}`. Разрешает обработчик табличного предпросмотра по имени из
`TablePreviewRegistry` и вызывает `render()` с зависимостями и контекстом.

### MarkdownPreviewAction

Обрабатывает `GET /protected/preview/markdown/{name}`. Разрешает обработчик markdown-предпросмотра по имени из
`MarkdownPreviewRegistry` и вызывает `render()` с зависимостями и контекстом.

### SpecialRequestAction (абстрактный)

Базовый класс для типоспецифичных обработчиков специальных запросов. Обрабатывает `POST /special/{name}`.
Парсит и верифицирует JWT из параметра тела запроса `request`, устанавливает ссылку на базу данных, проверяет
регистрацию и делегирует обработку абстрактному методу `handle(array $body, ...)`. Каждый подкласс должен
реализовать методы `handle()` и `getName()`.

### RobotsActions

Обрабатывает `GET /robots.txt`. Возвращает ответ `robots.txt`, запрещающий индексацию.

### Upload-действия

- **`UploadAction`** (абстрактный) -- базовый класс для обработчиков загрузки файлов. Валидирует наличие файла,
  расширение и размер по настроенным разрешениям. Делегирует обработку методу `handler(UploadedFile $file)`.
- **`LocalUploadAction`** -- конкретная реализация, сохраняющая файлы в
  `public/uploaded/{companyId}/{pluginId}/{uuid}.{ext}` и возвращающая публичный URI.
- **`UploadersContainer`** -- статический реестр обработчиков загрузки. Поддерживает загрузчик по умолчанию
  (`addDefaultUploader()`) и именованные загрузчики (`addCustomUploader(string $name, ...)`).

## Обработка ошибок

`SalesRender\Plugin\Core\Components\ErrorHandler` реализует `Slim\Interfaces\ErrorHandlerInterface` и обрабатывает
все неперехваченные исключения:

| Тип исключения | HTTP-код | Ответ |
|---|---|---|
| `IntegritySettingsException` | 424 | `Plugin settings should be reviewed` |
| `HttpException` | Код исключения | Сообщение исключения |
| Любое другое (режим отладки вкл.) | 500 | Полная информация: сообщение, файл, строка, трассировка |
| Любое другое (режим отладки выкл.) | 500 | `Internal plugin error` |

Пользовательский обработчик ошибок может быть зарегистрирован через `ErrorHandler::onErrorHandler(callable $callable)`
для целей логирования или мониторинга.

## Переменные окружения

Следующие переменные **обязательны** в файле `.env` (валидируются в `AppFactory::loadEnv()`):

| Переменная | Тип | Описание |
|---|---|---|
| `LV_PLUGIN_PHP_BINARY` | string | Путь к исполняемому файлу PHP (используется для запуска консольных процессов) |
| `LV_PLUGIN_DEBUG` | boolean | Режим отладки (`true`/`false`). Показывает подробные ошибки, запускает batch синхронно |
| `LV_PLUGIN_QUEUE_LIMIT` | integer | Максимальный размер очереди для пакетной обработки |
| `LV_PLUGIN_SELF_URI` | string | Публичный URI данного плагина (используется для базового пути и URL загруженных файлов) |

Пример файла `.env`:

```env
LV_PLUGIN_PHP_BINARY=/usr/bin/php
LV_PLUGIN_DEBUG=false
LV_PLUGIN_QUEUE_LIMIT=100
LV_PLUGIN_SELF_URI=https://my-plugin.example.com/plugin-path/
```

## Справочник конфигурации bootstrap

| Шаг | Компонент | Метод | Обязателен |
|---|---|---|---|
| 1 | База данных | `Connector::config(new Medoo([...]))` | Да |
| 2 | Язык по умолчанию | `Translator::config('ru_RU')` | Да |
| 3 | Загрузка файлов | `UploadersContainer::addDefaultUploader(new LocalUploadAction([...]))` | Нет |
| 4 | Информация о плагине | `Info::config(PluginType, name, description, purpose, Developer)` | Да |
| 5 | Форма настроек | `Settings::setForm(fn($context) => new SettingsForm($context))` | Да |
| 6 | Автодополнение | `AutocompleteRegistry::config(fn(string $name) => ...)` | Нет |
| 7 | Табличный предпросмотр | `TablePreviewRegistry::config(fn(string $name) => ...)` | Нет |
| 8 | Markdown-предпросмотр | `MarkdownPreviewRegistry::config(fn(string $name) => ...)` | Нет |
| 9 | Пакетные операции | `BatchContainer::config(fn(int $number) => ..., new Handler())` | Нет |

### Типы плагинов

Константа `PluginType` определяет категорию плагина:

- `PluginType::MACROS` -- плагины макросов/автоматизации
- `PluginType::LOGISTIC` -- плагины логистики/доставки
- `PluginType::CHAT` -- плагины обмена сообщениями/коммуникации
- `PluginType::PBX` -- плагины телефонии
- `PluginType::GEOCODER` -- плагины геокодирования

### Настройка загрузки файлов

```php
// Загрузчик по умолчанию: обрабатывает POST /protected/upload
UploadersContainer::addDefaultUploader(new LocalUploadAction([
    'jpg' => 1 * 1024 * 1024,   // Макс. 1 МБ
    'png' => 2 * 1024 * 1024,   // Макс. 2 МБ
    'zip' => 10 * 1024 * 1024,  // Макс. 10 МБ
    '*'   => 10 * 1024 * 1024,  // Любое расширение, макс. 10 МБ
]));

// Именованные загрузчики: обрабатывают POST /protected/upload/{name}
UploadersContainer::addCustomUploader('image', new LocalUploadAction([
    'jpg' => 5 * 1024 * 1024,
    'png' => 5 * 1024 * 1024,
]));
```

Передайте пустой массив `[]` в `LocalUploadAction` для отключения загрузки файлов.

## Создание нового плагина

### Шаг 1: Создание проекта

```bash
mkdir my-plugin && cd my-plugin
composer init
composer require salesrender/plugin-core-macros  # или соответствующее типоспецифичное ядро
```

### Шаг 2: Создание bootstrap.php

```php
<?php
// bootstrap.php
use SalesRender\Plugin\Components\Db\Components\Connector;
use SalesRender\Plugin\Components\Info\Developer;
use SalesRender\Plugin\Components\Info\Info;
use SalesRender\Plugin\Components\Info\PluginType;
use SalesRender\Plugin\Components\Settings\Settings;
use SalesRender\Plugin\Components\Translations\Translator;
use SalesRender\Plugin\Core\Actions\Upload\LocalUploadAction;
use SalesRender\Plugin\Core\Actions\Upload\UploadersContainer;
use Medoo\Medoo;
use XAKEPEHOK\Path\Path;

require_once __DIR__ . '/vendor/autoload.php';

Connector::config(new Medoo([
    'database_type' => 'sqlite',
    'database_file' => Path::root()->down('db/database.db')
]));

Translator::config('ru_RU');

UploadersContainer::addDefaultUploader(new LocalUploadAction([
    'jpg' => 1 * 1024 * 1024,
    'png' => 2 * 1024 * 1024,
]));

Info::config(
    new PluginType(PluginType::MACROS),
    fn() => Translator::get('info', 'My Plugin Name'),
    fn() => Translator::get('info', 'My plugin description in **markdown**'),
    new PluginPurpose(
        new MacrosPluginClass(MacrosPluginClass::CLASS_HANDLER),
        new PluginEntity(PluginEntity::ENTITY_ORDER)
    ),
    new Developer(
        'My Company',
        'support@example.com',
        'example.com',
    )
);

Settings::setForm(fn($context) => new SettingsForm($context));
```

### Шаг 3: Создание точки входа web

```php
<?php
// public/index.php
use SalesRender\Plugin\Core\Macros\Factories\WebAppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$factory = new WebAppFactory();
$application = $factory->build();
$application->run();
```

### Шаг 4: Создание точки входа CLI

```php
#!/usr/bin/env php
<?php
// console.php
use SalesRender\Plugin\Core\Macros\Factories\ConsoleAppFactory;

require __DIR__ . '/vendor/autoload.php';

$factory = new ConsoleAppFactory();
$application = $factory->build();
$application->run();
```

### Шаг 5: Создание файла .env

```env
LV_PLUGIN_PHP_BINARY=/usr/bin/php
LV_PLUGIN_DEBUG=true
LV_PLUGIN_QUEUE_LIMIT=100
LV_PLUGIN_SELF_URI=https://my-plugin.example.com/
```

### Шаг 6: Создание иконки плагина

Поместите PNG-изображение размером 128x128 пикселей с прозрачным фоном в `public/icon.png`. Эндпоинт `/info`
вернёт ошибку, если этот файл отсутствует.

### Шаг 7: Создание необходимых каталогов

```bash
mkdir -p db public/uploaded public/output runtime
```

### Шаг 8: Инициализация базы данных

```bash
php console.php db:create-tables
```

### Шаг 9: Настройка cron

Добавьте cron-задачу для запуска команды cron каждую минуту:

```
* * * * * /usr/bin/php /path/to/my-plugin/console.php cron:run
```

## Вспомогательные классы

### PathHelper

`SalesRender\Plugin\Core\Helpers\PathHelper` предоставляет статические методы для получения путей к стандартным
каталогам:

```php
PathHelper::getTemp();         // {root}/temp
PathHelper::getPublic();       // {root}/public
PathHelper::getPublicOutput(); // {root}/public/output
PathHelper::getPublicUpload(); // {root}/public/uploaded
```

## Поддержка CORS

`WebAppFactory` предоставляет метод `addCors()` для включения Cross-Origin Resource Sharing:

```php
$factory = new WebAppFactory();
$factory->addCors('*', '*'); // Разрешить все источники и заголовки
```

Это добавляет маршрут-перехватчик `OPTIONS` и применяет CORS-заголовки (`Access-Control-Allow-Origin`,
`Access-Control-Allow-Headers`, `Access-Control-Allow-Methods`) ко всем ответам.

## Зависимости

| Пакет | Версия | Назначение |
|---|---|---|
| `slim/slim` | ^4.0 | HTTP-фреймворк приложения |
| `slim/psr7` | ^1.2 | Реализация PSR-7 |
| `slim/http` | ^1.1 | HTTP-декораторы Slim |
| `symfony/console` | ^5.0 | CLI-фреймворк приложения |
| `ramsey/uuid` | ^3.9 | Генерация UUID (загрузка файлов) |
| `vlucas/phpdotenv` | ^4.1 | Загрузка переменных окружения |
| `adbario/php-dot-notation` | ^2.2 | Доступ к массивам через точечную нотацию |
| [`salesrender/plugin-component-form`](https://github.com/SalesRender/plugin-component-form) | ^0.11.1 | Определения форм, автодополнение, табличный/markdown-предпросмотр |
| [`salesrender/plugin-component-info`](https://github.com/SalesRender/plugin-component-info) | ^0.1.1 | Метаданные плагина (Info, Developer, PluginType) |
| [`salesrender/plugin-component-api-client`](https://github.com/SalesRender/plugin-component-api-client) | ^0.6.0 | API-клиент платформы SalesRender |
| [`salesrender/plugin-component-translations`](https://github.com/SalesRender/plugin-component-translations) | ^0.1.1 | Поддержка многоязычных переводов |
| [`salesrender/plugin-component-directory-cleaner`](https://github.com/SalesRender/plugin-component-directory-cleaner) | ^0.1.0 | Очистка временных каталогов |
| [`salesrender/plugin-component-settings`](https://github.com/SalesRender/plugin-component-settings) | ^0.2.15 | Хранилище настроек плагина |
| [`salesrender/plugin-component-batch`](https://github.com/SalesRender/plugin-component-batch) | ^0.3.12 | Пакетная обработка (очередь, обработчик, процесс) |
| [`salesrender/plugin-component-request-dispatcher`](https://github.com/SalesRender/plugin-component-special-request) | ^0.3.0 | Очередь и диспетчеризация специальных запросов |
| `xakepehok/path` | ^0.2.1 | Вспомогательный класс для работы с путями |
| `dragonmantank/cron-expression` | ^3.1 | Парсинг cron-выражений |

## Смотрите также

- [plugin-component-form](https://github.com/SalesRender/plugin-component-form) -- Определения форм, типы полей, автодополнение
- [plugin-component-info](https://github.com/SalesRender/plugin-component-info) -- Метаданные плагина
- [plugin-component-settings](https://github.com/SalesRender/plugin-component-settings) -- Хранилище настроек
- [plugin-component-batch](https://github.com/SalesRender/plugin-component-batch) -- Пакетная обработка
- [plugin-component-translations](https://github.com/SalesRender/plugin-component-translations) -- Система переводов
- [plugin-component-api-client](https://github.com/SalesRender/plugin-component-api-client) -- API-клиент SalesRender
- [plugin-component-db](https://github.com/SalesRender/plugin-component-db) -- Абстракция базы данных
- [plugin-component-request-dispatcher](https://github.com/SalesRender/plugin-component-request-dispatcher) -- Обработка специальных запросов
- [plugin-core-macros](https://github.com/SalesRender/plugin-core-macros) -- Ядро для плагинов macros
- [plugin-core-logistic](https://github.com/SalesRender/plugin-core-logistic) -- Ядро для плагинов logistic
- [plugin-core-chat](https://github.com/SalesRender/plugin-core-chat) -- Ядро для плагинов chat
- [plugin-core-pbx](https://github.com/SalesRender/plugin-core-pbx) -- Ядро для плагинов PBX
- [plugin-macros-example](https://github.com/SalesRender/plugin-macros-example) -- Пример плагина macros
- [plugin-logistic-example](https://github.com/SalesRender/plugin-logistic-example) -- Пример плагина logistic
- [plugin-chat-example](https://github.com/SalesRender/plugin-chat-example) -- Пример плагина chat
- [plugin-pbx-example](https://github.com/SalesRender/plugin-pbx-example) -- Пример плагина PBX
