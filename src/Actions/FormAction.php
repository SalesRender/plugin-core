<?php
/**
 * Created for plugin-component-core
 * Date: 10.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Actions;


use Slim\Http\Response;
use Slim\Http\ServerRequest;

class FormAction implements ActionInterface
{

    /** @var callable */
    private $form;

    public function __construct(callable $form)
    {
        $this->form = $form;
    }

    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        $form = ($this->form)();

        if (is_null($form)) {
            return $response->withStatus(404);
        }

        return $response->withJson($form);
    }
}