<?php
/**
 * Created for plugin-core
 * Date: 30.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Factories;


use Leadvertex\Plugin\Core\Actions\Batch\BatchPrepareAction;
use Leadvertex\Plugin\Core\Actions\Batch\BatchRunAction;
use Leadvertex\Plugin\Core\Actions\Batch\GetBatchFormAction;
use Leadvertex\Plugin\Core\Actions\Batch\PutBatchOptionsAction;
use Leadvertex\Plugin\Core\Actions\InfoAction;
use Leadvertex\Plugin\Core\Actions\ProcessAction;
use Leadvertex\Plugin\Core\Actions\RegistrationAction;
use Leadvertex\Plugin\Core\Actions\RobotsActions;
use Leadvertex\Plugin\Core\Actions\Settings\GetSettingsDataAction;
use Leadvertex\Plugin\Core\Actions\Settings\GetSettingsFormAction;
use Leadvertex\Plugin\Core\Actions\Settings\PutSettingsDataAction;
use Leadvertex\Plugin\Core\Actions\UploadAction;
use Leadvertex\Plugin\Core\Middleware\ProtectedMiddleware;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\App;
use Slim\Http\Response;
use Slim\Http\ServerRequest as Request;

abstract class WebAppFactory extends AppFactory
{

    protected App $app;

    protected ProtectedMiddleware $protected;

    public function __construct()
    {
        parent::__construct();
        $this->protected = new ProtectedMiddleware();
        $this->app = $this->createBaseApp();
    }

    public function addUploadAction(): self
    {
        $this->app
            ->post('/protected/upload', UploadAction::class)
            ->add($this->protected);

        return $this;
    }

    public function addAutocompleteAction(): self
    {
        $this->app
            ->post('/protected/upload', UploadAction::class)
            ->add($this->protected);

        return $this;
    }

    public function addBatchActions(): self
    {
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

        return $this;
    }

    public function addProcessAction(): self
    {
        $this->app->get('/process', ProcessAction::class);
        return $this;
    }

    public function addCors(string $origin = '*', string $headers = '*'): self
    {
        $this->app->options('/{routes:.+}', function ($request, $response) {
            return $response;
        });

        $this->app->add(function (Request $request, RequestHandlerInterface $handler) use ($origin, $headers) {
            /** @var Response $response */
            $response = $handler->handle($request);
            return $response
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Headers', $headers)
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, OPTIONS');
        });
        return $this;
    }


    public function build(): App
    {
        $app = $this->app;
        $this->app = $this->createBaseApp();
        return $app;
    }

    protected function createBaseApp(): App
    {
        $app = \Slim\Factory\AppFactory::create();
        $app->addRoutingMiddleware();

        //todo cors
        $errorMiddleware = $app->addErrorMiddleware($_ENV['LV_PLUGIN_DEBUG'] ?? false, true, true);
        $errorHandler = $errorMiddleware->getDefaultErrorHandler();
        $errorHandler->forceContentType('application/json');

        $app->get('/info', InfoAction::class);
        $app->put('/registration', RegistrationAction::class);

        $app->get('/protected/forms/settings', GetSettingsFormAction::class)->add($this->protected);
        $app->get('/protected/data/settings', GetSettingsDataAction::class)->add($this->protected);
        $app->put('/protected/data/settings', PutSettingsDataAction::class)->add($this->protected);

        $app->get('/robots.txt', RobotsActions::class);

        $app->setBasePath((function () {
            return rtrim(parse_url($_ENV['LV_PLUGIN_SELF_URI'], PHP_URL_PATH), '/');
        })());

        return $app;
    }

}