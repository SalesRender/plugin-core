<?php
/**
 * Created for LeadVertex
 * Date: 10/25/21 6:14 PM
 * @author Timur Kasumov (XAKEPEHOK)
 */

namespace SalesRender\Plugin\Core\Commands;

use Cron\CronExpression;
use SalesRender\Plugin\Components\Batch\BatchContainer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Throwable;
use XAKEPEHOK\Path\Path;

class CronCommand extends Command
{
    const CHECK_PROCESS_STATUS_TIMEOUT = 3;

    private static array $commands = [];

    public function __construct()
    {
        parent::__construct('cron:run');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $tasks = $this->getTasks();
        $table = new Table($output);
        $table->setHeaders(['Schedule', 'Command']);
        foreach ($tasks as $arguments) {
            list($expression, $command) = $arguments;
            $table->addRow([$expression, $command]);
        }
        $table->render();
        $output->writeln('');

        $processes = [];
        foreach ($tasks as $arguments) {
            list($expression, $command) = $arguments;
            $cron = new CronExpression($expression);
            if ($cron->isDue()) {
                $output->writeln("<info>Run:</info> {$expression} {$command}");
                $process = Process::fromShellCommandline($command);
                $process->start();
                $processes["{$expression} {$command}"] = $process;
            }
        }

        $output->writeln('');

        do {
            foreach ($processes as $cronLine => $process) {
                if ($process->isTerminated()) {
                    unset($processes[$cronLine]);
                    if ($process->isSuccessful()) {
                        $output->writeln('<info>Finished:</info> ' . $cronLine);
                    } else {
                        $output->writeln('<error>Finished:</error> ' . $cronLine);
                    }
                }
                sleep(self::CHECK_PROCESS_STATUS_TIMEOUT);
            }
        } while (count($processes) > 0);

        return self::SUCCESS;
    }

    protected function getTasks(): array
    {
        $crontabFile = Path::root()->down('cron.txt');
        $crontab = '';
        if (file_exists((string)$crontabFile)) {
            $crontab = file_get_contents((string)$crontabFile);
        }

        $crontab .= PHP_EOL . $this->getDefaultTasks();

        foreach (self::$commands as $command) {
            $crontab .= trim($command) . PHP_EOL;
        }

        $crontab = str_replace("\r\n", "\n", trim($crontab));

        $result = [];
        $tasks = explode("\n", $crontab);
        foreach ($tasks as $task) {
            $parts = explode(' ', $task, 6);
            $expression = implode(' ', array_slice($parts, 0, 5));
            $command = $parts[5];
            $result[] = [$expression, $command];
        }

        return $result;
    }

    protected function getDefaultTasks(): string
    {
        $default = '* * * * * ' . PHP_BINARY . ' ' . Path::root()->down('console.php');
        $crontab = '';
        try {
            BatchContainer::getHandler();
            $crontab = $default . ' batch:queue' . PHP_EOL;
        } catch (Throwable $throwable) {
        }

        $crontab .= $default . ' specialRequest:queue' . PHP_EOL;
        return $crontab;
    }

    protected function configure()
    {
        $this->setDescription('This command run some plugin-system tasks and tasks from cron.txt file, placed in root directory of plugin');
    }

    public static function addTask($command)
    {
        self::$commands[] = $command;
    }

}