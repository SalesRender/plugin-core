<?php
/**
 * Created for plugin-component-core
 * Date: 10.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Actions;


use SalesRender\Plugin\Components\Form\Autocomplete\AutocompleteRegistry;
use SalesRender\Plugin\Components\Form\Exceptions\AutocompleteRegistryException;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class AutocompleteAction implements ActionInterface
{


    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        try {
            $autocomplete = AutocompleteRegistry::getAutocomplete($args['name']);
        } catch (AutocompleteRegistryException $exception) {
            return $response->withStatus(404);
        }

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