<?php
/**
 * Created for plugin-component-core
 * Date: 10.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Actions;


use Slim\Http\Response;
use Slim\Http\ServerRequest;

class RobotsActions implements ActionInterface
{

    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        $response->getBody()->write("User-agent: *\nDisallow: /");
        return $response;
    }

}