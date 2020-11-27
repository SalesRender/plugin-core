<?php
/**
 * Created for plugin-component-core
 * Date: 10.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Components\Core\Actions;


use Slim\Http\Response;
use Slim\Http\ServerRequest;

interface ActionInterface
{

    public function __invoke(ServerRequest $request, Response $response, array $args): Response;

}