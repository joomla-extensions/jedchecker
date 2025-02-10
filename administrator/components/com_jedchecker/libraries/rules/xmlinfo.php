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

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;

// Include the rule base class
require_once JPATH_COMPONENT_ADMINISTRATOR . '/models/rule.php';

// Include the helper class
require_once JPATH_COMPONENT_ADMINISTRATOR . '/libraries/helper.php';


/**
 * class JedcheckerRulesXMLinfo
 *
 * This class searches all xml manifests for specific tags
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
	 * The ordering value to sort rules in the menu.
	 *
	 * @var    integer
	 */
	public static $ordering = 0;

	/**
	 * List of JED extension types
	 *
	 * @var string[]
	 */
	protected $jedTypes = array(
		'component', 'module', 'package', 'plugin', 'library'
	);

	/**
	 * Mapping of the plugin title prefix to the plugin group
	 *
	 * @var    string[]
	 */
	protected $pluginsGroupMap = array(
		'button' => 'editors-xtd',
		'editor' => 'editors',
		'smartsearch' => 'finder',
		'twofactorauthentication' => 'twofactorauth'
	);

	/**
	 * Initiates the search and check
	 *
	 * @return    void
	 */
	public function check()
	{
		// Find all XML files of the extension
		$files = JEDCheckerHelper::findManifests($this->basedir);

		$manifestFound = false;

		if (count($files))
		{
			$topLevelDepth = substr_count($files[0], '/');

			// Iterate through all the xml files
			foreach ($files as $file)
			{
				$isTopLevel = substr_count($file, '/') === $topLevelDepth;

				// Try to find the license
				if ($this->find($file, $isTopLevel))
				{
					$manifestFound = true;
				}
			}
		}

		if (!$manifestFound)
		{
			$this->report->addError('', Text::_('COM_JEDCHECKER_INFO_XML_NO_MANIFEST'));
		}
	}

	/**
	 * Reads a file and searches for the license
	 *
	 * @param   string  $file        - The path to the file
	 * @param   bool    $isTopLevel  - Is the file located in the top-level manifests directory?
	 *
	 * @return boolean True if the manifest file was found, otherwise False.
	 */
	protected function find($file, $isTopLevel)
	{
		$xml = simplexml_load_file($file);

		// Failed to parse the xml file.
		// Assume that this is not a extension manifest
		if (!$xml)
		{
			return false;
		}

		// Check if this is an extension manifest
		// 1.5 uses 'install', 1.6+ uses 'extension'
		if ($xml->getName() === 'install')
		{
			$this->report->addWarning($file, Text::sprintf('COM_JEDCHECKER_INFO_XML_MANIFEST_OUTDATED'));
		}

		if ($xml->getName() !== 'extension')
		{
			return false;
		}

		// Get extension type
		$type = (string) $xml['type'];

		// Load the language of the extension (if any)
		if (!$this->loadExtensionLanguage($xml, dirname($file))) {
			$lang_file = JEDCheckerHelper::getElementName($xml) . '.sys.ini';

			if ($type === 'plugin' && isset($xml['group']) && strpos($lang_file, 'plg_') !== 0)
			{
				$lang_file = 'plg_' . $xml['group'] . '_' . $lang_file;
			}

			$this->report->addNotice($file, Text::sprintf('COM_JEDCHECKER_INFO_XML_NO_LANGUAGE_FILE_FOUND', $lang_file, 'en-GB'));
		}

		// Get the real extension's name now that the language has been loaded
		$lang = Factory::getLanguage();
		$extensionName = $lang->_((string) $xml->name);

		$info[] = Text::sprintf('COM_JEDCHECKER_INFO_XML_NAME_XML', $extensionName);
		$info[] = Text::sprintf('COM_JEDCHECKER_INFO_XML_VERSION_XML', (string) $xml->version);
		$info[] = Text::sprintf('COM_JEDCHECKER_INFO_XML_CREATIONDATE_XML', (string) $xml->creationDate);

		$this->report->addInfo($file, implode('<br />', $info));

		if ($isTopLevel)
		{
			// JED allows components, modules, plugins, and packages (as a container) only
			if (!in_array($type, $this->jedTypes, true))
			{
				$this->report->addError($file, Text::sprintf('COM_JEDCHECKER_MANIFEST_TYPE_NOT_ACCEPTED', $type));
			}

			// NM3 - Listing name contains “module” or “plugin”
			// (and other reserved words)
			if (preg_match('/\b(?:module|plugin|component|template|extension|free)\b/i', $extensionName, $match))
			{
				$this->report->addIssue(JEDcheckerReport::LEVEL_ERROR, 'NM3', $file,
				                        Text::sprintf('COM_JEDCHECKER_INFO_XML_NAME_RESERVED_KEYWORDS', $extensionName, strtolower($match[0])));
			}

			// Extension name shouldn't start with extension type prefix
			if (preg_match('/^\s*(?:mod|com|plg|tpl|pkg)_/i', $extensionName))
			{
				$this->report->addError($file, Text::sprintf('COM_JEDCHECKER_INFO_XML_NAME_PREFIXED', $extensionName));
			}

			// NM5 - Version in name/title
			if (preg_match('/(?:\bversion\b|\d\.\d)/i', $extensionName))
			{
				$this->report->addIssue(JEDcheckerReport::LEVEL_ERROR, 'NM5', $file,
				                        Text::sprintf('COM_JEDCHECKER_INFO_XML_NAME_VERSION', $extensionName));
			}

			// Check for "Joomla" in the name
			if (stripos($extensionName, 'joomla') === 0)
			{
				// An extension name can't start with the word "Joomla"
				$this->report->addIssue(JEDcheckerReport::LEVEL_ERROR, 'TM2', $file,
				                        Text::sprintf('COM_JEDCHECKER_INFO_XML_NAME_JOOMLA', $extensionName));
			}
			else
			{
				$cleanName = preg_replace('/\s+for\s+Joomla!?$/', '', $extensionName);

				if (stripos($cleanName, 'joomla') !== false)
				{
					// Extensions that use "Joomla" or a derivative of Joomla in the extension name need to be licensed by OSM
					$this->report->addIssue(JEDcheckerReport::LEVEL_WARNING, 'TM2', $file,
					                        Text::sprintf('COM_JEDCHECKER_INFO_XML_NAME_JOOMLA_DERIVATIVE', $extensionName, 'https://tm.joomla.org/approved-domains.html'));
				}
			}

			// Check extension name consists of ASCII characters only
			if (preg_match('/[^\x20-\x7E]/', $extensionName))
			{
				$this->report->addError($file, Text::sprintf('COM_JEDCHECKER_INFO_XML_NAME_NON_ASCII', $extensionName));
			}

			// Extension name shouldn't be too long
			$nameLen = strlen($extensionName);

			if ($nameLen > 80)
			{
				$this->report->addError($file, Text::sprintf('COM_JEDCHECKER_INFO_XML_NAME_TOO_LONG', $extensionName));
			}
			elseif ($nameLen > 40)
			{
				$this->report->addWarning($file, Text::sprintf('COM_JEDCHECKER_INFO_XML_NAME_TOO_LONG', $extensionName));
			}
		}

		// Validate URLs
		$this->validateDomain($file, (string) $xml->authorUrl);

		if ($type === 'package' && (string) $xml->packagerurl !== (string) $xml->authorUrl)
		{
			$this->validateDomain($file, (string) $xml->packagerurl);
		}

		if ($type === 'component' && isset($xml->administration->menu))
		{
			$menuName = $lang->_((string) $xml->administration->menu);
			// Do name the Component's admin menu the same as the extension name
			if ($extensionName !== $menuName)
			{
				$this->report->addWarning($file, Text::sprintf('COM_JEDCHECKER_INFO_XML_NAME_ADMIN_MENU', $menuName, $extensionName));
			}
		}

		if ($isTopLevel && $type === 'plugin')
		{
			// The name of your plugin must comply with the JED naming conventions - plugins in the form “{Type} - {Extension Name}”.
			$parts = explode(' - ', $extensionName, 2);
			$extensionNameGroup = isset($parts[1]) ? strtolower(preg_replace('/\s/', '', $parts[0])) : false;
			$group = (string) $xml['group'];

			if ($extensionNameGroup !== $group && $extensionNameGroup !== str_replace('-', '', $group)
				&& !(isset($this->pluginsGroupMap[$extensionNameGroup]) && $this->pluginsGroupMap[$extensionNameGroup] === $group)
			)
			{
				$this->report->addWarning($file, Text::sprintf('COM_JEDCHECKER_INFO_XML_NAME_PLUGIN_FORMAT', $extensionName));
			}
		}

		// All checks passed. Return true
		return true;
	}

	/**
	 * Locate and load extension's .sys.ini translation file
	 *
	 * @param   SimpleXMLElement  $xml      Extension's XML manifest
	 * @param   string            $rootDir  The basepath
	 * @param   string            $langTag  The language to load
	 *
	 * @return  bool    True if language file found, and false otherwise
	 */
	protected function loadExtensionLanguage($xml, $rootDir, $langTag = 'en-GB')
	{
		// Get extension's element name (simulates work of Joomla's installer)
		$extension = JEDCheckerHelper::getElementName($xml);

		$type = (string) $xml['type'];

		// Plugin's element name starts with plg_
		if ($type === 'plugin' && isset($xml['group']) && strpos($extension, 'plg_') !== 0)
		{
			$extension = 'plg_' . $xml['group'] . '_' . $extension;
		}

		// Load the language of the extension (if any)
		$lang = Factory::getLanguage();

		// Populate list of directories to look for
		$lookupLangDirs = array();

		if (isset($xml->administration->files['folder']))
		{
			$lookupLangDirs[] = trim($xml->administration->files['folder'], '/') . '/language/' . $langTag;
		}

		if (isset($xml->files['folder']))
		{
			$lookupLangDirs[] = trim($xml->files['folder'], '/') . '/language/' . $langTag;
		}

		$lookupLangDirs[] = 'language/' . $langTag;

		if (isset($xml->administration->languages))
		{
			$folder = trim($xml->administration->languages['folder'], '/');

			foreach ($xml->administration->languages->language as $language)
			{
				if (trim($language['tag']) === $langTag)
				{
					$lookupLangDirs[] = trim($folder . '/' . dirname($language), '/');
				}
			}
		}

		if (isset($xml->languages))
		{
			$folder = trim((string)$xml->languages['folder'], '/');

			foreach ($xml->languages->language as $language)
			{
				if (trim($language['tag']) === $langTag)
				{
					$lookupLangDirs[] = trim($folder . '/' . dirname($language), '/');
				}
			}
		}

		$lookupLangDirs[] = '';

		$lookupLangDirs = array_unique($lookupLangDirs);

		$lookupLangFiles = array(
			$langTag. '.' . $extension . '.sys.ini', // classical filename
			$extension . '.sys.ini', // modern filename
		);

		// Looking for language file in specified directories
		foreach ($lookupLangDirs as $dir)
		{
			foreach ($lookupLangFiles as $file)
			{
				$langSysFile = $rootDir . '/' . ($dir === '' ? '' : $dir . '/') . $file;

				if (is_file($langSysFile))
				{
					$loadLanguage = new ReflectionMethod($lang, 'loadLanguage');
					$loadLanguage->setAccessible(true);
					$loadLanguage->invoke($lang, $langSysFile, $extension);
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check domain name contains "Joomla"/derivative
	 *
	 * @param   string $file Current file name
	 * @param   string $url  URL to validate
	 *
	 * @return  void
	 */
	protected function validateDomain($file, $url)
	{
		$domain = (strpos($url, '//') === false) ? $url : parse_url(trim($url), PHP_URL_HOST);

		if (stripos($domain, 'joomla') !== false)
		{
			// Extensions that use "Joomla" or a derivative of Joomla in the domain name need to be licensed by OSM
			$this->report->addIssue(JEDcheckerReport::LEVEL_ERROR, 'TM1', $file,
			                        Text::sprintf('COM_JEDCHECKER_INFO_XML_URL_JOOMLA_DERIVATIVE', $url, 'https://tm.joomla.org/approved-domains.html'));
		}
	}
}
