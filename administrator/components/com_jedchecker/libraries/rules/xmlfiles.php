<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2021 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');


// Include the rule base class
require_once JPATH_COMPONENT_ADMINISTRATOR . '/models/rule.php';

// Include the helper class
require_once JPATH_COMPONENT_ADMINISTRATOR . '/libraries/helper.php';


/**
 * class JedcheckerRulesXMLFiles
 *
 * This class searches all xml manifests for valid files declarations
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
	 * List of warnings.
	 *
	 * @var    string[]
	 */
	protected $warnings;

	/**
	 * Manifest's directory
	 *
	 * @var    string
	 */
	protected $basedir;

	/**
	 * Initiates the search and check
	 *
	 * @return    void
	 */
	public function check()
	{
		// Find all XML files of the extension
		$files = JEDCheckerHelper::findManifests($this->basedir);

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

		$this->errors = array();
		$this->warnings = array();

		// Check declared files and folders do exist

		$this->basedir = dirname($file) . '/';

		$sitedir = '';

		// Check: files[folder] (filename|folder)*
		// ( for package: files[folder] (file|folder)* )
		if (isset($xml->files))
		{
			$node = $xml->files;

			// Get path to site files from "folder" attribute
			$sitedir = $this->getSourceFolder($node);

			$this->checkFiles($node->filename, $sitedir);
			$this->checkFiles($node->file, $sitedir);
			$this->checkFolders($node->folder, $sitedir);
		}

		// Check: media[folder] (filename|file|folder)*
		if (isset($xml->media))
		{
			$node = $xml->media;
			$dir = $this->getSourceFolder($node);

			$this->checkFiles($node->filename, $dir);
			$this->checkFiles($node->file, $dir);
			$this->checkFolders($node->folder, $dir);
		}

		// Check: fonts[folder] (filename|file|folder)*
		if (isset($xml->fonts))
		{
			$node = $xml->fonts;
			$dir = $this->getSourceFolder($node);

			$this->checkFiles($node->filename, $dir);
			$this->checkFiles($node->file, $dir);
			$this->checkFolders($node->folder, $dir);
		}

		// Check files: languages[folder] language*
		if (isset($xml->languages))
		{
			$node = $xml->languages;
			$dir = $this->getSourceFolder($node);

			$this->checkFiles($node->language, $dir);
		}

		$admindir = '';

		// Check: administration files[folder] (filename|file|folder)*
		if (isset($xml->administration->files))
		{
			$node = $xml->administration->files;

			// Get path to admin files from "folder" attribute
			$admindir = $this->getSourceFolder($node);

			$this->checkFiles($node->filename, $admindir);
			$this->checkFiles($node->file, $admindir);
			$this->checkFolders($node->folder, $admindir);
		}

		// Check: administration media[folder] (filename|file|folder)*
		if (isset($xml->administration->media))
		{
			$node = $xml->administration->media;
			$dir = $this->getSourceFolder($node);

			$this->checkFiles($node->filename, $dir);
			$this->checkFiles($node->file, $dir);
			$this->checkFolders($node->folder, $dir);
		}

		// Check files: administration languages[folder] language*
		if (isset($xml->administration->languages))
		{
			$node = $xml->administration->languages;
			$dir = $this->getSourceFolder($node);

			$this->checkFiles($node->language, $dir);
		}

		// For type="file" extensions:
		// Check files: fileset files[folder] (filename|file|folder)*
		if (isset($xml->fileset->files))
		{
			$node = $xml->fileset->files;
			$dir = $this->getSourceFolder($node);

			$this->checkFiles($node->filename, $dir);
			$this->checkFiles($node->file, $dir);
			$this->checkFolders($node->folder, $dir);
		}

		// Check: api files[folder] (filename|file|folder)*
		if (isset($xml->api->files))
		{
			$node = $xml->api->files;
			$dir = $this->getSourceFolder($node);

			$this->checkFiles($node->filename, $dir);
			$this->checkFiles($node->file, $dir);
			$this->checkFolders($node->folder, $dir);
		}

		// Check file: scriptfile
		if (isset($xml->scriptfile))
		{
			$this->checkFiles($xml->scriptfile);
		}

		// Check files: install sql file*
		if (isset($xml->install->sql->file))
		{
			$this->checkFiles($xml->install->sql->file, $admindir);
		}

		// Check files: uninstall sql file*
		if (isset($xml->uninstall->sql->file))
		{
			$this->checkFiles($xml->uninstall->sql->file, $admindir);
		}

		// Check folders: update schemas schemapath*
		if (isset($xml->update->schemas->schemapath))
		{
			$this->checkFolders($xml->update->schemas->schemapath, $admindir);
		}

		// Check: config [add...path] directories exist
		if (isset($xml->config))
		{
			$attributes = array('addfieldpath', 'addformpath', 'addrulepath');

			$element = JEDCheckerHelper::getElementName($xml);
			$extensionPath = false;

			$type = (string) $xml['type'];

			switch ($type)
			{
				case 'module':
					$extensionPath = 'modules/' . $element . '/';
					break;

				case 'plugin':
					$group = (string) $xml['group'];
					$extensionPath = 'plugins/' . $group . '/' . $element . '/';
					break;

				case 'template':
					$extensionPath = 'templates/' . $element . '/';
			}

			if ($extensionPath !== false)
			{
				foreach ($attributes as $attribute)
				{
					foreach ($xml->config->xpath('//*[@' . $attribute . ']') as $node)
					{
						$attrPath = (string) $node[$attribute];
						$folder = ltrim($attrPath, '/');

						// Convert absolute path to relative (if matches extension path)
						if (strpos($folder, $extensionPath) === 0)
						{
							$folder = $this->basedir . $sitedir . substr($folder, strlen($extensionPath));

							if (!is_dir($folder))
							{
								$this->errors[] = JText::sprintf('COM_JEDCHECKER_XML_FILES_FOLDER_NOT_FOUND', $attrPath);
							}
						}
					}
				}
			}
		}

		// Check /namespace[path] directory exists (Joomla!4)
		if (isset($xml->namespace['path']))
		{
			$folder = (string) $xml->namespace['path'];

			if (!is_dir($this->basedir . $admindir . $folder) && !is_dir($this->basedir . $sitedir . $folder))
			{
				$this->errors[] = JText::sprintf('COM_JEDCHECKER_XML_FILES_FOLDER_NOT_FOUND', $folder);
			}
		}

		if (count($this->errors))
		{
			$this->report->addError($file, implode('<br />', $this->errors));
		}

		if (count($this->warnings))
		{
			$this->report->addWarning($file, implode('<br />', $this->warnings));
		}

		// All checks passed. Return true
		return true;
	}

	/**
	 * Get source folder for a node
	 *
	 * @param   SimpleXMLElement  $node  The node to check for "folder" attribute
	 *
	 * @return  string
	 */
	protected function getSourceFolder($node)
	{
		if (!isset($node['folder']))
		{
			return '';
		}

		$folder = (string) $node['folder'];

		if (is_dir($this->basedir . $folder))
		{
			return $folder . '/';
		}

		$this->warnings[] = JText::sprintf('COM_JEDCHECKER_XML_FILES_FOLDER_NOT_FOUND', $folder);

		return '';
	}

	/**
	 * Check files exist
	 *
	 * @param   SimpleXMLElement  $files  Files to check
	 * @param   string            $dir    Base directory
	 *
	 * @return  void
	 */
	protected function checkFiles($files, $dir = '')
	{
		foreach ($files as $file)
		{
			$filename = $this->basedir . $dir . $file;

			if (is_file($filename))
			{
				continue;
			}

			// Extra check for unzipped files
			if (preg_match('/^(.*)\.(zip|tgz|tar\.gz)$/', $filename, $matches) && is_dir($matches[1]))
			{
				continue;
			}

			$this->errors[] = JText::sprintf('COM_JEDCHECKER_XML_FILES_FILE_NOT_FOUND', $dir . $file);
		}
	}

	/**
	 * Check folders exist
	 *
	 * @param   SimpleXMLElement  $folders  Directories to check
	 * @param   string            $dir      Base directory
	 *
	 * @return  void
	 */
	protected function checkFolders($folders, $dir = '')
	{
		foreach ($folders as $folder)
		{
			if (!is_dir($this->basedir . $dir . $folder))
			{
				$this->errors[] = JText::sprintf('COM_JEDCHECKER_XML_FILES_FOLDER_NOT_FOUND', $dir . $folder);
			}
		}
	}
}
