<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2019 Open Source Matters, Inc. All rights reserved.
 * 			   Copyright (C) 2008 - 2016 fasterjoomla.com. All rights reserved.
 * @author     Riccardo Zorn <support@fasterjoomla.com>
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Text;

// Include the rule base class
require_once JPATH_COMPONENT_ADMINISTRATOR . '/models/rule.php';

// Include the helper class
require_once JPATH_COMPONENT_ADMINISTRATOR . '/libraries/helper.php';

/**
 * JedcheckerRulesFramework
 *
 * @since  2014-02-23
 * Attempts to identify deprecated code, unsafe code, leftover stuff
 */
class JedcheckerRulesFramework extends JEDcheckerRule
{
	/**
	 * The formal ID of this rule. For example: SE1.
	 *
	 * @var    string
	 */
	protected $id = 'Framework';

	/**
	 * The title or caption of this rule.
	 *
	 * @var    string
	 */
	protected $title = 'COM_JEDCHECKER_RULE_FRAMEWORK';

	/**
	 * The description of this rule.
	 *
	 * @var    string
	 */
	protected $description = 'COM_JEDCHECKER_RULE_FRAMEWORK_DESC';

	/**
	 * The ordering value to sort rules in the menu.
	 *
	 * @var    integer
	 */
	public static $ordering = 700;

	protected $tests = false;

	protected $regexLeftoverFolders;

