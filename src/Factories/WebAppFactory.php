<?php
/**
 * Created for plugin-core
 * Date: 30.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Factories;


use SalesRender\Plugin\Components\Settings\Settings;
use SalesRender\Plugin\Core\Actions\ActionInterface;
use SalesRender\Plugin\Core\Actions\AutocompleteAction;
use SalesRender\Plugin\Core\Actions\Batch\BatchPrepareAction;
use SalesRender\Plugin\Core\Actions\Batch\BatchRunAction;
use SalesRender\Plugin\Core\Actions\Batch\GetBatchFormAction;
use SalesRender\Plugin\Core\Actions\Batch\PutBatchOptionsAction;
use SalesRender\Plugin\Core\Actions\FormAction;
use SalesRender\Plugin\Core\Actions\InfoAction;
use SalesRender\Plugin\Core\Actions\MarkdownPreviewAction;
use SalesRender\Plugin\Core\Actions\ProcessAction;
use SalesRender\Plugin\Core\Actions\RegistrationAction;
use SalesRender\Plugin\Core\Actions\RobotsActions;
use SalesRender\Plugin\Core\Actions\Settings\GetSettingsDataAction;
use SalesRender\Plugin\Core\Actions\Settings\PutSettingsDataAction;
use SalesRender\Plugin\Core\Actions\Settings\SettingsAccessMiddleware;
use SalesRender\Plugin\Core\Actions\SpecialRequestAction;
use SalesRender\Plugin\Core\Actions\TablePreviewAction;
use SalesRender\Plugin\Core\Actions\Upload\UploadersContainer;
use SalesRender\Plugin\Core\Components\ErrorHandler;
use SalesRender\Plugin\Core\Middleware\LanguageMiddleware;
use SalesRender\Plugin\Core\Middleware\ProtectedMiddleware;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Http\Response;
use Slim\Http\ServerRequest as Request;

abstract class WebAppFactory extends AppFactory
{

    protected App $app;

    protected ProtectedMiddleware $protected;

    private array $actions = [];

    public function __construct()
    {
        parent::__construct();
        $this->protected = new ProtectedMiddleware();
        $this->createBaseApp();
    }

    public function addSettingsActions(): self
    {
        $this->addForm(
            'settings',
            fn(array $context) => Settings::getForm($context),
            new PutSettingsDataAction(),
            new GetSettingsDataAction(),
            new SettingsAccessMiddleware()
        );

        return $this;
    }

    public function addBatchActions(): self
    {
        if (!$this->registerActions(__METHOD__)) {
            return $this;
        }

        $this->app
            ->post('/protected/batch/prepare', BatchPrepareAction::class)
            ->add($this->protected);

        $this->app
            ->get('/protected/forms/batch/{number:[\d]+}', GetBatchFormAction::class)
            ->add($this->protected);

        $this->app
            ->put('/protected/data/batch/{number:[\d]+}', PutBatchOptionsAction::class)
            ->add($this->protected);

        $this->app
            ->post('/protected/batch/run', BatchRunAction::class)
            ->add($this->protected);

        $this->addProcessAction();
        $this->addAutocompleteAction();
        $this->addTablePreviewAction();
        $this->addMarkdownPreviewAction();

        return $this;
    }

    public function addAutocompleteAction(): self
    {
        if (!$this->registerActions(__METHOD__)) {
            return $this;
        }

        $this->app
            ->get('/protected/autocomplete/{name}', AutocompleteAction::class)
            ->add($this->protected);

        return $this;
    }

    public function addTablePreviewAction(): self
    {
        if (!$this->registerActions(__METHOD__)) {
            return $this;
        }

        $this->app
            ->get('/protected/preview/table/{name}', TablePreviewAction::class)
            ->add($this->protected);

        return $this;
    }

    public function addMarkdownPreviewAction(): self
    {
        if (!$this->registerActions(__METHOD__)) {
            return $this;
        }

        $this->app
            ->get('/protected/preview/markdown/{name}', MarkdownPreviewAction::class)
            ->add($this->protected);

        return $this;
    }

    public function addProcessAction(): self
    {
        if (!$this->registerActions(__METHOD__)) {
            return $this;
        }

        $this->app->get('/process', ProcessAction::class);
        return $this;
    }

    public function addSpecialRequestAction(SpecialRequestAction $action): self
    {
        if (!$this->registerActions(__METHOD__ . '::' . $action->getName())) {
            return $this;
        }

        $this->app->post("/special/{$action->getName()}", $action);

        return $this;
    }

    public function addCors(string $origin = '*', string $headers = '*'): self
    {
        if (!$this->registerActions(__METHOD__)) {
            return $this;
        }

        $this->app->options('/{routes:.+}', function ($request, $response) {
            return $response;
        });

        $this->app->add(function (Request $request, RequestHandlerInterface $handler) use ($origin, $headers) {
            /** @var Response $response */
            $response = $handler->handle($request);
            return $this->addCorsHeaders($response, $origin, $headers);
        });
        return $this;
    }

    public function build(): App
    {
        $app = $this->app;
        $errorMiddleware = $app->addErrorMiddleware($_ENV['LV_PLUGIN_DEBUG'] ?? false, true, true);

        /** @var Response $response */
        $response = $app->getResponseFactory()->createResponse();
        $handler = new ErrorHandler($this->addCorsHeaders($response));

        $errorMiddleware->setDefaultErrorHandler($handler);

        $this->createBaseApp();

        return $app;
    }

    protected function addUploaders(): self
    {
        if (!$this->registerActions(__METHOD__)) {
            return $this;
        }

        $uploaders = UploadersContainer::getUploaders();
        if (empty($uploaders)) {
            return $this;
        }

        foreach ($uploaders as $name => $action) {
            $this->app
                ->post("/protected/upload" . $name, $action)
                ->add($this->protected);
        }

        return $this;
    }

    protected function addCorsHeaders(Response $response, string $origin = '*', string $headers = '*'): Response
    {
        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Headers', $headers)
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, OPTIONS');
    }

    protected function addForm(string $name, callable $form, ActionInterface $put, ?ActionInterface $get = null, ?callable $middleware = null): self
    {
        if (!$this->registerActions($name)) {
            return $this;
        }

        $formRoute = $this->app->get("/protected/forms/{$name}", new FormAction($form));
        if ($middleware) {
            $formRoute = $formRoute->add($middleware);
        }
        $formRoute->add($this->protected);

        $formPutRoute = $this->app->put("/protected/data/{$name}", $put);
        if ($middleware) {
            $formPutRoute = $formPutRoute->add($middleware);
        }
        $formPutRoute->add($this->protected);

        if ($get) {
            $formGetRoute = $this->app->get("/protected/data/{$name}", $get);
            if ($middleware) {
                $formGetRoute = $formGetRoute->add($middleware);
            }
            $formGetRoute->add($this->protected);
        }

        $this->addAutocompleteAction();

        return $this;
    }

    protected function createBaseApp(): App
    {
        $this->app = \Slim\Factory\AppFactory::create();
        $this->app->addRoutingMiddleware();
        $this->app->add(new LanguageMiddleware());

        $this->app->get('/info', InfoAction::class);
        $this->app->put('/registration', RegistrationAction::class);
        $this->app->get('/robots.txt', RobotsActions::class);

        $this->addSettingsActions();
        $this->addAutocompleteAction();
        $this->addTablePreviewAction();
        $this->addMarkdownPreviewAction();
        $this->addUploaders();

        $this->app->setBasePath((function () {
            return rtrim(parse_url($_ENV['LV_PLUGIN_SELF_URI'], PHP_URL_PATH), '/');
        })());

        return $this->app;
    }

    private function registerActions(string $identity): bool
    {
        if (isset($this->actions[$identity])) {
            return false;
        }

        $this->actions[$identity] = true;
        return true;
    }

}