<?php
/**
 * Created for plugin-core
 * Date: 02.12.2020
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Factories;


use SalesRender\Plugin\Components\Batch\BatchContainer;
use SalesRender\Plugin\Components\Batch\Commands\BatchHandleCommand;
use SalesRender\Plugin\Components\Batch\Commands\BatchQueueCommand;
use SalesRender\Plugin\Components\Db\Commands\CreateTablesCommand;
use SalesRender\Plugin\Components\Db\Commands\TableCleanerCommand;
use SalesRender\Plugin\Components\DirectoryCleaner\DirectoryCleanerCommand;
use SalesRender\Plugin\Components\SpecialRequestDispatcher\Commands\SpecialRequestQueueCommand;
use SalesRender\Plugin\Components\SpecialRequestDispatcher\Commands\SpecialRequestHandleCommand;
use SalesRender\Plugin\Components\Translations\Commands\LangAddCommand;
use SalesRender\Plugin\Components\Translations\Commands\LangUpdateCommand;
use SalesRender\Plugin\Core\Commands\CronCommand;
use Symfony\Component\Console\Application;
use Throwable;
use XAKEPEHOK\Path\Path;

abstract class ConsoleAppFactory extends AppFactory
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

        $app->add(new CreateTablesCommand());
        $app->add(new TableCleanerCommand());

        $app->add(new LangAddCommand());
        $app->add(new LangUpdateCommand());

        $app->add(new SpecialRequestQueueCommand());
        $app->add(new SpecialRequestHandleCommand());

        try {
            BatchContainer::getHandler();
            $this->addCronTask('* * * * *', 'batch:queue');
        } catch (Throwable $throwable) {}
        $this->addCronTask('* * * * *', 'specialRequest:queue');

        $app->add(new CronCommand());

        return $app;
    }

    protected function addCronTask(string $schedule, string $phpConsoleCommand): void
    {
        CronCommand::addTask(implode(" ", [
            trim($schedule),
            PHP_BINARY,
            Path::root()->down('console.php'),
            trim($phpConsoleCommand)
        ]));
    }

}