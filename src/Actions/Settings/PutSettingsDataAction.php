<?php
/**
 * Created for plugin-core
 * Date: 26.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Actions\Settings;


use Leadvertex\Plugin\Components\Form\FieldDefinitions\PasswordDefinition;
use Leadvertex\Plugin\Components\Form\FormData;
use Leadvertex\Plugin\Components\Settings\Settings;
use Leadvertex\Plugin\Core\Actions\ActionInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class PutSettingsDataAction implements ActionInterface
{

    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        $form = Settings::getForm();

        $oldData = Settings::find()->getData();
        $newData = new FormData($request->getParsedBody());

        foreach ($form->getGroups() as $groupName => $group) {
            foreach ($group->getFields() as $fieldName => $field) {
                if (!($field instanceof PasswordDefinition)) {
                    continue;
                }

                $path = "{$groupName}.{$fieldName}";
                $isUnchanged = $newData->has($path) === false;

                if ($isUnchanged) {
                    $newData->set($path, $oldData->get($path));
                }
            }
        }

        $errors = $form->getErrors($newData);
        if (!empty($errors)) {
            return $response->withJson($errors, 400);
        }

        $settings = Settings::find();
        $settings->setData($newData);
        $settings->save();

        return $response->withJson(Settings::find()->getData());
    }

}