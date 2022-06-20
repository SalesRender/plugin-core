<?php
/**
 * Created for plugin-core
 * Date: 20.06.2022
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Middleware;


use Leadvertex\Plugin\Components\Translations\Translator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpException;
use Slim\Http\Response;
use Slim\Http\ServerRequest as Request;

class LanguageMiddleware
{

    /**
     * @param Request $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws HttpException
     */
    public function __invoke(Request $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = str_replace('-', '_', $request->getHeader('Accept-Language')[0] ?? '');

        $matches = [];
        if (preg_match_all('~[a-z]{2}_[A-Z]{2}~', $header, $matches)) {
            $languages = array_filter($matches[0], function (string $lang) {
                return in_array($lang, Translator::getLanguages());
            });

            Translator::setLang($languages[0] ?? Translator::getDefaultLang());
        }

        /** @var Response $response */
        return $handler->handle($request);
    }

}