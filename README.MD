# Plugin Core

> Base framework for all SalesRender plugins

## Overview

`salesrender/plugin-core` is the foundational framework upon which every SalesRender plugin is built. It provides two
application entry points:

- **Web Application** (Slim 4) -- handles all HTTP requests to the plugin (registration, settings, batch operations,
  file uploads, info, autocomplete, etc.)
- **Console Application** (Symfony Console) -- runs CLI tasks such as cron scheduling, batch queue processing, database
  management, translations, and special request dispatching.

The package establishes a standardized bootstrap configuration pattern so that every plugin, regardless of type (macros,
logistic, chat, PBX, geocoder, etc.), follows the same initialization sequence and exposes a uniform HTTP/CLI interface
to the SalesRender platform.

## Installation

```bash
composer require salesrender/plugin-core
```

**Requirements:**
- PHP >= 7.4
- Extensions: `ext-json`

> **Note:** In practice, plugins do not depend on `plugin-core` directly. Instead, they depend on a type-specific core
> package (e.g. `salesrender/plugin-core-macros`, `salesrender/plugin-core-logistic`, `salesrender/plugin-core-chat`,
> `salesrender/plugin-core-pbx`) which itself depends on `plugin-core`. Those type-specific packages extend
> `WebAppFactory` and `ConsoleAppFactory` from this package with routes and commands specific to each plugin type.

## Architecture

### Two Application Types

| Application | Base class | Framework | Entry point |
|---|---|---|---|
| Web (HTTP) | `WebAppFactory` | Slim 4 | `public/index.php` |
| Console (CLI) | `ConsoleAppFactory` | Symfony Console | `console.php` |

Both factories extend the abstract `AppFactory`, which is responsible for:

1. Loading environment variables from the `.env` file (via `vlucas/phpdotenv`)
2. Validating required environment variables (`LV_PLUGIN_PHP_BINARY`, `LV_PLUGIN_DEBUG`, `LV_PLUGIN_QUEUE_LIMIT`,
   `LV_PLUGIN_SELF_URI`)
3. Including `bootstrap.php` from the project root -- the central configuration file for every plugin

### Namespace

All classes reside under the `SalesRender\Plugin\Core\` namespace:

```
SalesRender\Plugin\Core\
    Actions\             -- HTTP request handlers (ActionInterface implementations)
        Batch\           -- batch preparation, running, forms, options
        Settings\        -- settings read/write, access middleware
        Upload\          -- file upload handling
    Commands\            -- Symfony Console commands (CronCommand, MutexCommand)
    Components\          -- ErrorHandler
    Factories\           -- AppFactory, WebAppFactory, ConsoleAppFactory
    Helpers\             -- PathHelper (temp, public, upload directories)
    Middleware\           -- ProtectedMiddleware (JWT), LanguageMiddleware
```

## Getting Started

### Project Structure

A typical SalesRender plugin has this directory layout:

```
my-plugin/
    bootstrap.php          # Plugin configuration (DB, translations, info, settings, batch, etc.)
    console.php            # CLI entry point
    .env                   # Environment variables
    cron.txt               # (optional) Additional cron tasks
    composer.json
    db/
        database.db        # SQLite database (auto-created)
    public/
        index.php          # Web entry point
        icon.png           # Plugin icon (128x128 px, transparent background, required)
        uploaded/          # Uploaded files directory
        output/            # Output files directory
    runtime/
        *.mutex            # Mutex lock files
    lang/                  # Translation files
    src/                   # Plugin source code
    vendor/
