<?php
/**
 * Created for plugin-core
 * Date: 07.10.2021
 * @author Timur Kasumov (XAKEPEHOK)
 */


namespace SalesRender\Plugin\Core\Actions\Upload;

class UploadersContainer
{

    private static array $uploaders = [];

    public static function addDefaultUploader(UploadAction $action): void
    {
        self::$uploaders[''] = $action;
    }

    public static function addCustomUploader(string $name, UploadAction $action): void
    {
        self::$uploaders['/' . $name] = $action;
    }

    /**
     * @return UploadAction[]
     */
    public static function getUploaders(): array
    {
        return self::$uploaders;
    }

}