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
	 * Regexp to match directories to skip
	 *
	 * @var    string
	 */
	protected $regexExcludeFolders;

	/**
	 * List of files related to libraries
	 *
	 * @var    array
	 */
	protected $libFiles;

	/**
	 * Initiates the file search and check
	 *
	 * @return    void
	 */
	public function check()
	{
		$this->initJexec();

		// Find all php files of the extension
		$files = $this->files($this->basedir);

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
		// Load file and strip comments
		$content = php_strip_whitespace($file);

		// Strip BOM (it is checked separately)
		$content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

		// Skip empty files
		if ($content === '' || preg_match('#^<\?php\s+$#', $content))
		{
			return true;
		}

		// Check guards
		if (preg_match($this->regex, $content))
		{
			return true;
		}

		return false;
	}

	/**
	 * Prepare regexps aforehand
	 *
	 * @return void
	 */
	protected function initJexec()
	{
		// Generate regular expression to match JEXEC quard
		$defines = $this->params->get('constants');
		$defines = explode(',', $defines);

		foreach ($defines as $i => $define)
		{
			$defines[$i] = preg_quote(trim($define), '#');
		}

		$this->regex
			= '#^' // at the beginning of the file
			. '<\?php\s+' // there is an opening php tag
			. '(?:declare ?\(strict_types ?= ?1 ?\) ?; ?)?' // optionally followed by declare(strict_types=1) directive
			. '(?:namespace [0-9A-Za-z_\\\\]+ ?; ?)?' // optionally followed by namespace directive
			. '(?:use [0-9A-Za-z_\\\\]+ ?(?:as [0-9A-Za-z_]+ ?)?; ?)*' // optionally followed by use directives
			. 'defined ?\( ?' // followed by defined test
			. '([\'"])(?:' . implode('|', $defines) . ')\1' // of any of given constant
			. ' ?\) ?(?:or |\|\| ?)(?:die|exit)\b' // or exit
			. '#i'; // (case insensitive)

		// Generate regular expression to match excluded directories
		$libfolders = $this->params->get('libfolders');
		$libfolders = explode(',', $libfolders);

		foreach ($libfolders as &$libfolder)
		{
			$libfolder = preg_quote(trim($libfolder), '#');
		}

		// Prepend libFolders with default Joomla's exclude list
		$this->regexExcludeFolders = '#^(?:\.svn|CVS|\.DS_Store|__MACOSX|' . implode('|', $libfolders) . ')$#';

		// Generate list of libraries fingerprint files
		$libFiles = $this->params->get('libfiles');
		$this->libFiles = array_map('trim', explode(',', $libFiles));
	}

	/**
	 * Collect php files to check (excluding external library directories)
	 *
	 * @param   string $path The path of the folder to read.
	 *
	 * @return array
	 * @since 3.0
	 */
	protected function files($path)
	{
		$arr = array();

		// Read the source directory
		if ($handle = @opendir($path))
		{
			while (($file = readdir($handle)) !== false)
			{
				// Skip excluded directories
				if ($file !== '.' && $file !== '..' && !preg_match($this->regexExcludeFolders, $file))
				{
					$fullpath = $path . '/' . $file;

					if (is_dir($fullpath))
					{
						// Detect and skip external library directories
						foreach ($this->libFiles as $libFile)
						{
							if (is_file($fullpath . '/' . $libFile))
							{
								// Skip processing of this directory
								continue 2;
							}
						}

						$arr = array_merge($arr, $this->files($fullpath));
					}
					elseif (preg_match('/\.php$/', $file))
					{
						$arr[] = $fullpath;
					}
				}
			}

			closedir($handle);
		}

		return $arr;
	}
}
