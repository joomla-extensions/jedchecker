<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2019 Open Source Matters, Inc. All rights reserved.
 * 			   Copyright (C) 2008 - 2016 compojoom.com . All rights reserved.
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
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
	 * Regular expression to match GPL licenses.
	 *
	 * @var    string
	 */
	protected $regex_gpl_licenses;

	/**
	 * Regular expression to match GPL-compatible licenses.
	 *
	 * @var    string
	 */
	protected $regex_compat_licenses;

	/**
	 * Initiates the file search and check
	 *
	 * @return    void
	 */
	public function check()
	{
		// Prepare regexp
		$this->init();

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
	 * Initialization (prepare regular expressions)
	 */
	protected function init()
	{
		$gpl_licenses = (array) file(__DIR__ . '/gpl_gnu.txt');
		$this->regex_gpl_licenses = $this->generate_regexp($gpl_licenses);

		$compat_licenses = (array) file(__DIR__ . '/gpl_compat.txt');

		$extra_licenses = $this->params->get('constants');
		$extra_licenses = explode(',', $extra_licenses);

		$compat_licenses = array_merge($compat_licenses, $extra_licenses);

		$this->regex_compat_licenses = $this->generate_regexp($compat_licenses);
	}

	/**
	 * Generate regular expression to match the given list of license names
	 * @param $lines
	 * @return string
	 */
	protected function generate_regexp($lines)
	{
		$titles = array();
		$ids = array();

		foreach ($lines as $line)
		{
			$line = trim($line);
			if ($line === '' || $line[0] === '#')
			{
				// skip empty and commented lines
				continue;
			}

			$title = $line;
			if (substr($line, -1, 1) === ')')
			{
				// extract identifier
				$pos = strrpos($line, '(');
				if ($pos !== false)
				{
					$title = trim(substr($line, 0, $pos));

					$id = trim(substr($line, $pos + 1, -1));

					if ($id !== '')
					{
						$id = preg_quote($id, '#');
						$ids[$id] = 1;
					}
				}
			}

			if ($title !== '')
			{
				$title = preg_quote($title, '#');

				// expand vN.N to different version formats
				$title = preg_replace('/(?<=\S)\s+v(?=\d)/', ',?\s+(?:v\.?\s*|version\s+)?', $title);

				$title = preg_replace('/\s+/', '\s+', $title);

				$titles[$title] = 1;
			}
		}

		if (count($titles) === 0)
		{
			return null;
		}

		$titles = implode('|', array_keys($titles));

		if (count($ids))
		{
			$ids = implode('|', array_keys($ids));
			$titles .=
				'|\blicense\b.+?(?:' . $ids . ')' .
				'|\b(?:' . $ids . ')\s+license\b';
		}

		return '#^.*?(?:' . $titles . ').*?$#im';
	}

	/**
	 * Reads a file and searches for its license
	 *
	 * @param   string  $file  - The path to the file
	 *
	 * @return boolean True if the statement was found, otherwise False.
	 */
	protected function find($file)
	{
		// check the file is empty (i.e. comments-only)
		$content = php_strip_whitespace($file);
		if (preg_match('#^<\?php\s+$#', $content))
		{
			return true;
		}

		$content = file_get_contents($file);

		if (preg_match($this->regex_gpl_licenses, $content, $match, PREG_OFFSET_CAPTURE))
		{
			$line_no = substr_count($content, "\n", 0, $match[0][1]) + 1;
			$this->report->addInfo(
				$file,
				JText::_('COM_JEDCHECKER_PH1_LICENSE_FOUND') . ':' . '<strong>' . $match[0][0] . '</strong>',
				$line_no
			);
			return true;
		}

		if (preg_match($this->regex_compat_licenses, $content, $match, PREG_OFFSET_CAPTURE))
		{
			$line_no = substr_count($content, "\n", 0, $match[0][1]) + 1;
			$this->report->addInfo(
				$file,
				JText::_('COM_JEDCHECKER_GPL_COMPATIBLE_LICENSE_WAS_FOUND') . ':' . '<strong>' . $match[0][0] . '</strong>',
				$line_no
			);
			return true;
		}

		return false;
	}
}