	/**
	 * Initiates the file search and check
	 *
	 * @return    void
	 */
	public function check()
	{
		// Warn about code versioning files included
		$leftoverFolders = $this->params->get('leftover_folders');
		$leftoverFoldersWhitelist = $this->params->get('leftover_folders_whitelist');

		$this->regexLeftoverFolders = '';

		if (!empty($leftoverFoldersWhitelist))
		{
			$this->regexLeftoverFolders .=
				'(?!(?:'
				. str_replace(array(',', '\*'), array('|', '.*'), preg_quote($leftoverFoldersWhitelist, '/'))
				. '))';
		}

		$this->regexLeftoverFolders .= '(?:' . str_replace(array(',', '\*'), array('|', '.*'), preg_quote($leftoverFolders, '/')) . ')';

		$regexLeftoverFolders = '^' . $this->regexLeftoverFolders . '$';

		// Get matched files and folder (w/o default exclusion list)
		$folders = Folder::folders($this->basedir, $regexLeftoverFolders, true, true, array(), array());
		$files = Folder::files($this->basedir, $regexLeftoverFolders, true, true, array(), array());

		if ($folders !== false)
		{
			// Warn on leftover folders found
			foreach ($folders as $folder)
			{
				$this->report->addWarning($folder, Text::_("COM_JEDCHECKER_ERROR_FRAMEWORK_LEFTOVER_FOLDER"));
			}
		}

		if ($files !== false)
		{
			// Warn on leftover files found
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
				// Process the file
				if ($this->find($file))
				{
					// Error messages are set by find() based on the errors found.
				}
			}
		}
	}

	/**
	 * Check if the given resource is inside of a leftover folder
	 *
	 * @param   string  $file  The file name to test
	 *
	 * @return   boolean
	 */
	private function excludeResource($file)
	{
		return (bool) preg_match('/\/' . $this->regexLeftoverFolders . '\//', $file);
	}

	/**
	 * reads a file and searches for any function defined in the params
	 *
	 * @param   string  $file  The file name
	 *
	 * @return    boolean            True if the statement was found, otherwise False.
	 */
	protected function find($file)
	{
		$origContent = (array) file($file);

		if (count($origContent) === 0)
		{
			return false;
		}

		$result = false;

		$content = file_get_contents($file);

		// Check BOM
		if (strncmp($content, "\xEF\xBB\xBF", 3) === 0)
		{
			$this->report->addError($file, Text::_('COM_JEDCHECKER_ERROR_FRAMEWORK_BOM_FOUND'));
			$result = true;
		}

		// Report spaces/tabs/EOLs at the beginning of file
		if (strpos(" \t\n\r\v\f", $content[0]) !== false)
		{
			$this->report->addNotice($file, Text::_('COM_JEDCHECKER_ERROR_FRAMEWORK_LEADING_SPACES'));
			$result = true;
		}

		// Clean non-code
		$cleanContent = JEDCheckerHelper::cleanPhpCode(
			$content,
			JEDCheckerHelper::CLEAN_HTML | JEDCheckerHelper::CLEAN_COMMENTS | JEDCheckerHelper::CLEAN_STRINGS
		);

		// Check short PHP tag
		if (preg_match('/<\?\s/', $cleanContent, $match, PREG_OFFSET_CAPTURE))
		{
			$lineno = substr_count($cleanContent, "\n", 0, $match[0][1]);
			$this->report->addError($file, Text::_('COM_JEDCHECKER_ERROR_FRAMEWORK_SHORT_PHP_TAG'), $lineno + 1, $origContent[$lineno]);
			$result = true;
		}

		$cleanContentKeepStrings = JEDCheckerHelper::cleanPhpCode(
			$content,
			JEDCheckerHelper::CLEAN_HTML | JEDCheckerHelper::CLEAN_COMMENTS
		);

		$cleanContent = JEDCheckerHelper::splitLines($cleanContent);
		$cleanContentKeepStrings = JEDCheckerHelper::splitLines($cleanContentKeepStrings);

		// Run other tests
		foreach ($this->getTests() as $testObject)
		{
			if ($this->runTest($file, $origContent, $cleanContent, $cleanContentKeepStrings, $testObject))
			{
				$result = true;
			}
		}

		return $result;
	}

	/**
	 * runs tests and reports to the appropriate function if strings match.
	 *
	 * @param   string  $file         The file name
	 * @param   array   $origContent  The file content
	 * @param   array   $cleanContent The file content w/o non-code elements
	 * @param   array   $cleanContentKeepStrings The file content w/o comments and HTML
	 * @param   object  $testObject   The test object generated by getTests()
	 *
	 * @return boolean
	 */
	private function runTest($file, $origContent, $cleanContent, $cleanContentKeepStrings, $testObject)
	{
		// @todo remove as unused?
		$error_count = 0;

		foreach ($cleanContent as $line_number => $line)
		{
			$origLine = $origContent[$line_number];

			foreach ($testObject->tests as $singleTest)
			{
				$lineContent = $singleTest->keepStrings ? $cleanContentKeepStrings[$line_number] : $line;

				if (preg_match($singleTest->regex, $lineContent))
				{
					$origLine = str_ireplace($singleTest->test, '<b>' . $singleTest->test . '</b>', htmlspecialchars($origLine));
					$error_message = Text::_('COM_JEDCHECKER_ERROR_FRAMEWORK_' . strtoupper($testObject->group)) . ':<pre>' . $origLine . '</pre>';

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
							// Case 'notice':
							$this->report->addNotice($file, $error_message, $line_number);
							break;
					}
				}

				// If you scored 100 errors on a single file, that's enough for now.
				if ($error_count > 100)
				{
					return true;
				}
			}
		}

		return $error_count > 0;
	}

	/**
	 * Lazyloads the tests from the framework.ini params.
	 * The whole structure depends on the file. The vars
	 * error_groups, warning_groups, notice_groups, compatibility_groups
	 * serve as lists of other rules, which are grouped and show a different error message per rule.
	 * Please note: if you want to add more rules, simply do so in the .ini file
	 * BUT MAKE SURE that you add the relevant key to the translation files:
	 * 		COM_JEDCHECKER_ERROR_NOFRAMEWOR_SOMEKEY
	 *
	 * @return array
	 */
	private function getTests()
	{
		if (!$this->tests)
		{
			// Build the test array. Please read the comments in the framework.ini file
			$this->tests = array();
			$testNames = array('error','warning','notice','compatibility');

			foreach ($testNames as $test)
			{
				foreach (explode(",", $this->params->get($test . '_groups')) as $group)
				{
					$newTest = new stdClass;
					$newTest->group = $group;
					$newTest->kind = $test;
					$newTest->tests = array();

					foreach (explode(",", $this->params->get($group)) as $test)
					{
						if (strpos($test, '=>') !== false)
						{
							list($test, $replacement) = explode('=>', $test, 2);
						}
						else
						{
							$replacement = false;
						}

						$testObj = new stdClass;
						$testObj->test = $test;
						$testObj->regex = $this->generateRegex($test);
						$testObj->replacement = $replacement;
						$testObj->keepStrings = strpos($test, "'") !== false;

						$newTest->tests[] = $testObj;
					}

					$this->tests[] = $newTest;
				}
			}

			$newTest = new stdClass;
			$newTest->group = 'j5legacy';
			$newTest->kind = 'compatibility';
			$newTest->tests = array();

			// Too many tests for j5legacy group, so that they are extracted to separate file
			$j5legacy = parse_ini_file(__DIR__ . '/framework_j5legacy.ini');

			foreach ($j5legacy as $oldClass => $newClass)
			{
				$testObj = new stdClass;
				$testObj->test = $oldClass;
				$testObj->regex = $this->generateRegex($oldClass, true);
				$testObj->replacement = $newClass;
				$testObj->keepStrings = false;

				$newTest->tests[] = $testObj;
			}

			$this->tests[] = $newTest;
		}

		return $this->tests;
	}

	/**
	 * Generates regular expression for a given test
	 *
	 * @param   string  $test       The string to match
	 * @param   boolean $matchCase  True for case-sensitive matching
	 *
	 * @return string
	 */
	private function generateRegex($test, $matchCase = false)
	{
		$regex = preg_quote($test, '/');

		// Add word boundary check for rules staring/ending with a letter (to avoid false-positives because of partial match)
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
