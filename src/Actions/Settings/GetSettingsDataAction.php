<?php
/**
 * Created for plugin-core
 * Date: 26.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Components\Core\Actions\Settings;


use Leadvertex\Plugin\Components\Settings\Settings;
use Leadvertex\Plugin\Components\Core\Actions\ActionInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class GetSettingsDataAction implements ActionInterface
{

    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        return $response->withJson(Settings::find()->getData());
    }

}