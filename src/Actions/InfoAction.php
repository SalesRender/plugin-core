<?php
/**
 * Created for plugin-core
 * Date: 30.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Actions;


use Leadvertex\Plugin\Components\Info\Info;
use Leadvertex\Plugin\Core\Helpers\PathHelper;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class InfoAction implements ActionInterface
{

    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        if (!file_exists(PathHelper::getPublic()->down('icon.png'))) {
            return $response->withJson(
                [
                    'code' => 510,
                    'message' => 'Plugin can not work without 128x128 px "public/icon.png" with transparent background'
                ],
                510
            );
        }

        return $response->withJson(Info::getInstance());
    }
}