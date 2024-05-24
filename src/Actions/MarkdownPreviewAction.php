<?php

namespace SalesRender\Plugin\Core\Actions;

use SalesRender\Plugin\Components\Form\Exceptions\MarkdownPreviewRegistryException;
use SalesRender\Plugin\Components\Form\MarkdownPreview\MarkdownPreviewRegistry;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class MarkdownPreviewAction implements ActionInterface
{

    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        try {
            $preview = MarkdownPreviewRegistry::getMarkdownPreview($args['name']);
        } catch (MarkdownPreviewRegistryException $exception) {
            return $response->withStatus(404);
        }

        if (is_null($preview)) {
            return $response->withStatus(404);
        }

        return $response
            ->withHeader('Content-Type', 'text/plain')
            ->write($preview->render(
                json_decode($request->getQueryParam('dep', '[]'), true),
                json_decode($request->getQueryParam('context', '[]'), true)
            ));
    }
}