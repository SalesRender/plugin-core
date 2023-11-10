<?php
/**
 * Created for plugin-component-core
 * Date: 10.11.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Actions\Upload;


use SalesRender\Plugin\Core\Actions\ActionInterface;
use Slim\Http\Response;
use Slim\Http\ServerRequest;
use Slim\Psr7\UploadedFile;

abstract class UploadAction implements ActionInterface
{

    protected array $permissions = [];

    protected Response $response;

    public function __construct(array $permissions)
    {
        foreach ($permissions as $ext => $size) {
            $this->permissions[strtolower($ext)] = $size;
        }
    }

    public function __invoke(ServerRequest $request, Response $response, array $args): Response
    {
        $this->response = $response;

        if (empty($this->permissions)) {
            return $response->withJson(
                [
                    'code' => 405,
                    'message' => 'This plugin do not work with files'
                ],
                405
            );
        }

        /** @var UploadedFile $file */
        $file = $request->getUploadedFiles()['file'] ?? null;

        if (!$file) {
            return $response->withStatus(400);
        }

        $ext = strtolower(pathinfo($file->getClientFilename(), PATHINFO_EXTENSION));
        if (empty($ext)) {
            return $response->withJson(
                [
                    'code' => 403,
                    'message' => "Files without extension can not be uploaded",
                    'permissions' => $this->permissions,
                ],
                403
            );
        }

        if (!isset($this->permissions[$ext]) && !isset($this->permissions['*'])) {
            return $response->withJson(
                [
                    'code' => 415,
                    'message' => "Files with *.{$ext} can not be uploaded",
                    'permissions' => $this->permissions,
                ],
                415
            );
        }

        $maxSize = $this->permissions[$ext] ?? $this->permissions['*'];
        if ($file->getSize() > $maxSize) {
            return $response->withJson(
                [
                    'code' => 413,
                    'message' => "Files too big and can not be uploaded",
                    'permissions' => $this->permissions,
                ],
                413
            );
        }

        return $this->handler($file);
    }

    abstract protected function handler(UploadedFile $file): Response;

}