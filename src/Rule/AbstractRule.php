<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2025 Open Source Matters, Inc. All rights reserved.
 *             Copyright (C) 2008 - 2016 compojoom.com . All rights reserved.
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 *             eaxs <support@projectfork.net>
 *
 * @license    GNU General Public Licence version 2 or later; see LICENCE.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Rule;

defined('_JEXEC') or die('Restricted access');

use Joomla\Component\Jedchecker\Administrator\Report\Report;
use Joomla\Registry\Registry;

/**
 * AbstractRule is the base class for all JEDChecker rules.
 *
 * @since  3.0
 */
abstract class AbstractRule
{
	protected string $id          = '';
	protected string $title       = '';
	protected string $description = '';

	/** @var int Rule sort order */
	public static int $ordering = 10000;

	protected string   $basedir;
	protected Registry $params;
	protected Report   $report;

	public function __construct(string $basedir = '', ?Registry $params = null)
	{
		$this->basedir = $basedir;
		$this->report  = new Report;

		if ($params !== null)
		{
			$this->params = $params;
		}
		else
		{
			$this->params = $this->loadParams();
		}
	}

	abstract public function check(): void;

	public function getId(): string
	{
		return $this->id;
	}

	public function getTitle(): string
	{
		return $this->title;
	}

	public function getDescription(): string
	{
		return $this->description;
	}

	public function getReport(): Report
	{
		return $this->report;
	}

	protected function loadParams(): Registry
	{
		$reflect   = new \ReflectionClass($this);
		$shortName = strtolower(preg_replace('/Rule$/', '', $reflect->getShortName()));

		$paramsFile = __DIR__ . '/Rules/data/' . $shortName . '.ini';

		$params = new Registry('jedchecker.rule.' . $shortName);

		if (file_exists($paramsFile))
		{
			$data = file_get_contents($paramsFile);

			if ($data)
			{
				$obj = (object) parse_ini_string($data);

				if (is_object($obj))
				{
					$params->loadObject($obj);
				}
			}
		}

		return $params;
	}
}
