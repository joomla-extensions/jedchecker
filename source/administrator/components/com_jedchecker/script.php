<?php
/**
 * @author Daniel Dimitrov - compojoom.com
 * @date: 02.06.12
 *
 * @copyright  Copyright (C) 2008 - 2012 compojoom.com . All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');


class com_jedcheckerInstallerScript
{
    protected $extension = 'com_jedchecker';

    function preflight( $type, $parent ) {
        $this->parent = $parent;
        if (version_compare(PHP_VERSION, '5.3.1', '<')) {
            $this->loadLanguage();
            Jerror::raiseWarning(null, JText::sprintf('COM_JEDCHECKER_PHP_VERSION_INCOMPATIBLE', PHP_VERSION, '5.3.6'));
            return false;
        }
    }

	/**
	 * Update cleans out any old rules.
	 *
	 * @param   JInstallerComponent  $parent  Is the class calling this method.
	 *
	 * @return  bool|null  If this returns false, Joomla will abort the update and undo everything already done.
	 */
	public function update($parent)
	{
        // Load our language
        $this->loadLanguage();

		// Doing it this way in case there are other old rules to be deleted
		$oldRules = array('htmlindexes');

		foreach ($oldRules as $rule)
		{
			$rulePhpFile = JPATH_ADMINISTRATOR . '/components/' . $this->extension . '/libraries/rules/' . $rule . '.php';
			$ruleIniFile = JPATH_ADMINISTRATOR . '/components/' . $this->extension . '/libraries/rules/' . $rule . '.ini';

			// Remove the rule's php file
			if(file_exists($rulePhpFile))
			{
				if(JFile::delete($rulePhpFile))
				{
					echo '<p>' . JText::sprintf('COM_JEDCHECKER_OLD_RULE_X_PHP_FILE_REMOVED', $rule) . '</p>';
				}
				else
				{
					echo '<p>' . JText::sprintf('COM_JEDCHECKER_OLD_RULE_X_PHP_FILE_NOT_REMOVED', $rule) . '</p>';
				}
			}

			// Remove the rule's ini file
			if(file_exists($ruleIniFile))
			{
				if(JFile::delete($ruleIniFile))
				{
					echo '<p>' . JText::sprintf('COM_JEDCHECKER_OLD_RULE_X_INI_FILE_REMOVED', $rule) . '</p>';
				}
				else
				{
					echo '<p>' . JText::sprintf('COM_JEDCHECKER_OLD_RULE_X_INI_FILE_NOT_REMOVED', $rule) . '</p>';
				}
			}
		}
	}

	public function loadLanguage()
    {
        $extension = $this->extension;
        $jlang = JFactory::getLanguage();
        $path = $this->parent->getParent()->getPath('source') . '/administrator';
        $jlang->load($extension, $path, 'en-GB', true);
        $jlang->load($extension, $path, $jlang->getDefault(), true);
        $jlang->load($extension, $path, null, true);
        $jlang->load($extension . '.sys', $path, 'en-GB', true);
        $jlang->load($extension . '.sys', $path, $jlang->getDefault(), true);
        $jlang->load($extension . '.sys', $path, null, true);
    }
}