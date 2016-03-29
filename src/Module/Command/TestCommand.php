<?php
/**
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace Notamedia\ConsoleJedi\Module\Command;

use Notamedia\ConsoleJedi\Application\Command\BitrixCommand;
use Notamedia\ConsoleJedi\Module\Exception as Ex;
use Notamedia\ConsoleJedi\Module\Module;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Zend\Diactoros\Uri;

/**
 * Command for module installation/register
 *
 * @author Marat Shamshutdinov <m.shamshutdinov@gmail.com>
 */
class TestCommand extends BitrixCommand
{
	/** @var ProgressBar */
	private $bar;
	/** @var string */
	private $query = 'notamedia';

	/** @var Uri */
	private $baseUrl;

	/** @var string[] */
	private $modules = [];

	private $report = [];

	/**
	 * {@inheritdoc}
	 */
	protected function configure()
	{
		parent::configure();

		$this->setName('module:test')
			->setDescription('Test modules installation from Marketplace')
			->addArgument('query', InputArgument::OPTIONAL,
				'Url to module list on marketplace.1c-bitrix.ru or search query');
	}

	protected function interact(InputInterface $input, OutputInterface $output)
	{
		$query = $input->getArgument('query');

		if (!strlen(trim($query)))
		{
			$question = new Question('Please enter url or search query for module list: ', $this->query);
			$question->setMaxAttempts(5);
			$question->setValidator(function ($answer)
			{
				if (!strlen(trim($answer)))
				{
					throw new \RuntimeException('Please enter full url or search term.');
				}

				return $answer;
			});

			$query = $this->getHelper('question')->ask($input, $output, $question);
		}

		if (preg_match('#^http[s]?://#', $query))
		{
			$this->baseUrl = new Uri($query);
		}
		elseif (strlen(trim($query)))
		{
			$this->baseUrl = new Uri('http://marketplace.1c-bitrix.ru/search/?q=' . urlencode($query));
		}
	}

	protected function fetchModules(InputInterface $input, OutputInterface $output)
	{
		$query = [];
		parse_str($this->baseUrl->getQuery(), $query);
		$query['update_sys_new'] = 'Y';
		$query['PAGEN_1'] = '1';

		$modules = [];
		$pages = 0;

		$output->writeln('<info>Fetching modules info</info>');

		do
		{
			$url = $this->baseUrl->withQuery(http_build_query($query));

			$output->writeln((string)$url, OutputInterface::VERBOSITY_VERBOSE);
			$modulesXml = new \SimpleXMLElement((string)$url, 0, true);

			if ($pages++ == 0 && isset($modulesXml->categoryName))
			{
				$output->writeln('Modules from category <info>' . $modulesXml->categoryName . '</info> will be tested');
			}

			foreach ($modulesXml->items->item as $moduleInfo)
			{
				if ($moduleInfo->freeModule == 'Y' || $moduleInfo->freeModule == 'D')
				{
					$modules[(string)$moduleInfo->code] = (string)$moduleInfo->name;
				}
			}

			$nav = unserialize($modulesXml->navData);
			if (is_array($nav))
			{
				$this->bar->start($nav['NavPageCount']);
				$query['PAGEN_' . $nav['NavNum']] = $nav['NavPageNomer'] + 1;
			}
			$this->bar->advance();
		} while (is_array($nav) && $nav['NavPageNomer'] < $nav['NavPageCount']);
		$this->bar->clear();
		$output->write("\r");

		if (!count($modules))
		{
			throw new \RuntimeException('Free or trial versions of the modules has not been found');
		}

		$this->modules = $modules;
	}

	protected function renderReport(InputInterface $input, OutputInterface $output)
	{
		$report = $this->report;

		$headers = array_keys($report);
		$footer = [];
		$rowCount = 0;
		array_walk($this->report, function ($val) use (&$rowCount)
		{
			if (is_array($val) && count($val) > $rowCount)
			{
				$rowCount = count($val);
			}
		});

		$table = new Table($output);
		$table->setStyle('symfony-style-guide');
		$table->setHeaders($headers);
		for ($i = 0; $i < $rowCount; $i++)
		{
			$row = [];
			foreach ($headers as $col)
			{
				if ($cell = array_shift($report[$col]))
				{
					$row[] = $cell;
					$footer[$col] += 1;
				}
				else
				{
					$row[] = '';
				}
			}
			$table->addRow($row);
		}

		$table->addRows([
			new TableSeparator(),
			$footer,
		]);

		$table->render();
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$this->report = $this->modules = [];

		if (!isset($this->baseUrl))
		{
			throw new \RuntimeException('No query argument specified');
		}

		$this->bar = new ProgressBar($output);
		$this->bar->setRedrawFrequency(1);

		$this->fetchModules($input, $output);

		$this->bar->start(count($this->modules));
		$this->bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% ~%estimated:-6s% %message%');

		foreach ($this->modules as $moduleName => $moduleDesc)
		{
			$message = 'installing ' . $moduleName;

			try
			{
				$this->bar->setMessage('(' . $message . ')');
				$this->bar->display();

				(new Module($moduleName))->load()->register()->remove();

				$this->bar->advance();
				$this->bar->clear();
				$output->write("\r");
				$output->writeln("\r   " . $message . ': <info>ok</info>');
			}
			catch (Ex\ModuleException $e)
			{
				$className = join('', array_slice(explode('\\', get_class($e)), -1));
				$this->report['<error>' .$className . '</error>'][] = $moduleName;
				$this->bar->clear();
				$output->write("\r");
				$output->writeln($e->getMessage(), OutputInterface::VERBOSITY_VERBOSE);
				$output->writeln(sprintf("\r" . '   installing %s: <error>FAILED</error> (%s)', $moduleName,
					$className));
				continue;
			}
			$this->bar->clear();
			$this->report['Success'][] = $moduleName;
		}

		$this->bar->clear();
		$output->write("\r");

		$this->renderReport($input, $output);
	}
}