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
use Joomla\Component\Jedchecker\Administrator\Helper\CheckerHelper;
use Joomla\Component\Jedchecker\Administrator\Rule\AbstractRule;
use Joomla\Filesystem\Folder;

/**
 * PhpRule checks for PHP compatibility issues — deprecated and removed functions.
 *
 * @since  3.0
 */
class PhpRule extends AbstractRule
{
	protected string $id          = 'PHP';
	protected string $title       = 'COM_JEDCHECKER_RULE_PHP';
	protected string $description = 'COM_JEDCHECKER_RULE_PHP_DESC';

	public static int $ordering = 725;

	protected $tests = false;

	/** Data files remain in libraries/rules/ during the transitional migration period */
	private const DATA_DIR = '/libraries/rules/';

	public function check(): void
	{
		$files = Folder::files($this->basedir, '\.php$', true, true);

		foreach ($files as $file)
		{
			$this->find($file);
		}
	}

	protected function find(string $file): bool
	{
		$origContent = (array) file($file);

		if (count($origContent) === 0)
		{
			return false;
		}

		$content = file_get_contents($file);

		$cleanContent = CheckerHelper::cleanPhpCode(
			$content,
			CheckerHelper::CLEAN_HTML | CheckerHelper::CLEAN_COMMENTS | CheckerHelper::CLEAN_STRINGS
		);
		$cleanContent = CheckerHelper::splitLines($cleanContent);

		$result = false;

		foreach ($this->getTests() as $testObject)
		{
			if ($this->runTest($file, $origContent, $cleanContent, $testObject))
			{
				$result = true;
			}
		}

		return $result;
	}

	private function runTest(string $file, array $origContent, array $cleanContent, object $testObject): bool
	{
		$error_count = 0;

		foreach ($cleanContent as $line_number => $line)
		{
			$origLine = $origContent[$line_number];

			foreach ($testObject->tests as $singleTest)
			{
				if (preg_match($singleTest->regex, $line))
				{
					$highlightedLine = str_ireplace($singleTest->test, '<b>' . $singleTest->test . '</b>', $origLine);
					$highlightedLine = htmlspecialchars($highlightedLine, ENT_NOQUOTES);
					$highlightedLine = str_replace(['&lt;b&gt;', '&lt;/b&gt;'], ['<b>', '</b>'], $highlightedLine);

					$langKey       = (strpos($testObject->group, 'deprecated') === 0) ? 'COM_JEDCHECKER_ERROR_PHP_DEPRECATED' : 'COM_JEDCHECKER_ERROR_PHP_REMOVED';
					$error_message = sprintf(Text::_($langKey), $testObject->version) . ':<pre>' . $highlightedLine . '</pre>';

					if ($singleTest->replacement !== false)
					{
						$error_message .= Text::_('COM_JEDCHECKER_ERROR_PHP_INSTEAD_USE') . ': ' . $singleTest->replacement;
					}

					switch ($testObject->kind)
					{
						case 'error':
							$this->report->addError($file, $error_message, $line_number);
							break;
						default:
							$this->report->addWarning($file, $error_message, $line_number);
							break;
					}

					$error_count++;
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
			$this->loadDeprecatedPatterns();
		}

		return $this->tests;
	}

	private function loadDeprecatedPatterns(): void
	{
		$deprecatedFile = JPATH_COMPONENT_ADMINISTRATOR . self::DATA_DIR . 'php_deprecated.json';

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
			$newTest->kind  = (strpos($sectionName, 'removed') === 0) ? 'error' : 'warning';
			$newTest->tests = [];

			if (preg_match('/(?:deprecated|removed)-in-php-(.+)/', $sectionName, $matches))
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

				$newTest->tests[] = $testObj;
			}

			$this->tests[] = $newTest;
		}
	}

	private function generateRegex(string $test): string
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

		return '/' . $regex . '/i';
	}
}
