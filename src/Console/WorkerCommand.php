<?php
/* (c) Anton Medvedev <anton@elfet.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Deployer\Console;

use Deployer\Console\Output\RemoteOutput;
use Deployer\Deployer;
use Deployer\Server\Environment;
use Deployer\Task\Context;
use Pure\Client;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class WorkerCommand extends Command
{
    /**
     * @var Deployer
     */
    private $deployer;

    /**
     * @param Deployer $deployer
     */
    public function __construct(Deployer $deployer)
    {
        parent::__construct('worker');
        $this->setDescription('Deployer uses workers for parallel deployment.');

        $this->deployer = $deployer;

        $this->addOption(
            'master',
            null,
            InputOption::VALUE_REQUIRED
        );

        $this->addOption(
            'server',
            null,
            InputOption::VALUE_REQUIRED
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $serverName = $input->getOption('server');
        list($host, $port) = explode(':', $input->getOption('master'));
        $pure = new Client($port, $host);

        try {
            
            $server = $this->deployer->servers->get($serverName);
            $environment = isset($this->deployer->environments[$serverName]) ? $this->deployer->environments[$serverName] : new Environment();
            $output = new RemoteOutput($output, $pure, $serverName);

            while (!$pure->map('shutdown')->has($serverName)) {
                // Get task to do
                $taskName = $pure->map('tasks_to_do')->get($serverName);

                if (null !== $taskName) {
                    $task = $this->deployer->tasks->get($taskName);

                    $task->run(new Context($server, $environment, $input, $output));

                    $pure->map('tasks_to_do')->delete($serverName);
                }

            }

        } catch (\Exception $exception) {
            
            $pure->queue('exception')->push([$serverName, get_class($exception), $exception->getMessage()]);
            
        }
    }
}
