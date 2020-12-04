<?php
/**
 * Created for plugin-core
 * Date: 02.12.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Components;


use Leadvertex\Plugin\Components\Settings\Exceptions\IntegritySettingsException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\App;
use Slim\Exception\HttpException;
use Slim\Http\Response;
use Slim\Interfaces\ErrorHandlerInterface;
use Throwable;

class ErrorHandler implements ErrorHandlerInterface
{

    private App $app;

    /** @var callable */
    private static $onErrorHandler;

    public function __construct(App $app)
    {
        $this->app = $app;
    }

    public function __invoke(
        ServerRequestInterface $request,
        Throwable $exception,
        bool $displayErrorDetails,
        bool $logErrors,
        bool $logErrorDetails
    ): ResponseInterface
    {
        if (isset(static::$onErrorHandler)) {
            (static::$onErrorHandler)($exception, $request);
        }

        /** @var Response $response */
        $response = $this->app->getResponseFactory()->createResponse();

        if ($exception instanceof IntegritySettingsException) {
            return $response->withJson(
                [
                    'code' => 424,
                    'message' => 'Plugin settings should be reviewed'
                ],
                424
            );
        }

        if ($exception instanceof HttpException) {
            return $response->withJson(
                [
                    'code' => $exception->getCode(),
                    'message' => $exception->getMessage()
                ],
                $exception->getCode()
            );
        }

        if ($displayErrorDetails) {
            return $response->withJson(
                [
                    'code' => $exception->getCode(),
                    'message' => $exception->getMessage(),
                    'file' => $exception->getFile(),
                    'line' => $exception->getLine(),
                    'trace' => $exception->getTrace()
                ],
                500
            );
        }

        return $response->withJson(
            [
                'code' => 500,
                'message' => 'Internal plugin error',
            ],
            500
        );
    }

    public static function onErrorHandler(callable $callable): void
    {
        static::$onErrorHandler = $callable;
    }
}