<?php
/**
 * @author     eaxs <support@projectfork.net>
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 * @date       07/06/2012
 * @copyright  Copyright (C) 2008 - 2012 compojoom.com . All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');


// Include the rule base class
require_once(JPATH_COMPONENT_ADMINISTRATOR . '/models/rule.php');


/**
 * class JedcheckerRulesGpl
 *
 * This class searches all files for the _JEXEC check
 * which prevents direct file access.
 *
 * @since  1.0
 */
class JedcheckerRulesGpl extends JEDcheckerRule
{
	/**
	 * The formal ID of this rule. For example: SE1.
	 *
	 * @var    string
	 */
	protected $id = 'PH1';

	/**
	 * The title or caption of this rule.
	 *
	 * @var    string
	 */
	protected $title = 'COM_JEDCHECKER_RULE_PH1';

	/**
	 * The description of this rule.
	 *
	 * @var    string
	 */
	protected $description = 'COM_JEDCHECKER_RULE_PH1_DESC';

	/**
	 * Initiates the file search and check
	 *
	 * @return    void
	 */
	public function check()
	{
		// Find all php files of the extension
		$files = JFolder::files($this->basedir, '.php$', true, true);

		// Iterate through all files
		foreach ($files as $file)
		{
			// Try to find the _JEXEC check in the file
			if (!$this->find($file))
			{
				// Add as error to the report if it was not found
				$this->report->addError($file, JText::_('COM_JEDCHECKER_ERROR_GPL_NOT_FOUND'));
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
		$content = (array) file($file);

		// Get the constants to look for
		$licenses = $this->params->get('constants');
		$licenses = explode(',', $licenses);

		$hascode = 0;

		foreach ($content AS $key => $line)
		{
			$tline = trim($line);

			if ($tline == '' || $tline == '<?php' || $tline == '?>')
			{
				continue;
			}

			if ($tline['0'] != '/' && $tline['0'] != '*')
			{
				$hascode = 1;
			}

			// Search for GPL license
			$gpl = stripos($line, 'GPL');
			$gnu = stripos($line, 'GNU');
			$gpl_long = stripos($line, 'general public license');

			if ($gpl || $gnu || $gpl_long)
			{
				$this->report->addInfo(
					$file,
					JText::_('COM_JEDCHECKER_PH1_LICENSE_FOUND') . ':' . '<strong>' . $line . '</strong>',
					$key
				);

				return true;
			}

			// Search for the constant name
			foreach ($licenses AS $license)
			{
				$license = trim($license);

				// Search for the license
				$found = strpos($line, $license);

				// Skip the line if the license is not found
				if ($found === false)
				{
					continue;
				}
				else
				{
					$this->report->addInfo(
						$file,
						JText::_('COM_JEDCHECKER_GPL_COMPATIBLE_LICENSE_WAS_FOUND') . ':' . '<strong>' . $line . '</strong>',
						$key
					);

					return true;
				}
			}
		}

		unset($content);

		return $hascode ? false : true;
	}
}
