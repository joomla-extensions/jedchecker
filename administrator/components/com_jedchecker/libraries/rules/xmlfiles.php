<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2021 Open Source Matters, Inc. All rights reserved.
 *             Copyright (C) 2008 - 2016 compojoom.com . All rights reserved.
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 *             eaxs <support@projectfork.net>
 *             Denis Ryabov <denis@mobilejoomla.com>
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');


// Include the rule base class
require_once JPATH_COMPONENT_ADMINISTRATOR . '/models/rule.php';


/**
 * class JedcheckerRulesXMLFiles
 *
 * This class searches all xml manifestes for valid files declarations
 *
 * @since  2.3
 */
class JedcheckerRulesXMLFiles extends JEDcheckerRule
{
	/**
	 * The formal ID of this rule. For example: SE1.
	 *
	 * @var    string
	 */
	protected $id = 'XMLFILES';

	/**
	 * The title or caption of this rule.
	 *
	 * @var    string
	 */
	protected $title = 'COM_JEDCHECKER_XML_FILES';

	/**
	 * The description of this rule.
	 *
	 * @var    string
	 */
	protected $description = 'COM_JEDCHECKER_XML_FILES_DESC';

	/**
	 * List of errors.
	 *
	 * @var    string[]
	 */
	protected $errors;

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
			// Try to check the file
			$this->find($file);
		}
	}

	/**
	 * Reads a file and validate XML manifest
	 *
	 * @param   string  $file  - The path to the file
	 *
	 * @return boolean True if the manifest file was found, otherwise False.
	 */
	protected function find($file)
	{
		$xml = simplexml_load_file($file);

		// Failed to parse the xml file.
		// Assume that this is not a extension manifest
		if (!$xml)
		{
			return false;
		}

		// Check if this is an extension manifest
		if ($xml->getName() !== 'extension')
		{
			return false;
		}

		$this->errors = array();

		// check declared files and folders do exist

		$basedir = dirname($file) . '/';

		// check: files[folder] (filename|folder)*
		// for package: files[folder] (file|folder)*
		if (isset($xml->files))
		{
			$node = $xml->files;
			$dir = $basedir . (isset($node['folder']) ? $node['folder'] . '/' : '');
			$this->checkFiles($node->filename, $dir);
			$this->checkFiles($node->file, $dir); // for packages
			$this->checkFolders($node->folder, $dir);
		}

		// check: media[folder] (filename|folder)*
		if (isset($xml->media))
		{
			$node = $xml->media;
			$dir = $basedir . (isset($node['folder']) ? $node['folder'] . '/' : '');
			$this->checkFiles($node->filename, $dir);
			$this->checkFolders($node->folder, $dir);
		}

		// check files: languages[folder] language*
		if (isset($xml->languages))
		{
			$node = $xml->languages;
			$dir = $basedir . (isset($node['folder']) ? $node['folder'] . '/' : '');
			$this->checkFiles($node->language, $dir);
		}

		// check: administration files[folder] (filename|folder)*
		if (isset($xml->administration->files))
		{
			$node = $xml->administration->files;
			$dir = $basedir . (isset($node['folder']) ? $node['folder'] . '/' : '');
			$this->checkFiles($node->filename, $dir);
			$this->checkFolders($node->folder, $dir);
		}

		// check: administration media[folder] (filename|folder)*
		if (isset($xml->administration->media))
		{
			$node = $xml->administration->media;
			$dir = $basedir . (isset($node['folder']) ? $node['folder'] . '/' : '');
			$this->checkFiles($node->filename, $dir);
			$this->checkFolders($node->folder, $dir);
		}

		// check files: administration languages[folder] language*
		if (isset($xml->administration->languages))
		{
			$node = $xml->administration->languages;
			$dir = $basedir . (isset($node['folder']) ? $node['folder'] . '/' : '');
			$this->checkFiles($node->language, $dir);
		}

		// check file: scriptfile
		if (isset($xml->scriptfile))
		{
			$this->checkFiles($xml->scriptfile, $basedir);
		}

		// check files: install sql file*
		if (isset($xml->install->sql->file))
		{
			$this->checkFiles($xml->install->sql->file, $basedir);
		}

		// check files: uninstall sql file*
		if (isset($xml->uninstall->sql->file))
		{
			$this->checkFiles($xml->uninstall->sql->file, $basedir);
		}

		// check folders: update schemas schemapath*
		if (isset($xml->update->schemas->schemapath))
		{
			$this->checkFolders($xml->update->schemas->schemapath, $basedir);
		}

		if (count($this->errors))
		{
			$this->report->addError($file, implode('<br />', $this->errors));
		}

		// All checks passed. Return true
		return true;
	}

	/**
	 * Check files exist
	 *
	 * @param JXMLElement $files  Files to check
	 * @param string      $dir    Base directory
	 *
	 * @return void
	 */
	protected function checkFiles($files, $dir)
	{
		foreach ($files as $file)
		{
			$filename = $dir . $file;
			if (is_file($filename))
			{
				continue;
			}
			// extra check for unzipped files
			if (preg_match('/^(.*)\.(zip|tar\.gz)$/', $filename, $matches) && is_dir($matches[1]))
			{
				continue;
			}
			$this->errors[] = JText::sprintf('COM_JEDCHECKER_XML_FILES_FILE_NOT_FOUND', (string)$file);
		}
	}

	/**
	 * Check folders exist
	 *
	 * @param JXMLElement $folders  Directories to check
	 * @param string      $dir      Base directory
	 *
	 * @return void
	 */
	protected function checkFolders($folders, $dir)
	{
		foreach ($folders as $folder)
		{
			if (!is_dir($dir . $folder))
			{
				$this->errors[] = JText::sprintf('COM_JEDCHECKER_XML_FILES_FOLDER_NOT_FOUND', (string)$folder);
			}
		}
	}
}
