<?php
/**
 * Created for plugin-core
 * Date: 02.12.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace Leadvertex\Plugin\Components\Core\Factories;


use Leadvertex\Plugin\Components\Batch\Commands\BatchHandleCommand;
use Leadvertex\Plugin\Components\Batch\Commands\BatchQueueCommand;
use Leadvertex\Plugin\Components\Db\Commands\TableCleanerCommand;
use Leadvertex\Plugin\Components\DirectoryCleaner\DirectoryCleanerCommand;
use Leadvertex\Plugin\Components\Translations\Commands\LangAddCommand;
use Leadvertex\Plugin\Components\Translations\Commands\LangUpdateCommand;
use Symfony\Component\Console\Application;

class ConsoleAppFactory extends AppFactory
{

    protected Application $app;

    public function __construct()
    {
        parent::__construct();
        $this->app = $this->createBaseApp();
    }

    public function addBatchCommands(): self
    {
        $this->app->add(new BatchQueueCommand());
        $this->app->add(new BatchHandleCommand());
        return $this;
    }

    public function build(): Application
    {
        $app = $this->app;
        $this->app = $this->createBaseApp();
        return $app;
    }

    protected function createBaseApp()
    {
        $app = new Application();

        $app->add(new DirectoryCleanerCommand());

        $app->add(new TableCleanerCommand());

        $app->add(new LangAddCommand());
        $app->add(new LangUpdateCommand());

        return $app;
    }
}