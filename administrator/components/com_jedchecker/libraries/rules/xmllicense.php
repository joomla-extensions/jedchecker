<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2019 Open Source Matters, Inc. All rights reserved.
 * 			   Copyright (C) 2008 - 2016 compojoom.com . All rights reserved.
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 * 			   eaxs <support@projectfork.net
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');

// Include the rule base class
require_once JPATH_COMPONENT_ADMINISTRATOR . '/models/rule.php';

/**
 * class JedcheckerRulesXMLlicense
 *
 * This class searches all xml manifestes for a valid license.
 *
 * @since  1.0
 */
class JedcheckerRulesXMLlicense extends JEDcheckerRule
{
	/**
	 * The formal ID of this rule. For example: SE1.
	 *
	 * @var    string
	 */
	protected $id = 'PH3';

	/**
	 * The title or caption of this rule.
	 *
	 * @var    string
	 */
	protected $title = 'COM_JEDCHECKER_RULE_PH3';

	/**
	 * The description of this rule.
	 *
	 * @var    string
	 */
	protected $description = 'COM_JEDCHECKER_RULE_PH3_DESC';

	/**
	 * Initiates the search and check
	 *
	 * @return    void
	 */
	public function check()
	{
		// Find all XML files of the extension
		$files = JFolder::files($this->basedir, '.xml$', true, true);

		// Iterate through all the xml files
		foreach ($files as $file)
		{
			// Try to find the license
			$this->find($file);
		}
	}

	/**
	 * Reads a file and searches for the license
	 *
	 * @param   string  $file  - The path to the file
	 *
	 * @return boolean True if the license was found, otherwise False.
	 */
	protected function find($file)
	{
		$xml = simplexml_load_file($file);

		// Failed to parse the xml file.
		// Assume that this is not a extension manifest
		if (!$xml)
		{
			return true;
		}

		// Check if this is an extension manifest
		// 1.5 uses 'install', 1.6 uses 'extension'
		if ($xml->getName() != 'install' && $xml->getName() != 'extension')
		{
			return true;
		}

		// Check if there's a license tag
		if (!isset($xml->license))
		{
			$this->report->addError($file, JText::_('COM_JEDCHECKER_ERROR_XML_LICENSE_NOT_FOUND'));

			return false;
		}

		// Check if the license is gpl
		if (stripos($xml->license, 'gpl') === false
			&& stripos($xml->license, 'general public license') === false)
		{
			$this->report->addCompat($file, JText::_('COM_JEDCHECKER_ERROR_XML_LICENSE_NOT_GPL'));

			return false;
		}

		// All checks passed. Return true
		return true;
	}
}
