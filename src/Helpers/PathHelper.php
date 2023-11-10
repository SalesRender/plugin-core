<?php
/**
 * Created for plugin-core
 * Datetime: 20.02.2020 16:53
 * @author Timur Kasumov aka XAKEPEHOK
 */

namespace SalesRender\Plugin\Core\Helpers;


use XAKEPEHOK\Path\Path;

class PathHelper
{

    public static function getTemp(): Path
    {
        return Path::root()->down('temp');
    }

    public static function getPublic(): Path
    {
        return Path::root()->down('public');
    }

    public static function getPublicOutput(): Path
    {
        return Path::root()->down('public')->down('output');
    }

    public static function getPublicUpload(): Path
    {
        return Path::root()->down('public')->down('uploaded');
    }

}