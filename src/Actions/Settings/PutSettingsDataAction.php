<?php
/**
 * Created for plugin-core
 * Date: 26.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Actions\Settings;


use SalesRender\Plugin\Components\Form\FieldDefinitions\PasswordDefinition;
use SalesRender\Plugin\Components\Form\FormData;
use SalesRender\Plugin\Components\Settings\Settings;
use SalesRender\Plugin\Core\Actions\ActionInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class PutSettingsDataAction implements ActionInterface
{

    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        $form = Settings::getForm();

        $oldData = Settings::find()->getData();
        $newData = new FormData($request->getParsedBody());
        $responseData = new FormData($request->getParsedBody());

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

                $responseData->set($path, $newData->get($path) != '');
            }
        }

        $errors = $form->getErrors($newData);
        if (!empty($errors)) {
            return $response->withJson($errors, 400);
        }

        //Prevent store redundant data in settings, which was received from end-user
        $newData = $form->clearRedundant($newData);
        $responseData = $form->clearRedundant($responseData);

        $settings = Settings::find();
        $settings->setData($newData);
        $settings->save();

        return $response->withJson($responseData);
    }

}