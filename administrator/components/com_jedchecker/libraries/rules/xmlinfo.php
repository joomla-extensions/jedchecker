<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2019 Open Source Matters, Inc. All rights reserved.
 * 			   Copyright (C) 2008 - 2016 compojoom.com . All rights reserved.
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 *             eaxs <support@projectfork.net>
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');


// Include the rule base class
require_once JPATH_COMPONENT_ADMINISTRATOR . '/models/rule.php';


/**
 * class JedcheckerRulesXMLinfo
 *
 * This class searches all xml manifestes for specific tags
 *
 * @since  1.0
 */
class JedcheckerRulesXMLinfo extends JEDcheckerRule
{
	/**
	 * The formal ID of this rule. For example: SE1.
	 *
	 * @var    string
	 */
	protected $id = 'INFO_XML';

	/**
	 * The title or caption of this rule.
	 *
	 * @var    string
	 */
	protected $title = 'COM_JEDCHECKER_INFO_XML';

	/**
	 * The description of this rule.
	 *
	 * @var    string
	 */
	protected $description = 'COM_JEDCHECKER_INFO_XML_DESC';

	/**
	 * Initiates the search and check
	 *
	 * @return    void
	 */
	public function check()
	{
		// Find all XML files of the extension
		$files = JFolder::files($this->basedir, '\.xml$', true, true);

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
		$xml = JFactory::getXml($file);

		// Get all the info about the file
		$folder_info = pathinfo($file);

		// Get the folder path
		$folder_path = $folder_info['dirname'];

		// Get the folder name
		$folder_name = $folder_info['dirname'];
		$folder_name_exploded = explode(DIRECTORY_SEPARATOR,$folder_name);
		if ( is_array($folder_name_exploded) ) {
			$folder_name = end($folder_name_exploded);
		}

		// Load the language of the extension (if any)
		$lang = JFactory::getLanguage();
		$lang->load($folder_name,$folder_path);

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

		// Get the real extension's name now that the language has been loaded
		(string) $extension_name = $lang->_($xml->name);

		$info[] = JText::sprintf('COM_JEDCHECKER_INFO_XML_NAME_XML', $extension_name);
		$info[] = JText::sprintf('COM_JEDCHECKER_INFO_XML_VERSION_XML', (string) $xml->version);
		$info[] = JText::sprintf('COM_JEDCHECKER_INFO_XML_CREATIONDATE_XML', (string) $xml->creationDate);
		$this->report->addInfo($file, implode('<br />', $info));

		// All checks passed. Return true
		return true;
	}
}
