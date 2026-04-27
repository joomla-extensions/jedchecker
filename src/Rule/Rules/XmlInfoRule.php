<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2025 Open Source Matters, Inc. All rights reserved.
 *             Copyright (C) 2008 - 2016 compojoom.com . All rights reserved.
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 *             eaxs <support@projectfork.net>
 *
 * @license    GNU General Public Licence version 2 or later; see LICENCE.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Rule\Rules;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\Component\Jedchecker\Administrator\Helper\CheckerHelper;
use Joomla\Component\Jedchecker\Administrator\Report\Report;
use Joomla\Component\Jedchecker\Administrator\Rule\AbstractRule;

/**
 * XmlInfoRule searches all XML manifests for specific tags.
 *
 * @since  3.0
 */
class XmlInfoRule extends AbstractRule
{
	protected string $id          = 'INFO_XML';
	protected string $title       = 'COM_JEDCHECKER_INFO_XML';
	protected string $description = 'COM_JEDCHECKER_INFO_XML_DESC';

	public static int $ordering = 0;

	protected array $jedTypes = [
		'component', 'module', 'package', 'plugin', 'library',
	];

	protected array $pluginsGroupMap = [
		'button'                 => 'editors-xtd',
		'editor'                 => 'editors',
		'smartsearch'            => 'finder',
		'twofactorauthentication' => 'twofactorauth',
	];

