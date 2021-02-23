<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2021 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');


// Include the rule base class
require_once JPATH_COMPONENT_ADMINISTRATOR . '/models/rule.php';


/**
 * class JedcheckerRulesLanguage
 *
 * This class validates language ini file
 *
 * @since  2.3
 */
class JedcheckerRulesLanguage extends JEDcheckerRule
{
	/**
	 * The formal ID of this rule. For example: SE1.
	 *
	 * @var    string
	 */
	protected $id = 'LANG';

	/**
	 * The title or caption of this rule.
	 *
	 * @var    string
	 */
	protected $title = 'COM_JEDCHECKER_LANG';

	/**
	 * The description of this rule.
	 *
	 * @var    string
	 */
	protected $description = 'COM_JEDCHECKER_LANG_DESC';

	/**
	 * Initiates the search and check
	 *
	 * @return    void
	 */
	public function check()
	{
		// Find all INI files of the extension (in the format tag.extension.ini or tag.extension.sys.ini)
		$files = JFolder::files($this->basedir, '^[a-z]{2,3}-[A-Z]{2}\.\w+(?:\.sys)?\.ini$', true, true);

		// Iterate through all the ini files
		foreach ($files as $file)
		{
			// Try to validate the file
			$this->find($file);
		}
	}

	/**
	 * Reads and validates an ini file
	 *
	 * @param   string  $file  - The path to the file
	 *
	 * @return bool True on success, otherwise False.
	 */
	protected function find($file)
	{
		$lines = file($file);
		foreach ($lines as $lineno => $line)
		{
			$line = trim($line);

			// Check for BOM sequence
			if ($lineno === 0 && strncmp($line, "\xEF\xBB\xBF", 3) === 0)
			{
				$this->report->addError($file, JText::_('COM_JEDCHECKER_LANG_BOM_FOUND'), 1);
				// Romeve BOM for further checks
				$line = substr($line, 3);
			}

			// Skip empty lines, comments, and section names
			if ($line === '' || $line[0] === ';' || $line[0] === '[')
			{
				continue;
			}

			// Report incorrect comment character
			if ($line[0] === '#')
			{
				$this->report->addError($file, JText::_('COM_JEDCHECKER_LANG_INCORRECT_COMMENT'), $lineno, $line);
				continue;
			}

			// Check for "=" character in the line
			if (strpos($line, '=') === false)
			{
				$this->report->addError($file, JText::_('COM_JEDCHECKER_LANG_WRONG_LINE'), $lineno, $line);
				continue;
			}

			// Extract key and value
			list ($key, $value) = explode('=', $line, 2);

			$key = rtrim($key);

			// Check for empty key
			if ($key === '')
			{
				$this->report->addError($file, JText::_('COM_JEDCHECKER_LANG_KEY_EMPTY'), $lineno, $line);
				continue;
			}

			// Check for spaces in the key name
			if (strpos($key, ' ') !== false)
			{
				$this->report->addError($file, JText::_('COM_JEDCHECKER_LANG_KEY_WHITESPACE'), $lineno, $line);
				continue;
			}

			// Check for invalid characters (see https://www.php.net/manual/en/function.parse-ini-file.php)
			if (strpbrk($key, '{}|&~![()^"') !== false)
			{
				$this->report->addError($file, JText::_('COM_JEDCHECKER_LANG_KEY_INVALID_CHARACTER'), $lineno, $line);
				continue;
			}

			// Check for invalid key names (see https://www.php.net/manual/en/function.parse-ini-file.php)
			if (in_array($key, array('null', 'yes', 'no', 'true', 'false', 'on', 'off', 'none'), true))
			{
				$this->report->addError($file, JText::_('COM_JEDCHECKER_LANG_KEY_RESERVED'), $lineno, $line);
				continue;
			}

			$value = ltrim($value);

			if (strlen($value) <2 || $value[0] !== '"' || substr($value, -1) !== '"')
			{
				$this->report->addError($file, JText::_('COM_JEDCHECKER_LANG_TRANSLATION_QUOTES'), $lineno, $line);
				continue;
			}

			$value = trim($matches[1]);
			// Check for empty value
			if ($value === '""')
			{
				$this->report->addWarning($file, JText::_('COM_JEDCHECKER_LANG_TRANSLATION_EMPTY'), $lineno, $line);
				continue;
			}

			if (strlen($value) < 2 || $value[0] !== '"' || substr($value, -1) !== '"')
			// Remove quotes around
			$value = substr($value, 1, -1);

			// Check for legacy "_QQ_" code (deprecated since Joomla! 3.9 if favor of escaped double quote \"; removed in Joomla! 4)
			if (strpos($value, '"_QQ_"') !== false)
			{
				$this->report->addInfo($file, JText::_('COM_JEDCHECKER_LANG_QQ_DEPRECATED'), $lineno, $line);
			}

			// Count %... formats in the string
			$count1 = preg_match_all('/(?<=^|[^%])%(?=[-+0 ]?\w)/', $value);

			// Count %n$... (argnum) formats in the string
			$count2 = preg_match_all('/(?<=^|[^%])%\d+\$/', $value);

			if ($count1 > 1 && $count2 < $count1) {
				// @todo It's not mentioned in docs
				$this->report->addInfo($file, JText::_('COM_JEDCHECKER_LANG_RECOMMEND_ARGNUM'), $startLineno, $line);
			}
		}

		// All checks passed. Return true
		return true;
	}
}
