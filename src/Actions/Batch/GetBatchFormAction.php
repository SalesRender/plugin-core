<?php
/**
 * Created for plugin-core
 * Date: 26.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Actions\Batch;


use Leadvertex\Plugin\Components\Batch\BatchContainer;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class GetBatchFormAction extends BatchAction
{

    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        $number = (int) $args['number'];

        if ($error = $this->guard($number, $response)) {
            return $error;
        }

        return $response->withJson(BatchContainer::getForm($number));
    }
}