<?php
/**
 * Created for plugin-core
 * Date: 05.10.2021
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Actions;

use Lcobucci\JWT\Parser;
use SalesRender\Plugin\Components\Access\PublicKey\PublicKey;
use SalesRender\Plugin\Components\Access\Registration\Registration;
use SalesRender\Plugin\Components\Db\Components\Connector;
use SalesRender\Plugin\Components\Db\Components\PluginReference;
use Slim\Exception\HttpException;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Throwable;

abstract class SpecialRequestAction implements ActionInterface
{

    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        try {
            $claims = $this->getJwtClaims($request);
        } catch (Throwable $throwable) {
            throw new HttpException($request, $throwable->getMessage(), 403);
        }

        Connector::setReference(new PluginReference(
            $claims['cid'],
            $claims['plugin']['alias'],
            $claims['plugin']['id'],
        ));

        if (Registration::find() === null) {
            throw new HttpException($request, 'Plugin was not registered', 403);
        }

        return $this->handle($claims['body'], $request, $response, $args);
    }

    abstract protected function handle(array $body, ServerRequest $request, Response $response, array $args): Response;

    protected function getJwtClaims(ServerRequest $request): array
    {
        $token = (new Parser())->parse($request->getParsedBodyParam('request'));
        PublicKey::verify($token);
        return json_decode(json_encode($token->getClaims()), true);
    }

    abstract public function getName(): string;

}