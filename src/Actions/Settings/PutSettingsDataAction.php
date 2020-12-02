<?php
/**
 * Created for plugin-core
 * Date: 26.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Actions\Settings;


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
        $data = new FormData($request->getParsedBody());

        $errors = $form->getErrors($data);
        if (!empty($errors)) {
            return $response->withJson($errors, 400);
        }

        $settings = Settings::find();
        $settings->setData($data);
        $settings->save();

        return $response->withJson(Settings::find()->getData());
    }

}