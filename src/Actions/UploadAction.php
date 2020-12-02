<?php
/**
 * Created for plugin-component-core
 * Date: 10.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Core\Actions;


use Leadvertex\Plugin\Components\Db\Components\Connector;
use Leadvertex\Plugin\Core\Helpers\PathHelper;
use Ramsey\Uuid\Uuid;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Slim\Psr7\UploadedFile;
use XAKEPEHOK\Path\Path;

class UploadAction implements ActionInterface
{

    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        /** @var UploadedFile $file */
        $file = $request->getUploadedFiles()['file'] ?? null;

        if (!$file) {
            return $response->withStatus(400);
        }

        $ext = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);
        if (empty($ext)) {
            return $response->withStatus(403);
        }

        $relative = (new Path('/'))
            ->down(Connector::getReference()->getCompanyId())
            ->down(Connector::getReference()->getId())
            ->down(Uuid::uuid4()->toString() . '.' . $ext);

        $pathOnDisk = PathHelper::getPublicUpload()->down($relative);

        $directory = $pathOnDisk->up();
        if (!is_dir((string) $directory)) {
            mkdir((string) $directory, 0755, true);
        }

        $file->moveTo((string) $pathOnDisk);

        $uriPath = (new Path($_ENV['LV_PLUGIN_SELF_URI']))->down('uploaded')->down($relative);
        return $response->withJson([
            'uri' => (string) $uriPath,
        ]);
    }

}