<?php
/**
 * @link https://github.com/notamedia/console-jedi
 * @copyright Copyright © 2016 Notamedia Ltd.
 * @license MIT
 */

namespace Notamedia\ConsoleJedi\Command\Agents;

use Bitrix\Main\Config\Option;
use Notamedia\ConsoleJedi\Command\BitrixCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Installation configurations for run Agents on cron.
 */
class OnCronCommand extends BitrixCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this->setName('agents:on-cron')
            ->setDescription('Installation configurations for run Agents on cron');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        Option::set('main', 'agents_use_crontab', 'N');
        Option::set('main', 'check_agents', 'N');
    }
}