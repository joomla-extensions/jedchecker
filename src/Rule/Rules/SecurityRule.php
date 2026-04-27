<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2025 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public Licence version 2 or later; see LICENCE.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Rule\Rules;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\Component\Jedchecker\Administrator\Rule\AbstractRule;
use Joomla\Filesystem\Folder;

/**
 * SecurityRule checks for obfuscated loaders, executable files, and badly named files.
 *
 * @since  3.0
 */
class SecurityRule extends AbstractRule
{
	protected string $id          = 'Security';
	protected string $title       = 'COM_JEDCHECKER_RULE_SECURITY';
	protected string $description = 'COM_JEDCHECKER_RULE_SECURITY_DESC';

	public static int $ordering = 750;

	protected string $obfuscatedRegex = '';
	protected array  $executableExts  = [];
	protected array  $shellExts       = [];
	protected string $badChars        = '';

	public function check(): void
	{
		$this->executableExts = array_map('trim', explode(',', $this->params->get('executable_extensions', '')));
		$this->shellExts      = array_map('trim', explode(',', $this->params->get('shell_extensions', '')));
		$this->badChars       = $this->params->get('bad_filename_chars', '');

		$this->buildObfuscatedRegex();

		$files = Folder::files($this->basedir, '.', true, true);

		foreach ($files as $file)
		{
			$this->checkBadFilename($file);
			$this->checkExecutableFile($file);

			if (preg_match('/\.php$/i', $file))
			{
				$this->checkObfuscatedCode($file);
			}
		}
	}

	protected function buildObfuscatedRegex(): void
	{
		$patterns    = explode(',', $this->params->get('obfuscated_patterns', ''));
		$regexParts  = [];

		foreach ($patterns as $pattern)
		{
			$pattern = trim($pattern);

			if (empty($pattern))
			{
				continue;
			}

			$regexParts[] = $this->generateRegex($pattern);
		}

		if (!empty($regexParts))
		{
			$this->obfuscatedRegex = '/(?:' . implode('|', $regexParts) . ')/';
		}
	}

	protected function generateRegex(string $pattern): string
	{
		$regex = preg_quote($pattern, '/');

		if (preg_match('/\w/', $pattern[0]))
		{
			$regex = '\b' . $regex;
		}

		if (preg_match('/\w/', $pattern[strlen($pattern) - 1]))
		{
			$regex .= '\b';
		}

		return $regex;
	}

	protected function checkObfuscatedCode(string $file): void
	{
		if (empty($this->obfuscatedRegex))
		{
			return;
		}

		$content = file_get_contents($file);

		if (empty($content))
		{
			return;
		}

		if (preg_match($this->obfuscatedRegex, $content, $matches))
		{
			$this->report->addError($file, Text::sprintf('COM_JEDCHECKER_ERROR_SECURITY_OBFUSCATED_CODE', $matches[0]));
		}
	}

	protected function checkExecutableFile(string $file): void
	{
		$extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

		if (in_array($extension, $this->executableExts))
		{
			$this->report->addWarning($file, Text::_('COM_JEDCHECKER_ERROR_SECURITY_EXECUTABLE_FILE'));

			return;
		}

		if (in_array($extension, $this->shellExts))
		{
			$this->report->addWarning($file, Text::_('COM_JEDCHECKER_ERROR_SECURITY_SHELL_SCRIPT'));

			return;
		}

		$this->checkShebang($file);
	}

	protected function checkShebang(string $file): void
	{
		$handle = @fopen($file, 'r');

		if ($handle === false)
		{
			return;
		}

		$firstBytes = fread($handle, 2);
		fclose($handle);

		if ($firstBytes === '#!')
		{
			$this->report->addWarning($file, Text::_('COM_JEDCHECKER_ERROR_SECURITY_SHEBANG_FILE'));
		}
	}

	protected function checkBadFilename(string $file): void
	{
		if (empty($this->badChars))
		{
			return;
		}

		$filename = basename($file);
		$chars    = strpbrk($filename, $this->badChars);

		if ($chars !== false)
		{
			$char        = $chars[0];
			$displayChar = ($char === ' ') ? 'space' : $char;
			$this->report->addNotice($file, Text::sprintf('COM_JEDCHECKER_ERROR_SECURITY_BAD_FILENAME_CHAR', $displayChar));
		}
	}
}
