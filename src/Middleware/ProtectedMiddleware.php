<?php
/**
 * Created for plugin-core-macros
 * Date: 10.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Middleware;


use Exception;
use Leadvertex\Plugin\Components\Access\Registration\Registration;
use Leadvertex\Plugin\Components\Access\Token\GraphqlInputToken;
use Leadvertex\Plugin\Components\Db\Components\Connector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest as Request;

class ProtectedMiddleware
{

    public function __invoke(Request $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $jwt = $request->getHeader('')[0] ?? '';

        /** @var Response $response */
        $response = $handler->handle($request);

        if (empty($jwt)) {
            return $response->withJson([
                'error' => 'X-PLUGIN-TOKEN not found'
            ], 403);
        }

        try {
            $token = new GraphqlInputToken($jwt);
        } catch (Exception $exception) {
            return $response->withJson([
                'error' => $exception->getMessage()
            ], 403);
        }

        Connector::setReference($token->getPluginReference());
        if (Registration::find() === null) {
            return $response->withJson([
                'error' => 'Plugin was not registered'
            ], 403);
        }

        return $response;
    }

}