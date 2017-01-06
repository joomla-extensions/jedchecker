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
require_once JPATH_COMPONENT_ADMINISTRATOR . '/models/rule.php';

/**
 * class JedcheckerRulesXMLlicense
 *
 * This class searches all xml manifestes for a valid license.
 *
 * @since  1.0
 */
class JedcheckerRulesXMLUpdateServer extends JEDcheckerRule
{
	/**
	 * The formal ID of this rule. For example: SE1.
	 *
	 * @var    string
	 */
	protected $id = 'US1';

	/**
	 * The title or caption of this rule.
	 *
	 * @var    string
	 */
	protected $title = 'COM_JEDCHECKER_RULE_US1';

	/**
	 * The description of this rule.
	 *
	 * @var    string
	 */
	protected $description = 'COM_JEDCHECKER_RULE_US1_DESC';

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
		$xml = JFactory::getXml($file);

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

		// Check if there is an updateservers tag
		if (!isset($xml->updateservers))
		{
			$this->report->addError($file, JText::_('COM_JEDCHECKER_ERROR_XML_UPDATE_SERVER_NOT_FOUND'));

			return false;
		}
		
		// Check if server tag(s) exist
		if (!isset($xml->updateservers->server))
		{
			$this->report->addError($file, JText::_('COM_JEDCHECKER_ERROR_XML_UPDATE_SERVER_NOT_FOUND'));

			return false;
			
		} 
		
		// Check if server tag(s) contain valid links
		foreach ($xml->updateservers->server as $server)
		{
			if (stripos($server, 'http') === false)
			{
				$this->report->addError($file, JText::_('COM_JEDCHECKER_ERROR_XML_UPDATE_SERVER_LINK_NOT_FOUND'));

				return false;
				
			} else {
				$this->report->addInfo($file, JText::sprintf('COM_JEDCHECKER_INFO_XML_UPDATE_SERVER_LINK', (string) $server));
			}
		}


		// All checks passed. Return true
		return true;
	}
}
