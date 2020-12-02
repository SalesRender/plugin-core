<?php
namespace Leadvertex\Plugin\Components\Core\Factories;

use Dotenv\Dotenv;
use Dotenv\Repository\Adapter\EnvConstAdapter;
use Dotenv\Repository\RepositoryBuilder;
use XAKEPEHOK\Path\Path;

abstract class AppFactory
{

    public function __construct()
    {
        $repository = RepositoryBuilder::create()
            ->withReaders([new EnvConstAdapter()])
            ->withWriters([new EnvConstAdapter()])
            ->immutable()
            ->make();

        $env = Dotenv::create($repository, (string) Path::root());
        $env->load();

        $env->required('LV_PLUGIN_PHP_BINARY')->notEmpty();
        $env->required('LV_PLUGIN_DEBUG')->isBoolean();
        $env->required('LV_PLUGIN_QUEUE_LIMIT')->notEmpty()->isInteger();
        $env->required('LV_PLUGIN_SELF_URI')->notEmpty();

        $_ENV['LV_PLUGIN_SELF_URI'] = rtrim($_ENV['LV_PLUGIN_SELF_URI'], '/') . '/';

        $bootstrap = Path::root()->down('bootstrap.php');
        include_once $bootstrap;
    }

    abstract protected function createBaseApp();

}