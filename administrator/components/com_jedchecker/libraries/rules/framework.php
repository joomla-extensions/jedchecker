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

// Include the rule base class
require_once JPATH_COMPONENT_ADMINISTRATOR . '/models/rule.php';

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

	protected $tests = false;

	protected $leftover_folders;

	/**
	 * Initiates the file search and check
	 *
	 * @return    void
	 */
	public function check()
	{
		$files = JFolder::files($this->basedir, '\.php$', true, true);
		$this->leftover_folders = explode(',', $this->params->get('leftover_folders'));

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
	 * Check if the given resource is a leftover folder
	 *
	 * @param   string  $file  The file name to test
	 *
	 * @return   boolean
	 */
	private function excludeResource($file)
	{
		// Warn about code versioning files included
		$result = false;

		foreach ($this->leftover_folders as $leftover_folder)
		{
			if (strpos($file, $leftover_folder) !== false)
			{
				$error_message = JText::_("COM_JEDCHECKER_ERROR_FRAMEWORK_GIT") . ":";
				$this->report->addWarning($file, $error_message, 0);
				$result = true;
			}
		}

		return $result;
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
		$cleanContent = preg_split("/(?:\r\n|\n|\r)(?!$)/", $this->cleanNonCode($file));

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

	/**
	 * @param   string $file
	 *
	 * @return  string
	 */
	protected function cleanNonCode($file)
	{
		$content = file_get_contents($file);

		if (!preg_match('/<\?php\s/i', $content, $match, PREG_OFFSET_CAPTURE))
		{
			// No PHP code found
			return '';
		}

		$pos = $match[0][1];
		$cleanContent = $this->removeContent(substr($content, 0, $pos));

		while (preg_match('/(?:[\'"]|\/\*|\/\/|\?>)/', $content, $match, PREG_OFFSET_CAPTURE, $pos))
		{
			$foundPos = $match[0][1];
			$cleanContent .= substr($content, $pos, $foundPos - $pos);
			$pos = $foundPos;

			switch ($match[0][0])
			{
				case '"':
				case "'":
					$q = $match[0][0];

					if (!preg_match("/$q(?>[^$q\\\\]+|\\\\.)*$q/As", $content, $match, 0, $pos))
					{
						return $cleanContent . $q;
					}

					$cleanContent .= $q . $this->removeContent($match[0]) . $q;
					$pos += strlen($match[0]);
					break;

				case '/*':
					$cleanContent .= '/*';
					$pos += 2;

					$endPos = strpos($content, '*/', $pos);

					if ($endPos === false)
					{
						return $cleanContent;
					}

					$cleanContent .= $this->removeContent(substr($content, $pos, $endPos - $pos)) . '*/';
					$pos = $endPos + 2;

					break;

				case '//':
					$pos += strcspn($content, "\r\n", $pos);
					break;

				case '?>':
					$cleanContent .= '?>';
					$pos += 2;

					if (!preg_match('/<\?php\s/i', $content, $match, PREG_OFFSET_CAPTURE, $pos))
					{
						// No PHP code found (up to the end of the file)
						return $cleanContent;
					}

					$foundPos = $match[0][1];
					$cleanContent .= $this->removeContent(substr($content, $pos, $foundPos - $pos)) . $match[0][0];
					$pos = $foundPos + strlen($match[0][0]);

					break;
			}
		}

		return $cleanContent;
	}

	/**
	 * Remove all text content by keeping newline characters only
	 *
	 * @param   string $content
	 *
	 * @return  string
	 */
	protected function removeContent($content)
	{
		return str_repeat("\n", substr_count($content, "\n"));
	}

	/**
	 * runs tests and reports to the appropriate function if strings match.
	 *
	 * @param   string  $file         The file name
	 * @param   array   $origContent  The file content
	 * @param   array   $cleanContent The file content w/o non-code elements
	 * @param   object  $testObject   The test object generated by getTests()
	 *
	 * @return boolean
	 */
	private function runTest($file, $origContent, $cleanContent, $testObject)
	{
		// @todo remove as unused?
		$error_count = 0;

		foreach ($cleanContent as $line_number => $line)
		{
			$origLine = $origContent[$line_number];

			foreach ($testObject->tests as $singleTest)
			{
				$regex = preg_quote($singleTest, '/');

				if (ctype_alpha($singleTest[0]))
				{
					$regex = '(?<=\W|^)' . $regex;
				}

				if (ctype_alpha($singleTest[strlen($singleTest) - 1]))
				{
					$regex .= '(?=\W|$)';
				}

				if (preg_match('/' . $regex . '/i', $line))
				{
					$origLine = str_ireplace($singleTest, '<b>' . $singleTest . '</b>', htmlspecialchars($origLine));
					$error_message = JText::_('COM_JEDCHECKER_ERROR_FRAMEWORK_' . strtoupper($testObject->group)) . ':<pre>' . $origLine . '</pre>';

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
							$this->report->addInfo($file, $error_message, $line_number);
							break;
					}
				}

				// If you scored 10 errors on a single file, that's enough for now.
				if ($error_count > 10)
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
					$newTest->tests = explode(",", $this->params->get($group));
					$this->tests[] = $newTest;
				}
			}
		}

		return $this->tests;
	}
}
