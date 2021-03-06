<?php
/**
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Notamedia\ConsoleJedi\Console\Command\Agent;

use Notamedia\ConsoleJedi\Console\Command\BitrixCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Run agents.
 * 
 * @author Nik Samokhvalov <nik@samokhvalov.info>
 */
class RunCommand extends BitrixCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('agent:run')
            ->setDescription('Runs execution of Agents');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        @set_time_limit(0);
        @ignore_user_abort(true);
        define('CHK_EVENT', true);

        $agentManager = new \CAgent();
        $agentManager->CheckAgents();

        define('BX_CRONTAB_SUPPORT', true);
        define('BX_CRONTAB', true);

        $eventManager = new \CEvent();
        $eventManager->CheckEvents();
    }
}
