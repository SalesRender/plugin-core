<?php
/**
 * Created for plugin-core
 * Date: 26.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Actions\Batch;


use Leadvertex\Plugin\Components\Batch\Batch;
use Leadvertex\Plugin\Components\Batch\BatchContainer;
use Leadvertex\Plugin\Components\Form\FormData;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class PutBatchOptionsAction extends BatchAction
{

    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        $number = (int) $args['number'];

        if ($error = $this->guard($number, $response)) {
            return $error;
        }

        $form = BatchContainer::getForm($number);
        $data = new FormData($request->getParsedBody());
        $errors = $form->getErrors($data);
        if (!empty($errors)) {
            return $response->withJson($errors, 400);
        }

        /** @var Batch $batch */
        $batch = Batch::find();
        $batch->setOptions($number, $data);
        $batch->save();;

        return $response->withJson($data);
    }
}