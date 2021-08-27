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
 * class JedcheckerRulesXMLUpdateServer
 *
 * This class searches all xml files for valid Update Servers
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
		$files = JFolder::files($this->basedir, '\.xml$', true, true);

		// Find XML package file
		$packageFile = $this->checkPackageXML($files);

		if (!$packageFile)
		{
			$XMLFiles = $this->findXMLPaths($files);
		}

		return true;
	}

	/**
	 * Reads a file and searches for package xml file
	 *
	 * @param   string  $files  - The path to the file
	 *
	 * @return boolean True if the package xml file was found, otherwise False.
	 */
	protected function checkPackageXML($files)
	{
		$packageCount = 0;

		foreach ($files as $file)
		{
			$xml = simplexml_load_file($file);

			// Check if this is an XML and an extension manifest
			if ($xml && ($xml->getName() == 'install' || $xml->getName() == 'extension'))
			{
				// Check if extension attribute 'type' is for a package
				if($xml->attributes()->type == 'package')
				{
					$packageCount++;
					$this->find($file);
				}
			}
		}

		// No XML file found for package
		if ($packageCount == 0)
		{
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Reads a file and searches for paths of xml files
	 *
	 * @param   string  $files  - The path to the file
	 *
	 * @return void
	 */
	protected function findXMLPaths($files)
	{
		$XMLFiles = array();
		$componentPaths = array();

		foreach ($files as $file)
		{
			$xml = simplexml_load_file($file);

			// Check if this is an XML and an extension manifest
			if ($xml && ($xml->getName() == 'install' || $xml->getName() == 'extension'))
			{
				$directories = explode('/', substr($file, 0, strrpos( $file, '/')));
				$XMLFiles[] = array(
					'type' => (string) $xml->attributes()->type,
					'filepath' => $file,
					'directoryPath' => substr($file, 0, strrpos( $file, '/')),
					'directory' => trim(end($directories))
				);

				if ($xml->attributes()->type == 'component')
				{
					$componentPaths[] = substr($file, 0, strrpos( $file, '/'));
				}
			}
		}

		foreach ($XMLFiles as $XMLFile)
		{
			// Always check component XML files for update servers
			if ($XMLFile['type'] == 'component')
			{
				$this->find($XMLFile['filepath']);

			} else {
				// If not component, check if XML is nested inside component folder.
				$nested = false;

				foreach ($componentPaths as $component)
				{
					if (strpos($XMLFile['directoryPath'], $component) !== false)
					{
						$nested = true;
					}
				}

				if (!$nested){
					$this->find($XMLFile['filepath']);
				}
			}
		}

		return true;
	}


	/**
	 * Reads a file and searches for the update server
	 *
	 * @param   string  $file  - The path to the file
	 *
	 * @return boolean True if the update server was found, otherwise False.
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
