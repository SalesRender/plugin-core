<?php
/**
 * Created for plugin-component-core
 * Date: 10.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Actions;


use Lcobucci\JWT\Parser;
use SalesRender\Plugin\Components\Access\Registration\Registration;
use SalesRender\Plugin\Components\Db\Components\Connector;
use SalesRender\Plugin\Components\Db\Components\PluginReference;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class RegistrationAction implements ActionInterface
{

    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        $parser = new Parser();
        $token = $parser->parse($request->getParsedBodyParam('registration'));

        Connector::setReference(new PluginReference(
            $token->getClaim('cid'),
            $token->getClaim('plugin')->alias,
            $token->getClaim('plugin')->id
        ));

        if ($old = Registration::find()) {
            $old->delete();
        }

        $registration = new Registration($token);
        $registration->save();

        return $response->withJson(['registered' => true]);
    }

}