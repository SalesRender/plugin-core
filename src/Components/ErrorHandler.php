<?php
/**
 * Created for plugin-core
 * Date: 02.12.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Components;


use SalesRender\Plugin\Components\Settings\Exceptions\IntegritySettingsException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Exception\HttpException;
use Slim\Http\Response;
use Slim\Interfaces\ErrorHandlerInterface;
use Throwable;

class ErrorHandler implements ErrorHandlerInterface
{
    private Response $response;

    /** @var callable */
    private static $onErrorHandler;

    public function __construct(Response $response)
    {
        $this->response = $response;
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

        $response = $this->response;

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