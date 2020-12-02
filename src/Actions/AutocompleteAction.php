<?php
/**
 * Created for plugin-component-core
 * Date: 10.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Components\Core\Actions;


use Leadvertex\Plugin\Components\Form\Components\AutocompleteRegistry;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class AutocompleteAction implements ActionInterface
{


    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        $autocomplete = AutocompleteRegistry::getAutocomplete($args['name']);
        if (is_null($autocomplete)) {
            return $response->withStatus(404);
        }

        $query = $request->getQueryParam('query');

        if (is_array($query)) {
            return $response->withJson(
                $autocomplete->values($query)
            );
        }

        return $response->withJson(
            $autocomplete->query($query)
        );
    }
}