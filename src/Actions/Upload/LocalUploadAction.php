<?php
/**
 * Created for plugin-core
 * Date: 07.10.2021
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Actions\Upload;

use SalesRender\Plugin\Components\Db\Components\Connector;
use SalesRender\Plugin\Core\Helpers\PathHelper;
use Ramsey\Uuid\Uuid;
use Slim\Http\Response;
use Slim\Psr7\UploadedFile;
use XAKEPEHOK\Path\Path;

class LocalUploadAction extends UploadAction
{

    private Path $root;

    public function __construct(array $permissions, Path $root = null)
    {
        $this->root = $root ?? PathHelper::getPublicUpload();
        parent::__construct($permissions);
    }

    protected function handler(UploadedFile $file): Response
    {
        $ext = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));

        $relative = (new Path('/'))
            ->down(Connector::getReference()->getCompanyId())
            ->down(Connector::getReference()->getId())
            ->down(Uuid::uuid4()->toString() . '.' . $ext);

        $pathOnDisk = $this->root->down($relative);

        $directory = $pathOnDisk->up();
        if (!is_dir((string) $directory)) {
            mkdir((string) $directory, 0755, true);
        }

        $file->moveTo((string) $pathOnDisk);

        $uriPath = (new Path($_ENV['LV_PLUGIN_SELF_URI']))->down('uploaded')->down($relative);
        return $this->response->withJson([
            'uri' => (string) $uriPath,
        ]);
    }

}