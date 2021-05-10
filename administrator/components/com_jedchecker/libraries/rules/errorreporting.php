<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2019 Open Source Matters, Inc. All rights reserved.
 * 			   Copyright (C) 2008 - 2016 mijosoft.com . All rights reserved.
 * @author     Denis Dulici <denis@mijosoft.com>
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');

// Include the rule base class
require_once JPATH_COMPONENT_ADMINISTRATOR . '/models/rule.php';

// Include the helper class
require_once JPATH_COMPONENT_ADMINISTRATOR . '/libraries/helper.php';

/**
 * JedcheckerRulesErrorreporting
 *
 * This class searches all files for the php error_reporting function
 * Developers are discouraged to use this in their joomla extensions
 * as users are able to set the error reporting in the global config
 *
 * @since  1.5
 */
class JedcheckerRulesErrorreporting extends JEDcheckerRule
{
	/**
	 * The formal ID of this rule. For example: SE1.
	 *
	 * @var    string
	 */
	protected $id = 'errorreporting';

	/**
	 * The title or caption of this rule.
	 *
	 * @var    string
	 */
	protected $title = 'COM_JEDCHECKER_RULE_ERRORREPORTING';

	/**
	 * The description of this rule.
	 *
	 * @var    string
	 */
	protected $description = 'COM_JEDCHECKER_RULE_ERRORREPORTING_DESC';

	/**
	 * Regular expression to look for error_reporting calls.
	 *
	 * @var    string
	 */
	protected $errorreportingRegex;

	/**
	 * Initiates the file search and check
	 *
	 * @return    void
	 */
	public function check()
	{
		// Get the functions to look for
		$codes = explode(',', $this->params->get('errorreportings'));

		// Prepare regex
		foreach ($codes as $i => $encoding)
		{
			$codes[$i] = preg_quote(trim($encoding), '/');
		}

		$this->errorreportingRegex = '/' . implode('|', $codes) . '/i';

		// Find all php files of the extension
		$files = JFolder::files($this->basedir, '\.php$', true, true);

		// Iterate through all files
		foreach ($files as $file)
		{
			// Try to find the base64 use in the file
			if ($this->find($file))
			{
				// The error has been added by the find() method
			}
		}
	}

	/**
	 * Reads a file and searches for any function defined in the params
	 * Not a very clever way of doing this, but it should be fine for now
	 *
	 * @param   string  $file  - The path to the file
	 *
	 * @return    boolean            True if the statement was found, otherwise False.
	 */
	protected function find($file)
	{
		$content = file_get_contents($file);

		// Exclude non-code content
		$content = JEDCheckerHelper::cleanPhpCode(
			$content,
			JEDCheckerHelper::CLEAN_HTML | JEDCheckerHelper::CLEAN_COMMENTS | JEDCheckerHelper::CLEAN_STRINGS
		);
		$content = JEDCheckerHelper::splitLines($content);

		$found = false;

		foreach ($content as $i => $line)
		{
			if (preg_match($this->errorreportingRegex, $line))
			{
				$found = true;
				$this->report->addWarning($file, JText::_('COM_JEDCHECKER_ERROR_ERRORREPORTING'), $i + 1, $line);
			}
		}

		return $found;
	}
}
