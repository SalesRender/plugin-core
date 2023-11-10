<?php
/**
 * Created for plugin-core-macros
 * Date: 10.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Middleware;


use Exception;
use SalesRender\Plugin\Components\Access\Registration\Registration;
use SalesRender\Plugin\Components\Access\Token\GraphqlInputToken;
use SalesRender\Plugin\Components\Db\Components\Connector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpException;
use Slim\Http\Response;
use Slim\Http\ServerRequest as Request;

class ProtectedMiddleware
{

    /**
     * @param Request $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws HttpException
     */
    public function __invoke(Request $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $jwt = $request->getHeader('X-PLUGIN-TOKEN')[0] ?? '';

        if (empty($jwt)) {
            throw new HttpException($request, 'X-PLUGIN-TOKEN not found', 401);
        }

        try {
            $token = new GraphqlInputToken($jwt);
            GraphqlInputToken::setInstance($token);
        } catch (Exception $exception) {
            throw new HttpException($request, $exception->getMessage(), 403);
        }

        Connector::setReference($token->getPluginReference());
        if (Registration::find() === null) {
            throw new HttpException($request, 'Plugin was not registered', 403);
        }

        /** @var Response $response */
        return $handler->handle($request);
    }

}