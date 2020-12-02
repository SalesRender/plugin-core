<?php
/**
 * Created for plugin-core
 * Date: 30.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Components\Core\Actions;


use Leadvertex\Plugin\Components\Info\Info;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class InfoAction implements ActionInterface
{

    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        return $response->withJson(Info::getInstance());
    }
}