	public function check(): void
	{
		$this->report->setDefaultSubtype($this->id);

		$files = CheckerHelper::findManifests($this->basedir);

		$manifestFound = false;

		if (count($files))
		{
			$topLevelDepth = substr_count($files[0], '/');

			foreach ($files as $file)
			{
				$isTopLevel = substr_count($file, '/') === $topLevelDepth;

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

	protected function find(string $file, bool $isTopLevel): bool
	{
		$xml = simplexml_load_file($file);

		if (!$xml)
		{
			return false;
		}

		if ($xml->getName() === 'install')
		{
			$this->report->addWarning($file, Text::sprintf('COM_JEDCHECKER_INFO_XML_MANIFEST_OUTDATED'));
		}

		if ($xml->getName() !== 'extension')
		{
			return false;
		}

		$type = (string) $xml['type'];

		if (!$this->loadExtensionLanguage($xml, dirname($file)))
		{
			$lang_file = CheckerHelper::getElementName($xml) . '.sys.ini';

			if ($type === 'plugin' && isset($xml['group']) && strpos($lang_file, 'plg_') !== 0)
			{
				$lang_file = 'plg_' . $xml['group'] . '_' . $lang_file;
			}

			$this->report->addNotice($file, Text::sprintf('COM_JEDCHECKER_INFO_XML_NO_LANGUAGE_FILE_FOUND', $lang_file, 'en-GB'));
		}

		$lang          = Factory::getApplication()->getLanguage();
		$extensionName = $lang->_((string) $xml->name);

		$info   = [];
		$info[] = Text::sprintf('COM_JEDCHECKER_INFO_XML_NAME_XML', $extensionName);
		$info[] = Text::sprintf('COM_JEDCHECKER_INFO_XML_VERSION_XML', (string) $xml->version);
		$info[] = Text::sprintf('COM_JEDCHECKER_INFO_XML_CREATIONDATE_XML', (string) $xml->creationDate);

		$this->report->addInfo($file, implode('<br />', $info));

		if ($isTopLevel)
		{
			if (!in_array($type, $this->jedTypes, true))
			{
				$this->report->addError($file, Text::sprintf('COM_JEDCHECKER_MANIFEST_TYPE_NOT_ACCEPTED', $type));
			}

			if (preg_match('/\b(?:module|plugin|component|template|extension|free)\b/i', $extensionName, $match))
			{
				$this->report->addIssue(Report::LEVEL_ERROR, 'NM3', $file,
					Text::sprintf('COM_JEDCHECKER_INFO_XML_NAME_RESERVED_KEYWORDS', $extensionName, strtolower($match[0])));
			}

			if (preg_match('/^\s*(?:mod|com|plg|tpl|pkg)_/i', $extensionName))
			{
				$this->report->addError($file, Text::sprintf('COM_JEDCHECKER_INFO_XML_NAME_PREFIXED', $extensionName));
			}

			if (preg_match('/(?:\bversion\b|\d\.\d)/i', $extensionName))
			{
				$this->report->addIssue(Report::LEVEL_ERROR, 'NM5', $file,
					Text::sprintf('COM_JEDCHECKER_INFO_XML_NAME_VERSION', $extensionName));
			}

			if (stripos($extensionName, 'joomla') === 0)
			{
				$this->report->addIssue(Report::LEVEL_ERROR, 'TM2', $file,
					Text::sprintf('COM_JEDCHECKER_INFO_XML_NAME_JOOMLA', $extensionName));
			}
			else
			{
				$cleanName = preg_replace('/\s+for\s+Joomla!?$/', '', $extensionName);

				if (stripos($cleanName, 'joomla') !== false)
				{
					$this->report->addIssue(Report::LEVEL_WARNING, 'TM2', $file,
						Text::sprintf('COM_JEDCHECKER_INFO_XML_NAME_JOOMLA_DERIVATIVE', $extensionName, 'https://tm.joomla.org/approved-domains.html'));
				}
			}

			if (preg_match('/[^\x20-\x7E]/', $extensionName))
			{
				$this->report->addError($file, Text::sprintf('COM_JEDCHECKER_INFO_XML_NAME_NON_ASCII', $extensionName));
			}

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

		$this->validateDomain($file, (string) $xml->authorUrl);

		if ($type === 'package' && (string) $xml->packagerurl !== (string) $xml->authorUrl)
		{
			$this->validateDomain($file, (string) $xml->packagerurl);
		}

		if ($type === 'component' && isset($xml->administration->menu))
		{
			$menuName = $lang->_((string) $xml->administration->menu);

			if ($extensionName !== $menuName)
			{
				$this->report->addWarning($file, Text::sprintf('COM_JEDCHECKER_INFO_XML_NAME_ADMIN_MENU', $menuName, $extensionName));
			}
		}

		if ($isTopLevel && $type === 'plugin')
		{
			$parts              = explode(' - ', $extensionName, 2);
			$extensionNameGroup = isset($parts[1]) ? strtolower(preg_replace('/\s/', '', $parts[0])) : false;
			$group              = (string) $xml['group'];

			if ($extensionNameGroup !== $group && $extensionNameGroup !== str_replace('-', '', $group)
				&& !(isset($this->pluginsGroupMap[$extensionNameGroup]) && $this->pluginsGroupMap[$extensionNameGroup] === $group))
			{
				$this->report->addWarning($file, Text::sprintf('COM_JEDCHECKER_INFO_XML_NAME_PLUGIN_FORMAT', $extensionName));
			}
		}

		return true;
	}

	protected function loadExtensionLanguage(\SimpleXMLElement $xml, string $rootDir, string $langTag = 'en-GB'): bool
	{
		$extension = CheckerHelper::getElementName($xml);
		$type      = (string) $xml['type'];

		if ($type === 'plugin' && isset($xml['group']) && strpos($extension, 'plg_') !== 0)
		{
			$extension = 'plg_' . $xml['group'] . '_' . $extension;
		}

		$lang = Factory::getApplication()->getLanguage();

		$lookupLangDirs = [];

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
			$folder = trim((string) $xml->languages['folder'], '/');

			foreach ($xml->languages->language as $language)
			{
				if (trim($language['tag']) === $langTag)
				{
					$lookupLangDirs[] = trim($folder . '/' . dirname($language), '/');
				}
			}
		}

		$lookupLangDirs[] = '';
		$lookupLangDirs   = array_unique($lookupLangDirs);

		$lookupLangFiles = [
			$langTag . '.' . $extension . '.sys.ini',
			$extension . '.sys.ini',
		];

		foreach ($lookupLangDirs as $dir)
		{
			foreach ($lookupLangFiles as $file)
			{
				$langSysFile = $rootDir . '/' . ($dir === '' ? '' : $dir . '/') . $file;

				if (is_file($langSysFile))
				{
					$loadLanguage = new \ReflectionMethod($lang, 'loadLanguage');
					$loadLanguage->setAccessible(true);
					$loadLanguage->invoke($lang, $langSysFile, $extension);

					return true;
				}
			}
		}

		return false;
	}

	protected function validateDomain(string $file, string $url): void
	{
		$domain = (strpos($url, '//') === false) ? $url : parse_url(trim($url), PHP_URL_HOST);

		if (stripos($domain, 'joomla') !== false)
		{
			$this->report->addIssue(Report::LEVEL_ERROR, 'TM1', $file,
				Text::sprintf('COM_JEDCHECKER_INFO_XML_URL_JOOMLA_DERIVATIVE', $url, 'https://tm.joomla.org/approved-domains.html'));
		}
	}
}
