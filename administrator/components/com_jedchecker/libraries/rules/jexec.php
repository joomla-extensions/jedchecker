<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2019 Open Source Matters, Inc. All rights reserved.
 * 			   Copyright (C) 2008 - 2016 compojoom.com . All rights reserved.
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 * 			   eaxs <support@projectfork.net>
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');

// Include the rule base class
require_once JPATH_COMPONENT_ADMINISTRATOR . '/models/rule.php';

/**
 * class JedcheckerRulesJexec
 *
 * This class searches all files for the _JEXEC check
 * which prevents direct file access.
 *
 * @since  1.0
 */
class JedcheckerRulesJexec extends JEDcheckerRule
{
	/**
	 * The formal ID of this rule. For example: SE1.
	 *
	 * @var    string
	 */
	protected $id = 'PH2';

	/**
	 * The title or caption of this rule.
	 *
	 * @var    string
	 */
	protected $title = 'COM_JEDCHECKER_RULE_PH2';

	/**
	 * The description of this rule.
	 *
	 * @var    string
	 */
	protected $description = 'COM_JEDCHECKER_RULE_PH2_DESC';

	/**
	 * Regexp to match _JEXEC-like guard
	 *
	 * @var    string
	 */
	protected $regex;

	/**
	 * Initiates the file search and check
	 *
	 * @return    void
	 */
	public function check()
	{
		$this->init_jexec();

		// Find all php files of the extension
		$files = JFolder::files($this->basedir, '.php$', true, true);

		// Iterate through all files
		foreach ($files as $file)
		{
			// Try to find the _JEXEC check in the file
			if (!$this->find($file))
			{
				// Add as error to the report if it was not found
				$this->report->addError($file, JText::_('COM_JEDCHECKER_ERROR_JEXEC_NOT_FOUND'));
			}
		}
	}

	/**
	 * Reads a file and searches for the _JEXEC statement
	 *
	 * @param   string  $file  - The path to the file
	 *
	 * @return boolean True if the statement was found, otherwise False.
	 */
	protected function find($file)
	{
		// load file and strip comments
		$content = php_strip_whitespace($file);

		// skip empty files
		if (preg_match('#^<\?php\s+$#', $content))
		{
			return true;
		}

		// check guards
		if (preg_match($this->regex, $content))
		{
			return true;
		}

		// check there is no intermittent PHP and HTML codes
		if (strrpos($content, '<?') !== 0)
		{
			return false;
		}

		// Check the file is a class/interface/trait declaration only
		// and so _JEXEC guard is not necessary
		// (some regexp magic instead of the full PHP parser)
		if (preg_match(
			'#^' .
			'<\?php\s+' .
			'(?:namespace [0-9A-Za-z_\\\\]+ ?; ?)?' .
			'(?:use [0-9A-Za-z_\\\\]+( as [0-9A-Za-z_]+) ?; ?)*' .
			'(?:' .
			'(?:(?:abstract )?class|interface|trait) [0-9A-Za-z_]+' .
			'(?: extends [0-9A-Za-z_\\\\]+(?:, ?[0-9A-Za-z_\\\\]+)*)?' .
			'(?: implements [0-9A-Za-z_\\\\]+(?:, ?[0-9A-Za-z_\\\\]+)*)?' .
			' ?' .
			// recursive checking of curly braces and strings
			'(\{((?>[^\{\}\'"]+)|\'(?:(?>[^\'\\\\]+)|\\\\.)*\'|"(?:(?>[^"\\\\]+)|\\\\.)*"|(?-2))*\})' .
			' ?' .
			')+' .
			'$#i',
			$content))
		{
			return true;
		}

		return false;
	}

	/**
	 * Prepare regexp aforehand
	 *
	 * @return void
	 */
	protected function init_jexec()
	{
		$defines = $this->params->get('constants');
		$defines = explode(',', $defines);
		foreach ($defines as $i => $define)
		{
			$defines[$i] = preg_quote(trim($define), '#');
		}

		$this->regex =
			'#^' .
			'<\?php\s+' .
			'defined ?\( ?' .
			'([\'"])(?:' . implode('|', $defines) . ')\1' .
			' ?\) ?(?:or |\|\| ?)(?:die|exit)\b#i';
	}
}
