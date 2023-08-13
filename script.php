<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2022 Open Source Matters, Inc. All rights reserved.
 * 			   Copyright (C) 2008 - 2016 compojoom.com . All rights reserved.
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Installer\Adapter\ComponentAdapter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;

/**
 * Class Com_JedcheckerInstallerScript
 *
 * @since  1.5
 */
class Com_JedcheckerInstallerScript
{
	protected $extension = 'com_jedchecker';
	protected $min_php = '5.6.0';
	protected $min_joomla = '3.8.0';
	protected $parent;

	/**
	 * Function executed before the the installation
	 *
	 * @param   string            $type    - the installation type
	 * @param   ComponentAdapter  $parent  - the parent class
	 */
	public function preflight($type, $parent)
	{
		$this->parent = $parent;

		if (version_compare(PHP_VERSION, $this->min_php, '<'))
		{
			$this->loadLanguage();

			$msg = Text::sprintf('COM_JEDCHECKER_PHP_VERSION_INCOMPATIBLE', PHP_VERSION, $this->min_php);
			Log::add($msg, Log::WARNING, 'jerror');

			return false;
		}

		if (version_compare(JVERSION, $this->min_joomla, '<'))
		{
			$this->loadLanguage();

			$msg = Text::sprintf('COM_JEDCHECKER_JOOMLA_VERSION_INCOMPATIBLE', JVERSION, $this->min_joomla);
			Log::add($msg, Log::WARNING, 'jerror');

			return false;
		}
	}

	/**
	 * Update cleans out any old rules.
	 *
	 * @param   ComponentAdapter  $parent  Is the class calling this method.
	 *
	 * @return  bool|null  If this returns false, Joomla will abort the update and undo everything already done.
	 */
	public function update($parent)
	{
		$this->loadLanguage();

		// Doing it this way in case there are other old rules to be deleted
		$oldRules = array('htmlindexes');

		foreach ($oldRules as $rule)
		{
			$rulePhpFile = JPATH_ADMINISTRATOR . '/components/' . $this->extension . '/libraries/rules/' . $rule . '.php';
			$ruleIniFile = JPATH_ADMINISTRATOR . '/components/' . $this->extension . '/libraries/rules/' . $rule . '.ini';

			// Remove the rule's php file
			if (file_exists($rulePhpFile))
			{
				if (File::delete($rulePhpFile))
				{
					$msg = Text::sprintf('COM_JEDCHECKER_OLD_RULE_X_PHP_FILE_REMOVED', $rule);
				}
				else
				{
					$msg = Text::sprintf('COM_JEDCHECKER_OLD_RULE_X_PHP_FILE_NOT_REMOVED', $rule);
				}

				echo "<p>$msg</p>";
			}

			// Remove the rule's ini file
			if (file_exists($ruleIniFile))
			{
				if (File::delete($ruleIniFile))
				{
					$msg = Text::sprintf('COM_JEDCHECKER_OLD_RULE_X_INI_FILE_REMOVED', $rule);
				}
				else
				{
					$msg = Text::sprintf('COM_JEDCHECKER_OLD_RULE_X_INI_FILE_NOT_REMOVED', $rule);
				}

				echo "<p>$msg</p>";
			}
		}
	}

	/**
	 * Load language necessary during the installation
	 *
	 * @return void
	 */
	public function loadLanguage()
	{
		$extension = $this->extension;
		$jlang = Factory::getLanguage();
		$path = $this->parent->getParent()->getPath('source') . '/administrator/components/' . $extension;
		$jlang->load($extension, $path, 'en-GB', true);
		$jlang->load($extension, $path, $jlang->getDefault(), true);
		$jlang->load($extension, $path, null, true);
		$jlang->load($extension . '.sys', $path, 'en-GB', true);
		$jlang->load($extension . '.sys', $path, $jlang->getDefault(), true);
		$jlang->load($extension . '.sys', $path, null, true);
	}
}
