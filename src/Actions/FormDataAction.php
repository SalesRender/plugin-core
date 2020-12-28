<?php
/**
 * Created for plugin-core
 * Date: 28.12.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Actions;


use Leadvertex\Plugin\Components\Form\FieldDefinitions\PasswordDefinition;
use Leadvertex\Plugin\Components\Form\Form;
use Leadvertex\Plugin\Components\Form\FormData;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class FormDataAction implements ActionInterface
{

    private $getForm;

    private $getFormData;

    public function __construct(callable $getForm, callable $getFormData)
    {
        $this->getForm = $getForm;
        $this->getFormData = $getFormData;
    }

    public function getForm(): Form
    {
        return ($this->getForm)();
    }

    public function getFormData(): FormData
    {
        return ($this->getFormData)();
    }

    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        $form = $this->getForm();
        $data = $this->getFormData();

        foreach ($form->getGroups() as $groupName => $group) {
            foreach ($group->getFields() as $fieldName => $field) {
                if ($field instanceof PasswordDefinition) {
                    $path = "{$groupName}.{$fieldName}";
                    $data->set($path, $data->get($path, '') !== '');
                }
            }
        }

        return $response->withJson($data);
    }
}