```

### Bootstrap Configuration

Every plugin must create a `bootstrap.php` file in the project root. This file is automatically included by
`AppFactory` when either the web or console application starts. The bootstrap file configures all plugin components
in a standardized sequence.

Here is the canonical template from `bootstrap.example.php`:

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

# 1. Configure DB (for SQLite *.db file and parent directory should be writable)
Connector::config(new Medoo([
    'database_type' => 'sqlite',
    'database_file' => Path::root()->down('db/database.db')
]));

# 2. Set plugin default language
Translator::config('ru_RU');

# 3. Set permitted file extensions and max sizes (in bytes)
UploadersContainer::addDefaultUploader(new LocalUploadAction([
    'jpg' => 100 * 1024,       // Max 100 KB for *.jpg
    'zip' => 10 * 1024 * 1024, // Max 10 MB for *.zip
]));

# 4. Configure plugin info
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

# 5. Configure settings form
Settings::setForm(fn(array $context) => new Form($context));

# 6. Configure form autocompletes (optional)
AutocompleteRegistry::config(function (string $name) {
    // switch ($name) {
    //     case 'status': return new StatusAutocomplete();
    //     default: return null;
    // }
});

# 7. Configure table previews (optional)
TablePreviewRegistry::config(function (string $name) {
    // switch ($name) {
    //     case 'excel': return new ExcelTablePreview();
    //     default: return null;
    // }
});

# 8. Configure batch forms and handler (optional)
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

### Web Application (HTTP)

The web entry point creates a `WebAppFactory` instance, builds the Slim 4 application, and runs it:

```php
<?php
// public/index.php
use SalesRender\Plugin\Core\Macros\Factories\WebAppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$factory = new WebAppFactory();
$application = $factory->build();
$application->run();
```

> **Note:** Plugins use the type-specific `WebAppFactory` subclass (e.g.
> `SalesRender\Plugin\Core\Macros\Factories\WebAppFactory`), not the base one directly. The type-specific factory adds
> routes unique to that plugin type (e.g. batch actions for macros, waybill actions for logistics).

#### How WebAppFactory works

When instantiated, `WebAppFactory` calls `createBaseApp()` which:

1. Creates a new Slim 4 application
2. Adds routing middleware
3. Adds `LanguageMiddleware` (applied globally to all requests)
4. Registers base routes: `GET /info`, `PUT /registration`, `GET /robots.txt`
5. Adds settings routes (form, data read/write)
6. Adds autocomplete, table preview, markdown preview routes
7. Adds file upload routes (if configured)
8. Sets the application base path from `LV_PLUGIN_SELF_URI`

Type-specific subclasses then add additional routes via methods like `addBatchActions()`, `addSpecialRequestAction()`,
`addCors()`, `addProcessAction()`, etc.

The `build()` method finalizes the application by adding error middleware and setting up the `ErrorHandler`.

### Console Application (CLI)

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

#### How ConsoleAppFactory works

When instantiated, `ConsoleAppFactory` calls `createBaseApp()` which registers the following commands:

| Command | Source package | Description |
|---|---|---|
| `cron:run` | plugin-core | Runs scheduled cron tasks from `cron.txt` and auto-registered tasks |
| `directory:clean` | [plugin-component-directory-cleaner](https://github.com/SalesRender/plugin-component-directory-cleaner) | Cleans temporary directories |
| `db:create-tables` | [plugin-component-db](https://github.com/SalesRender/plugin-component-db) | Creates required database tables |
| `db:clean-tables` | [plugin-component-db](https://github.com/SalesRender/plugin-component-db) | Cleans old records from database tables |
| `lang:add` | [plugin-component-translations](https://github.com/SalesRender/plugin-component-translations) | Adds a new language |
| `lang:update` | [plugin-component-translations](https://github.com/SalesRender/plugin-component-translations) | Updates translation files |
| `specialRequest:queue` | [plugin-component-special-request](https://github.com/SalesRender/plugin-component-special-request) | Processes special request queue |
| `specialRequest:handle` | [plugin-component-special-request](https://github.com/SalesRender/plugin-component-special-request) | Handles a single special request |

If a batch handler is configured, the factory also auto-registers cron tasks:
- `* * * * *` -- `batch:queue` (if `BatchContainer` has a handler)
- `* * * * *` -- `specialRequest:queue` (always)

Type-specific subclasses add additional commands (e.g. `batch:queue` and `batch:handle` via `addBatchCommands()`).

#### Cron System

The `CronCommand` (`cron:run`) merges tasks from two sources:
1. The `cron.txt` file in the project root (one task per line, standard cron format)
2. Tasks registered programmatically via `CronCommand::addTask()`

Tasks are executed in parallel using Symfony Process. The command checks each task's cron expression and runs it if due.

#### MutexCommand

`MutexCommand` is an abstract base class for console commands that must not run concurrently. It provides the
`withMutex(callable $function)` method which uses file-based locking (`runtime/*.mutex`) to ensure only one instance
of the command runs at a time.

## Standard HTTP Endpoints

### Public Endpoints (no authentication)

| Method | Path | Action | Description |
|---|---|---|---|
| `GET` | `/info` | `InfoAction` | Returns plugin metadata (type, name, description, developer). Requires `public/icon.png` to exist. |
| `PUT` | `/registration` | `RegistrationAction` | Registers the plugin for a specific company. Receives a JWT token in the request body. |
| `GET` | `/robots.txt` | `RobotsActions` | Returns `User-agent: *\nDisallow: /` to block search engine indexing. |
| `GET` | `/process` | `ProcessAction` | Returns batch process status by `?id=` query parameter. |

### Protected Endpoints (JWT authentication via `ProtectedMiddleware`)

All `/protected/*` routes require the `X-PLUGIN-TOKEN` HTTP header containing a valid JWT token.

#### Settings

| Method | Path | Action | Description |
|---|---|---|---|
| `GET` | `/protected/forms/settings` | `FormAction` | Returns the settings form definition as JSON. |
| `GET` | `/protected/data/settings` | `GetSettingsDataAction` | Returns current settings data. Password fields are masked (boolean). |
| `PUT` | `/protected/data/settings` | `PutSettingsDataAction` | Saves settings data. Validates against form, handles password fields, clears redundant data. |

Settings routes are additionally protected by `SettingsAccessMiddleware` which checks the `settings` claim in the JWT
token. Access is denied with HTTP 403 if the claim is absent or `false`.

#### Batch Operations

| Method | Path | Action | Description |
|---|---|---|---|
| `POST` | `/protected/batch/prepare` | `BatchPrepareAction` | Creates a new batch with filters, sort, and arguments. Returns 409 if a batch already exists. |
| `GET` | `/protected/forms/batch/{number}` | `GetBatchFormAction` | Returns batch step form by number (1-10). Returns 425 if previous step not completed. |
| `PUT` | `/protected/data/batch/{number}` | `PutBatchOptionsAction` | Saves batch step options. Validates form data, returns 400 on validation errors. |
| `POST` | `/protected/batch/run` | `BatchRunAction` | Starts batch execution. In debug mode, runs synchronously; otherwise queues for async processing. |

#### Autocomplete

| Method | Path | Action | Description |
|---|---|---|---|
| `GET` | `/protected/autocomplete/{name}` | `AutocompleteAction` | Returns autocomplete suggestions. Accepts `?query=`, `?dep=`, `?context=` parameters. If `query` is an array, returns values; otherwise returns search results. |

#### Table Preview

| Method | Path | Action | Description |
|---|---|---|---|
| `GET` | `/protected/preview/table/{name}` | `TablePreviewAction` | Returns rendered table data. Accepts `?dep=` and `?context=` parameters. |

#### Markdown Preview

| Method | Path | Action | Description |
|---|---|---|---|
| `GET` | `/protected/preview/markdown/{name}` | `MarkdownPreviewAction` | Returns rendered markdown content. Accepts `?dep=` and `?context=` parameters. |

#### File Upload

| Method | Path | Action | Description |
|---|---|---|---|
| `POST` | `/protected/upload` | Default `UploadAction` | Uploads a file. Validates extension and size against configured permissions. Returns the uploaded file URI. |
| `POST` | `/protected/upload/{name}` | Custom `UploadAction` | Named uploader for specific file categories (e.g. `image`, `voice`). |

#### Special Requests

| Method | Path | Action | Description |
|---|---|---|---|
| `POST` | `/special/{name}` | `SpecialRequestAction` | Handles type-specific special requests. Each action self-verifies JWT and plugin registration. |

## Middleware

### ProtectedMiddleware

`SalesRender\Plugin\Core\Middleware\ProtectedMiddleware`

Applied to all `/protected/*` routes. Performs the following:

1. Extracts the JWT from the `X-PLUGIN-TOKEN` HTTP header
2. Creates a `GraphqlInputToken` instance and sets it as a singleton
3. Sets the database connector reference (`Connector::setReference()`) from the token's plugin reference
4. Verifies that the plugin is registered for the given company (checks `Registration::find()`)
5. Returns HTTP 401 if the header is missing, HTTP 403 if the token is invalid or the plugin is not registered

After successful authentication, all downstream action handlers can access the token via
`GraphqlInputToken::getInstance()`.

### LanguageMiddleware

`SalesRender\Plugin\Core\Middleware\LanguageMiddleware`

Applied globally to all HTTP requests. Performs the following:

1. Reads the `Accept-Language` HTTP header
2. Parses locale codes in `xx_XX` format (e.g., `ru_RU`, `en_US`)
3. Filters against available languages from `Translator::getLanguages()`
4. Sets the active language via `Translator::setLang()`, or falls back to the default language

### SettingsAccessMiddleware

`SalesRender\Plugin\Core\Actions\Settings\SettingsAccessMiddleware`

Applied to settings routes only (forms and data). Checks the `settings` claim in the JWT token. Returns HTTP 403 if
the caller does not have settings access.

## Actions (Request Handlers)

All action handlers implement `ActionInterface`:

```php
interface ActionInterface
{
    public function __invoke(ServerRequest $request, Response $response, array $args): Response;
}
```

### RegistrationAction

Handles `PUT /registration`. Parses the JWT from the `registration` body parameter, extracts company ID and plugin
reference, deletes any existing registration, and creates a new `Registration` record.

### InfoAction

Handles `GET /info`. Returns the plugin metadata configured via `Info::config()`. Returns HTTP 510 if the required
`public/icon.png` file does not exist.

### FormAction

A generic handler for form definition endpoints. Accepts a callable that returns a `Form` object. Passes a `context`
query parameter (JSON-decoded) to the callable. Returns 404 if the form callable returns `null`.

### FormDataAction

A generic handler for reading form data. Masks password fields (replaces values with `boolean` indicating presence).
Used by `GetSettingsDataAction`.

### GetSettingsDataAction

Handles `GET /protected/data/settings`. Extends `FormDataAction`, configured to read from `Settings::getForm()` and
`Settings::find()->getData()`.

### PutSettingsDataAction

Handles `PUT /protected/data/settings`. Validates submitted data against the settings form, preserves unchanged
password fields from existing data, clears redundant fields not defined in the form, and saves the settings.

### BatchPrepareAction

Handles `POST /protected/batch/prepare`. Creates a new `Batch` object with the provided filters, sort order, language,
and arguments. Returns HTTP 409 if a batch already exists (only one batch per plugin registration at a time).

### BatchRunAction

Handles `POST /protected/batch/run`. Creates a new `Process` record and either runs the batch handler synchronously
(in debug mode) or queues it for asynchronous processing via the cron-based queue system.

### GetBatchFormAction

Handles `GET /protected/forms/batch/{number}`. Returns the form definition for the given batch step. Validates
that the step number is 1-10, that a batch exists, and that all previous steps have been completed.

### PutBatchOptionsAction

Handles `PUT /protected/data/batch/{number}`. Validates and saves form data for the given batch step. Uses the same
guard logic as `GetBatchFormAction`.

### ProcessAction

Handles `GET /process`. Returns the current state of a batch process by its ID (passed as `?id=` query parameter).
Returns 404 if the process is not found.

### AutocompleteAction

Handles `GET /protected/autocomplete/{name}`. Resolves the autocomplete handler by name from `AutocompleteRegistry`.
If the `query` parameter is an array, calls `values()` to resolve specific values; otherwise calls `query()` to
search.

### TablePreviewAction

Handles `GET /protected/preview/table/{name}`. Resolves the table preview handler by name from
`TablePreviewRegistry` and calls `render()` with dependencies and context.

### MarkdownPreviewAction

Handles `GET /protected/preview/markdown/{name}`. Resolves the markdown preview handler by name from
`MarkdownPreviewRegistry` and calls `render()` with dependencies and context.

### SpecialRequestAction (abstract)

Base class for plugin type-specific special request handlers. Handles `POST /special/{name}`. Parses and verifies
the JWT from the `request` body parameter, sets the database reference, checks registration, and delegates to the
abstract `handle(array $body, ...)` method. Each subclass must implement `handle()` and `getName()`.

### RobotsActions

Handles `GET /robots.txt`. Returns a `robots.txt` response that disallows all crawling.

### Upload Actions

- **`UploadAction`** (abstract) -- Base class for file upload handlers. Validates file presence, extension, and size
  against configured permissions. Delegates to `handler(UploadedFile $file)`.
- **`LocalUploadAction`** -- Concrete implementation that saves files to `public/uploaded/{companyId}/{pluginId}/{uuid}.{ext}`
  and returns the public URI.
- **`UploadersContainer`** -- Static registry for upload handlers. Supports a default uploader (`addDefaultUploader()`)
  and named uploaders (`addCustomUploader(string $name, ...)`).

## Error Handling

`SalesRender\Plugin\Core\Components\ErrorHandler` implements `Slim\Interfaces\ErrorHandlerInterface` and handles all
uncaught exceptions:

| Exception type | HTTP code | Response |
|---|---|---|
| `IntegritySettingsException` | 424 | `Plugin settings should be reviewed` |
| `HttpException` | Exception's code | Exception's message |
| Any other (debug mode on) | 500 | Full details: message, file, line, trace |
| Any other (debug mode off) | 500 | `Internal plugin error` |

A custom error handler can be registered via `ErrorHandler::onErrorHandler(callable $callable)` for logging or
monitoring purposes.

## Environment Variables

The following variables are **required** in the `.env` file (validated by `AppFactory::loadEnv()`):

| Variable | Type | Description |
|---|---|---|
| `LV_PLUGIN_PHP_BINARY` | string | Path to the PHP binary (used for spawning console processes) |
| `LV_PLUGIN_DEBUG` | boolean | Enable debug mode (`true`/`false`). Shows detailed errors, runs batch synchronously |
| `LV_PLUGIN_QUEUE_LIMIT` | integer | Maximum queue size for batch processing |
| `LV_PLUGIN_SELF_URI` | string | Public URI of this plugin (used for base path and upload URLs) |

Example `.env` file:

```env
LV_PLUGIN_PHP_BINARY=/usr/bin/php
LV_PLUGIN_DEBUG=false
LV_PLUGIN_QUEUE_LIMIT=100
LV_PLUGIN_SELF_URI=https://my-plugin.example.com/plugin-path/
```

## Bootstrap Configuration Reference

| Step | Component | Method | Required |
|---|---|---|---|
| 1 | Database | `Connector::config(new Medoo([...]))` | Yes |
| 2 | Default language | `Translator::config('ru_RU')` | Yes |
| 3 | File uploads | `UploadersContainer::addDefaultUploader(new LocalUploadAction([...]))` | No |
| 4 | Plugin info | `Info::config(PluginType, name, description, purpose, Developer)` | Yes |
| 5 | Settings form | `Settings::setForm(fn($context) => new SettingsForm($context))` | Yes |
| 6 | Autocomplete | `AutocompleteRegistry::config(fn(string $name) => ...)` | No |
| 7 | Table preview | `TablePreviewRegistry::config(fn(string $name) => ...)` | No |
| 8 | Markdown preview | `MarkdownPreviewRegistry::config(fn(string $name) => ...)` | No |
| 9 | Batch | `BatchContainer::config(fn(int $number) => ..., new Handler())` | No |

### Plugin Types

The `PluginType` constant defines the plugin category:

- `PluginType::MACROS` -- macro/automation plugins
- `PluginType::LOGISTIC` -- logistics/delivery plugins
- `PluginType::CHAT` -- messaging/communication plugins
- `PluginType::PBX` -- telephony plugins
- `PluginType::GEOCODER` -- geocoding plugins

### File Upload Configuration

```php
// Default uploader: handles POST /protected/upload
UploadersContainer::addDefaultUploader(new LocalUploadAction([
    'jpg' => 1 * 1024 * 1024,   // Max 1 MB
    'png' => 2 * 1024 * 1024,   // Max 2 MB
    'zip' => 10 * 1024 * 1024,  // Max 10 MB
    '*'   => 10 * 1024 * 1024,  // Any extension, max 10 MB
]));

// Custom named uploaders: handles POST /protected/upload/{name}
UploadersContainer::addCustomUploader('image', new LocalUploadAction([
    'jpg' => 5 * 1024 * 1024,
    'png' => 5 * 1024 * 1024,
]));
```

Pass an empty array `[]` to `LocalUploadAction` to disable file uploading.

## Creating a New Plugin

### Step 1: Create the project

```bash
mkdir my-plugin && cd my-plugin
composer init
composer require salesrender/plugin-core-macros  # or the appropriate type-specific core
```

### Step 2: Create bootstrap.php

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

### Step 3: Create the web entry point

```php
<?php
// public/index.php
use SalesRender\Plugin\Core\Macros\Factories\WebAppFactory;

require_once __DIR__ . '/../vendor/autoload.php';

$factory = new WebAppFactory();
$application = $factory->build();
$application->run();
```

### Step 4: Create the console entry point

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

### Step 5: Create the .env file

```env
LV_PLUGIN_PHP_BINARY=/usr/bin/php
LV_PLUGIN_DEBUG=true
LV_PLUGIN_QUEUE_LIMIT=100
LV_PLUGIN_SELF_URI=https://my-plugin.example.com/
```

### Step 6: Create the plugin icon

Place a 128x128 pixel PNG image with a transparent background at `public/icon.png`. The `/info` endpoint will return
an error if this file is missing.

### Step 7: Create required directories

```bash
mkdir -p db public/uploaded public/output runtime
```

### Step 8: Initialize the database

```bash
php console.php db:create-tables
```

### Step 9: Set up cron

Add a cron job to run the cron command every minute:

```
* * * * * /usr/bin/php /path/to/my-plugin/console.php cron:run
```

## Helpers

### PathHelper

`SalesRender\Plugin\Core\Helpers\PathHelper` provides static methods for resolving standard directories:

```php
PathHelper::getTemp();         // {root}/temp
PathHelper::getPublic();       // {root}/public
PathHelper::getPublicOutput(); // {root}/public/output
PathHelper::getPublicUpload(); // {root}/public/uploaded
```

## CORS Support

`WebAppFactory` provides a `addCors()` method to enable Cross-Origin Resource Sharing:

```php
$factory = new WebAppFactory();
$factory->addCors('*', '*'); // Allow all origins and headers
```

This adds an `OPTIONS` catch-all route and applies CORS headers (`Access-Control-Allow-Origin`,
`Access-Control-Allow-Headers`, `Access-Control-Allow-Methods`) to all responses.

## Dependencies

| Package | Version | Purpose |
|---|---|---|
| `slim/slim` | ^4.0 | HTTP application framework |
| `slim/psr7` | ^1.2 | PSR-7 implementation |
| `slim/http` | ^1.1 | Slim HTTP decorators |
| `symfony/console` | ^5.0 | CLI application framework |
| `ramsey/uuid` | ^3.9 | UUID generation (file uploads) |
| `vlucas/phpdotenv` | ^4.1 | Environment variable loading |
| `adbario/php-dot-notation` | ^2.2 | Dot-notation array access |
| [`salesrender/plugin-component-form`](https://github.com/SalesRender/plugin-component-form) | ^0.11.1 | Form definitions, autocomplete, table/markdown preview |
| [`salesrender/plugin-component-info`](https://github.com/SalesRender/plugin-component-info) | ^0.1.1 | Plugin metadata (Info, Developer, PluginType) |
| [`salesrender/plugin-component-api-client`](https://github.com/SalesRender/plugin-component-api-client) | ^0.6.0 | API client for SalesRender platform |
| [`salesrender/plugin-component-translations`](https://github.com/SalesRender/plugin-component-translations) | ^0.1.1 | Multi-language translation support |
| [`salesrender/plugin-component-directory-cleaner`](https://github.com/SalesRender/plugin-component-directory-cleaner) | ^0.1.0 | Temporary directory cleanup |
| [`salesrender/plugin-component-settings`](https://github.com/SalesRender/plugin-component-settings) | ^0.2.15 | Plugin settings storage |
| [`salesrender/plugin-component-batch`](https://github.com/SalesRender/plugin-component-batch) | ^0.3.12 | Batch processing (queue, handler, process) |
| [`salesrender/plugin-component-request-dispatcher`](https://github.com/SalesRender/plugin-component-special-request) | ^0.3.0 | Special request queue/dispatch |
| `xakepehok/path` | ^0.2.1 | Path manipulation helper |
| `dragonmantank/cron-expression` | ^3.1 | Cron expression parsing |

## See Also

- [plugin-component-form](https://github.com/SalesRender/plugin-component-form) -- Form definitions, field types, autocomplete
- [plugin-component-info](https://github.com/SalesRender/plugin-component-info) -- Plugin metadata
- [plugin-component-settings](https://github.com/SalesRender/plugin-component-settings) -- Settings storage
- [plugin-component-batch](https://github.com/SalesRender/plugin-component-batch) -- Batch processing
- [plugin-component-translations](https://github.com/SalesRender/plugin-component-translations) -- Translation system
- [plugin-component-api-client](https://github.com/SalesRender/plugin-component-api-client) -- SalesRender API client
- [plugin-component-db](https://github.com/SalesRender/plugin-component-db) -- Database abstraction
- [plugin-component-request-dispatcher](https://github.com/SalesRender/plugin-component-request-dispatcher) -- Special request handling
- [plugin-core-macros](https://github.com/SalesRender/plugin-core-macros) -- Core for macros plugins
- [plugin-core-logistic](https://github.com/SalesRender/plugin-core-logistic) -- Core for logistic plugins
- [plugin-core-chat](https://github.com/SalesRender/plugin-core-chat) -- Core for chat plugins
- [plugin-core-pbx](https://github.com/SalesRender/plugin-core-pbx) -- Core for PBX plugins
- [plugin-macros-example](https://github.com/SalesRender/plugin-macros-example) -- Example macros plugin
- [plugin-logistic-example](https://github.com/SalesRender/plugin-logistic-example) -- Example logistic plugin
- [plugin-chat-example](https://github.com/SalesRender/plugin-chat-example) -- Example chat plugin
- [plugin-pbx-example](https://github.com/SalesRender/plugin-pbx-example) -- Example PBX plugin
