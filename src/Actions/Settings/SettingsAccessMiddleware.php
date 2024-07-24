<?php
/**
 * Created for plugin-core
 * Date: 24.07.2024 17:55
 * @author: Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Actions\Settings;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SalesRender\Plugin\Components\Access\Token\GraphqlInputToken;
use Slim\Exception\HttpException;
use Slim\Http\Response;
use Slim\Http\ServerRequest as Request;

class SettingsAccessMiddleware
{

    /**
     * @param Request $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws HttpException
     */
    public function __invoke(Request $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $isSettingsAllowed = GraphqlInputToken::getInstance()->getInputToken()->getClaim('settings', false);
        if (!$isSettingsAllowed) {
            throw new HttpException($request, 'Access to settings is not allowed', 403);
        }

        /** @var Response $response */
        return $handler->handle($request);
    }

}