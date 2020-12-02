<?php
/**
 * Created for plugin-core
 * Date: 26.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Actions\Batch;


use Leadvertex\Plugin\Components\Access\Token\GraphqlInputToken;
use Leadvertex\Plugin\Components\ApiClient\ApiFilterSortPaginate;
use Leadvertex\Plugin\Components\ApiClient\ApiSort;
use Leadvertex\Plugin\Components\Batch\Batch;
use Leadvertex\Plugin\Components\Translations\Translator;
use Leadvertex\Plugin\Core\Actions\ActionInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class BatchPrepareAction implements ActionInterface
{

    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        $batch = Batch::find();
        if ($batch) {
            return $response->withStatus(409);
        }

        $filters = $request->getParam('filters', []);
        $sort = $request->getParam('sort');
        if ($sort && isset($sort['field']) && isset($sort['direction'])) {
            $sort = new ApiSort($sort['field'], $sort['direction']);
        } else {
            $sort = null;
        }

        $batch = new Batch(
            GraphqlInputToken::getInstance(),
            new ApiFilterSortPaginate($filters, $sort, 100),
            Translator::getLang()
        );
        $batch->save();

        return $response->withStatus(201);
    }
}