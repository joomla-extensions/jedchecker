<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2025 Open Source Matters, Inc. All rights reserved.
 *             Copyright (C) 2008 - 2016 compojoom.com . All rights reserved.
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 *
 * @license    GNU General Public Licence version 2 or later; see LICENCE.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Model;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseModel;
use Joomla\Component\Jedchecker\Administrator\Rule\RuleDiscovery;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;

/**
 * UploadsModel manages upload paths, rule execution, and folder discovery.
 *
 * @since  3.0
 */
class UploadsModel extends BaseModel
{
	protected string $path;
	protected string $pathArchive;
	protected string $pathUnzipped;

	public function __construct($config = [])
	{
		parent::__construct($config);

		$tmpPath            = Factory::getApplication()->getConfig()->get('tmp_path');
		$this->path         = $tmpPath . '/jed_checker';
		$this->pathArchive  = $this->path . '/archives';
		$this->pathUnzipped = $this->path . '/unzipped';
	}

	public function getArchivePath(): string
	{
		return $this->pathArchive;
	}

	public function getUnzippedPath(): string
	{
		return $this->pathUnzipped;
	}

	public function getBasePath(): string
	{
		return $this->path;
	}

	/**
	 * Discover all folders that should be checked (unzipped subfolders + local.txt entries).
	 *
	 * @return  string[]
	 */
	public function getFolders(): array
	{
		$folders    = [];
		$tmpFolders = Folder::folders($this->pathUnzipped);

		if (!empty($tmpFolders))
		{
			foreach ($tmpFolders as $tmpFolder)
			{
				$folders[] = $this->pathUnzipped . '/' . $tmpFolder;
			}
		}

		$local = $this->path . '/local.txt';

		if (is_file($local))
		{
			$content = file_get_contents($local);

			if (!empty($content))
			{
				foreach (explode("\n", $content) as $line)
				{
					$line = trim($line);

					if ($line === '')
					{
						continue;
					}

					if (is_dir(JPATH_ROOT . '/' . $line))
					{
						$folders[] = JPATH_ROOT . '/' . $line;
					}
					elseif (is_dir($line))
					{
						$folders[] = $line;
					}
				}
			}
		}

		return $folders;
	}

	/**
	 * Run all rules against all discovered folders and return structured results.
	 *
	 * @param   string  $folder  The folder to check (single folder from getFolders())
	 *
	 * @return  array  Per-rule result arrays with keys: id, title, description, data, html
	 */
	public function runChecks(string $folder): array
	{
		$ruleClasses = RuleDiscovery::getRules();
		$results     = [];

		foreach ($ruleClasses as $ruleClass)
		{
			$instance = new $ruleClass(Path::clean($folder));
			$instance->check();

			$report    = $instance->getReport();
			$results[] = [
				'id'          => $instance->getId(),
				'title'       => $instance->getTitle(),
				'description' => $instance->getDescription(),
				'data'        => $report->getData(),
				'html'        => $report->getHTML(),
			];
		}

		return $results;
	}

	/**
	 * Run a single rule (identified by short name) against all folders and return HTML.
	 *
	 * @param   string  $shortName  Lowercase rule name, e.g. 'jexec', 'xmlmanifest'
	 *
	 * @return  string  Rendered HTML from the rule report
	 */
	public function runRule(string $shortName): string
	{
		$ruleClass = $this->findRuleClass($shortName);

		if ($ruleClass === null)
		{
			return '';
		}

		$folders = $this->getFolders();
		$html    = '';

		foreach ($folders as $folder)
		{
			$instance = new $ruleClass(Path::clean($folder));
			$instance->check();
			echo $instance->getReport()->getHTML();
		}

		return '';
	}

	/**
	 * Delete the jed_checker temp directory.
	 *
	 * @return  bool
	 */
	public function clearPaths(): bool
	{
		if (is_dir($this->path))
		{
			return Folder::delete($this->path);
		}

		return true;
	}

	/**
	 * Find the FQCN for a rule by its lowercase short name.
	 *
	 * @param   string  $shortName  e.g. 'jexec', 'xmlmanifest'
	 *
	 * @return  string|null  FQCN or null if not found
	 */
	protected function findRuleClass(string $shortName): ?string
	{
		foreach (RuleDiscovery::getRules() as $fqcn)
		{
			$parts     = explode('\\', $fqcn);
			$className = strtolower(preg_replace('/Rule$/', '', end($parts)));

			if ($className === strtolower($shortName))
			{
				return $fqcn;
			}
		}

		return null;
	}
}
