<?php
/**
 * Created for plugin-component-core
 * Date: 10.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Components\Core\Actions;


use Leadvertex\Plugin\Components\Process\Process;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class ProcessAction implements ActionInterface
{

    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        $process = Process::findById($request->getQueryParam('id'));

        if (is_null($process)) {
            return $response->withStatus(404);
        }

        return $response->withJson($process);
    }

}