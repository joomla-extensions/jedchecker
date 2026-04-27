<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2025 Open Source Matters, Inc. All rights reserved.
 *             Copyright (C) 2008 - 2016 fasterjoomla.com. All rights reserved.
 * @author     Riccardo Zorn <support@fasterjoomla.com>
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
 * FrameworkRule identifies deprecated code, unsafe code, and leftover development files.
 *
 * @since  3.0
 */
class FrameworkRule extends AbstractRule
{
	protected string $id          = 'Framework';
	protected string $title       = 'COM_JEDCHECKER_RULE_FRAMEWORK';
	protected string $description = 'COM_JEDCHECKER_RULE_FRAMEWORK_DESC';

	public static int $ordering = 700;

	protected $tests = false;
	protected string $regexLeftoverFolders;

	/** Data files remain in libraries/rules/ during the transitional migration period */
	private const DATA_DIR = '/libraries/rules/';

	public function check(): void
	{
		$leftoverFolders          = $this->params->get('leftover_folders');
		$leftoverFoldersWhitelist = $this->params->get('leftover_folders_whitelist');

		$this->regexLeftoverFolders = '';

		if (!empty($leftoverFoldersWhitelist))
		{
			$this->regexLeftoverFolders .=
				'(?!(?:'
				. str_replace([',', '\*'], ['|', '.*'], preg_quote($leftoverFoldersWhitelist, '/'))
				. '))';
		}

		$this->regexLeftoverFolders .= '(?:' . str_replace([',', '\*'], ['|', '.*'], preg_quote($leftoverFolders, '/')) . ')';

		$regexLeftoverFolders = '^' . $this->regexLeftoverFolders . '$';

		$folders = Folder::folders($this->basedir, $regexLeftoverFolders, true, true, [], []);
		$files   = Folder::files($this->basedir, $regexLeftoverFolders, true, true, [], []);

		if (is_array($folders))
		{
			foreach ($folders as $folder)
			{
				$this->report->addWarning($folder, Text::_("COM_JEDCHECKER_ERROR_FRAMEWORK_LEFTOVER_FOLDER"));
			}
		}

		if (is_array($files))
		{
			foreach ($files as $file)
			{
				$this->report->addWarning($file, Text::_("COM_JEDCHECKER_ERROR_FRAMEWORK_LEFTOVER_FILE"));
			}
		}

		$files = Folder::files($this->basedir, '\.php$', true, true);

		foreach ($files as $file)
		{
			if (!$this->excludeResource($file))
			{
				$this->find($file);
			}
		}
	}

	private function excludeResource(string $file): bool
	{
		return (bool) preg_match('/\/' . $this->regexLeftoverFolders . '\//', $file);
	}

	protected function find(string $file): bool
	{
		$origContent = (array) file($file);

		if (count($origContent) === 0)
		{
			return false;
		}

		$result  = false;
		$content = file_get_contents($file);

		if (strncmp($content, "\xEF\xBB\xBF", 3) === 0)
		{
			$this->report->addError($file, Text::_('COM_JEDCHECKER_ERROR_FRAMEWORK_BOM_FOUND'));
			$result = true;
		}

		if (strpos(" \t\n\r\v\f", $content[0]) !== false)
		{
			$this->report->addNotice($file, Text::_('COM_JEDCHECKER_ERROR_FRAMEWORK_LEADING_SPACES'));
			$result = true;
		}

		$cleanContent = CheckerHelper::cleanPhpCode(
			$content,
			CheckerHelper::CLEAN_HTML | CheckerHelper::CLEAN_COMMENTS | CheckerHelper::CLEAN_STRINGS
		);
		$cleanContent = CheckerHelper::resolveAliases($cleanContent);

		if (preg_match('/<\?\s/', $cleanContent, $match, PREG_OFFSET_CAPTURE))
		{
			$lineno = substr_count($cleanContent, "\n", 0, $match[0][1]);
			$this->report->addError($file, Text::_('COM_JEDCHECKER_ERROR_FRAMEWORK_SHORT_PHP_TAG'), $lineno + 1, $origContent[$lineno]);
			$result = true;
		}

		$cleanContentKeepStrings = CheckerHelper::cleanPhpCode(
			$content,
			CheckerHelper::CLEAN_HTML | CheckerHelper::CLEAN_COMMENTS
		);

		$cleanContent            = CheckerHelper::splitLines($cleanContent);
		$cleanContentKeepStrings = CheckerHelper::splitLines($cleanContentKeepStrings);

		foreach ($this->getTests() as $testObject)
		{
			if ($this->runTest($file, $origContent, $cleanContent, $cleanContentKeepStrings, $testObject))
			{
				$result = true;
			}
		}

		return $result;
	}

