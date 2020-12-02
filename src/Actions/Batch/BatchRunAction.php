<?php
/**
 * Created for plugin-core
 * Date: 02.12.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Components\Core\Actions\Batch;


use Leadvertex\Plugin\Components\Access\Token\GraphqlInputToken;
use Leadvertex\Plugin\Components\Batch\Batch;
use Leadvertex\Plugin\Components\Batch\BatchFormRegistry;
use Leadvertex\Plugin\Components\Batch\BatchHandler;
use Leadvertex\Plugin\Components\Process\Process;
use Slim\Http\Response;
use Slim\Http\ServerRequest;

class BatchRunAction extends BatchAction
{

    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        /** @var Batch $batch */
        $batch = Batch::find();
        if (is_null($batch)) {
            return $response->withStatus(425);
        }

        if (!is_null(BatchFormRegistry::getForm($batch->countOptions() + 1))) {
            return $response->withStatus(425);
        }

        $process = new Process(
            GraphqlInputToken::getInstance()->getPluginReference(),
            GraphqlInputToken::getInstance()->getId(),
        );

        if ((int) $_ENV['LV_PLUGIN_DEBUG']) {
            $process->setState(Process::STATE_PROCESSING);
            $process->save();

            BatchHandler::getInstance()($process, $batch);
        } else {
            $process->save();
        }

        return $response;
    }
}