<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2025 Open Source Matters, Inc. All rights reserved.
 *             Copyright (C) 2008 - 2016 mijosoft.com . All rights reserved.
 * @author     Denis Dulici <denis@mijosoft.com>
 *
 * @license    GNU General Public Licence version 2 or later; see LICENCE.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Rule\Rules;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\Component\Jedchecker\Administrator\Helper\CheckerHelper;
use Joomla\Component\Jedchecker\Administrator\Rule\AbstractRule;
use Joomla\Filesystem\Folder;

/**
 * ErrorReportingRule searches all files for the PHP error_reporting() function.
 *
 * @since  3.0
 */
class ErrorReportingRule extends AbstractRule
{
	protected string $id          = 'errorreporting';
	protected string $title       = 'COM_JEDCHECKER_RULE_ERRORREPORTING';
	protected string $description = 'COM_JEDCHECKER_RULE_ERRORREPORTING_DESC';

	public static int $ordering = 800;

	protected string $errorreportingRegex;

	public function check(): void
	{
		$codes = explode(',', $this->params->get('errorreportings'));

		foreach ($codes as $i => $encoding)
		{
			$codes[$i] = preg_quote(trim($encoding), '/');
		}

		$this->errorreportingRegex = '/' . implode('|', $codes) . '/i';

		$files = Folder::files($this->basedir, '\.php$', true, true);

		foreach ($files as $file)
		{
			$this->find($file);
		}
	}

	protected function find(string $file): bool
	{
		$content     = file_get_contents($file);
		$origContent = CheckerHelper::splitLines($content);

		$content = CheckerHelper::cleanPhpCode(
			$content,
			CheckerHelper::CLEAN_HTML | CheckerHelper::CLEAN_COMMENTS | CheckerHelper::CLEAN_STRINGS
		);
		$content = CheckerHelper::splitLines($content);

		$found = false;

		foreach ($content as $i => $line)
		{
			if (preg_match($this->errorreportingRegex, $line))
			{
				$found = true;
				$this->report->addWarning($file, Text::_('COM_JEDCHECKER_ERROR_ERRORREPORTING'), $i + 1, $origContent[$i]);
			}
		}

		return $found;
	}
}
