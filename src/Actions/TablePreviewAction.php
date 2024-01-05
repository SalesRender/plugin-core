<?php
/**
 * Created for plugin-component-core
 * Date: 10.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Actions;


use SalesRender\Plugin\Components\Form\Exceptions\TablePreviewRegistryException;
use SalesRender\Plugin\Components\Form\TableView\TablePreviewRegistry;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class TablePreviewAction implements ActionInterface
{


    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        try {
            $preview = TablePreviewRegistry::getTablePreview($args['name']);
        } catch (TablePreviewRegistryException $exception) {
            return $response->withStatus(404);
        }

        if (is_null($preview)) {
            return $response->withStatus(404);
        }

        return $response->withJson(
            $preview->render(
                json_decode($request->getQueryParam('dep', '[]'), true),
                json_decode($request->getQueryParam('context', '[]'), true)
            )
        );
    }
}