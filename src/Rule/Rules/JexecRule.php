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

namespace Joomla\Component\Jedchecker\Administrator\Rule\Rules;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\Component\Jedchecker\Administrator\Rule\AbstractRule;

/**
 * JexecRule searches all files for the _JEXEC guard which prevents direct file access.
 *
 * @since  3.0
 */
class JexecRule extends AbstractRule
{
	protected string $id          = 'PH2';
	protected string $title       = 'COM_JEDCHECKER_RULE_PH2';
	protected string $description = 'COM_JEDCHECKER_RULE_PH2_DESC';

	public static int $ordering = 600;

	protected string $regex;
	protected string $regexExcludeFolders;
	protected array  $libFiles;

	public function check(): void
	{
		$this->report->setDefaultSubtype($this->id);
		$this->initJexec();

		$files = $this->files($this->basedir);

		foreach ($files as $file)
		{
			if (!$this->find($file))
			{
				$this->report->addError($file, Text::_('COM_JEDCHECKER_ERROR_JEXEC_NOT_FOUND'));
			}
		}
	}

	protected function find(string $file): bool
	{
		$content = php_strip_whitespace($file);
		$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

		if ($content === '' || preg_match('#^\s*<\?php\s+$#', $content))
		{
			return true;
		}

		return (bool) preg_match($this->regex, $content);
	}

	protected function initJexec(): void
	{
		$defines = explode(',', $this->params->get('constants'));

		foreach ($defines as $i => $define)
		{
			$defines[$i] = preg_quote(trim($define), '#');
		}

		$use_single_ns = '[0-9A-Za-z_\\\\]+(?: as [0-9A-Za-z_]+?)? ?';
		$use_group_ns  = '[0-9A-Za-z_\\\\]+\\\\\\{ ?' . $use_single_ns . '(?:, ?' . $use_single_ns . ' ?)*\\} ?';
		$use_ns        = '(?:' . $use_single_ns . '|' . $use_group_ns . ')(?:, ?(?:' . $use_single_ns . '|' . $use_group_ns . '))*';

		$this->regex =
			'#^\s*'
			. '<\?php\s+'
			. '(?:declare ?\(strict_types ?= ?1 ?\) ?; ?)?'
			. '(?:namespace [0-9A-Za-z_\\\\]+ ?; ?)?'
			. '(?:use (?:function |const )?' . $use_ns . '; ?)*'
			. '\\\\?defined ?\( ?'
			. '([\'"])(?:' . implode('|', $defines) . ')\1'
			. ' ?\) ?(?:or |\|\| ?)(?:die|exit)\b'
			. '#i';

		$libfolders = explode(',', $this->params->get('libfolders'));

		foreach ($libfolders as &$libfolder)
		{
			$libfolder = preg_quote(trim($libfolder), '#');
		}

		$this->regexExcludeFolders = '#^(?:\.svn|CVS|\.DS_Store|__MACOSX|' . implode('|', $libfolders) . ')$#';

		$this->libFiles = array_map('trim', explode(',', $this->params->get('libfiles')));
	}

	protected function files(string $path, int $level = 0): array
	{
		$arr = [];

		if ($handle = @opendir($path))
		{
			while (($file = readdir($handle)) !== false)
			{
				if ($file !== '.' && $file !== '..' && !preg_match($this->regexExcludeFolders, $file))
				{
					$fullpath = $path . '/' . $file;

					if (is_dir($fullpath))
					{
						if ($level > 0)
						{
							foreach ($this->libFiles as $libFile)
							{
								if (is_file($fullpath . '/' . $libFile))
								{
									continue 2;
								}
							}
						}

						$arr = array_merge($arr, $this->files($fullpath, $level + 1));
					}
					elseif (preg_match('/\.php$/', $file))
					{
						$arr[] = $fullpath;
					}
				}
			}

			closedir($handle);
		}

		return $arr;
	}
}
