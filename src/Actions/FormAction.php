<?php
/**
 * Created for plugin-component-core
 * Date: 10.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Actions;


use Leadvertex\Plugin\Components\Form\Form;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class FormAction implements ActionInterface
{

    private ?Form $form;

    public function __construct(?Form $form)
    {
        $this->form = $form;
    }

    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        if (is_null($this->form)) {
            return $response->withStatus(404);
        }

        return $response->withJson($this->form);
    }
}