	private function runTest(string $file, array $origContent, array $cleanContent, array $cleanContentKeepStrings, object $testObject): bool
	{
		$error_count = 0;

		foreach ($cleanContent as $line_number => $line)
		{
			$origLine = $origContent[$line_number];

			foreach ($testObject->tests as $singleTest)
			{
				$lineContent = $singleTest->keepStrings ? $cleanContentKeepStrings[$line_number] : $line;

				if (preg_match($singleTest->regex, $lineContent))
				{
					$highlightedLine = str_ireplace($singleTest->test, '<b>' . $singleTest->test . '</b>', $origLine);
					$highlightedLine = htmlspecialchars($highlightedLine, ENT_NOQUOTES);
					$highlightedLine = str_replace(['&lt;b&gt;', '&lt;/b&gt;'], ['<b>', '</b>'], $highlightedLine);

					if (isset($testObject->version) && preg_match('/^(deprecated|removed)-in-j-/', $testObject->group))
					{
						$langKey       = (strpos($testObject->group, 'deprecated') === 0) ? 'COM_JEDCHECKER_ERROR_FRAMEWORK_DEPRECATED' : 'COM_JEDCHECKER_ERROR_FRAMEWORK_REMOVED';
						$error_message = sprintf(Text::_($langKey), $testObject->version) . ':<pre>' . $highlightedLine . '</pre>';
					}
					else
					{
						$error_message = Text::_('COM_JEDCHECKER_ERROR_FRAMEWORK_' . strtoupper($testObject->group)) . ':<pre>' . $highlightedLine . '</pre>';
					}

					if ($singleTest->replacement !== false)
					{
						$error_message .= Text::_('COM_JEDCHECKER_ERROR_FRAMEWORK_INSTEAD_USE') . ': ' . $singleTest->replacement;
					}

					switch ($testObject->kind)
					{
						case 'error':
							$this->report->addError($file, $error_message, $line_number);
							break;
						case 'warning':
							$this->report->addWarning($file, $error_message, $line_number);
							break;
						case 'compatibility':
							$this->report->addCompat($file, $error_message, $line_number);
							break;
						default:
							$this->report->addNotice($file, $error_message, $line_number);
							break;
					}
				}

				if ($error_count > 100)
				{
					return true;
				}
			}
		}

		return $error_count > 0;
	}

	private function getTests(): array
	{
		if (!$this->tests)
		{
			$this->tests = [];
			$testNames   = ['error', 'warning', 'notice', 'compatibility'];

			foreach ($testNames as $test)
			{
				foreach (explode(",", $this->params->get($test . '_groups')) as $group)
				{
					$newTest        = new \stdClass;
					$newTest->group = $group;
					$newTest->kind  = $test;
					$newTest->tests = [];

					foreach (explode(",", $this->params->get($group)) as $match)
					{
						if (strpos($match, '=>') !== false)
						{
							[$match, $replacement] = explode('=>', $match, 2);
						}
						else
						{
							$replacement = false;
						}

						$testObj              = new \stdClass;
						$testObj->test        = $match;
						$testObj->regex       = $this->generateRegex($match);
						$testObj->replacement = $replacement;
						$testObj->keepStrings = strpos($match, "'") !== false;

						$newTest->tests[] = $testObj;
					}

					$this->tests[] = $newTest;
				}
			}

			$newTest        = new \stdClass;
			$newTest->group = 'legacy_aliases';
			$newTest->kind  = 'compatibility';
			$newTest->tests = [];

			$legacyAliases = parse_ini_file(JPATH_COMPONENT_ADMINISTRATOR . self::DATA_DIR . 'framework_legacy_aliases.ini', true);

			foreach ($legacyAliases as $version => $aliases)
			{
				foreach ($aliases as $oldClass => $newClass)
				{
					$testObj              = new \stdClass;
					$testObj->test        = $oldClass;
					$testObj->regex       = $this->generateRegex($oldClass, true);
					$testObj->replacement = $newClass;
					$testObj->keepStrings = false;

					$newTest->tests[] = $testObj;
				}
			}

			$this->tests[] = $newTest;

			$this->loadDeprecatedPatterns();
		}

		return $this->tests;
	}

	private function loadDeprecatedPatterns(): void
	{
		$deprecatedFile = JPATH_COMPONENT_ADMINISTRATOR . self::DATA_DIR . 'framework_deprecated.json';

		if (!file_exists($deprecatedFile))
		{
			return;
		}

		$jsonContent = file_get_contents($deprecatedFile);

		if ($jsonContent === false)
		{
			return;
		}

		$sections = json_decode($jsonContent, true);

		if (!is_array($sections))
		{
			return;
		}

		foreach ($sections as $sectionName => $patterns)
		{
			$newTest        = new \stdClass;
			$newTest->group = $sectionName;
			$newTest->kind  = 'compatibility';
			$newTest->tests = [];

			if (preg_match('/(?:deprecated|removed)-in-j-(.+)/', $sectionName, $matches))
			{
				$newTest->version = $matches[1];
			}
			else
			{
				$newTest->version = null;
			}

			foreach ($patterns as $pattern => $replacement)
			{
				$testObj              = new \stdClass;
				$testObj->test        = $pattern;
				$testObj->regex       = $this->generateRegex($pattern);
				$testObj->replacement = ($replacement !== '') ? $replacement : false;
				$testObj->keepStrings = strpos($pattern, "'") !== false;

				$newTest->tests[] = $testObj;
			}

			$this->tests[] = $newTest;
		}
	}

	private function generateRegex(string $test, bool $matchCase = false): string
	{
		$regex = preg_quote($test, '/');

		if (ctype_alpha($test[0]))
		{
			$regex = '\b' . $regex;
		}

		if (ctype_alpha($test[strlen($test) - 1]))
		{
			$regex .= '\b';
		}

		return '/' . $regex . '/' . ($matchCase ? '' : 'i');
	}
}
