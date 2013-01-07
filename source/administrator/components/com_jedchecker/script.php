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


    public function loadLanguage()
    {
        $extension = $this->extension;
        $jlang =& JFactory::getLanguage();
        $path = $this->parent->getParent()->getPath('source') . '/administrator';
        $jlang->load($extension, $path, 'en-GB', true);
        $jlang->load($extension, $path, $jlang->getDefault(), true);
        $jlang->load($extension, $path, null, true);
        $jlang->load($extension . '.sys', $path, 'en-GB', true);
        $jlang->load($extension . '.sys', $path, $jlang->getDefault(), true);
        $jlang->load($extension . '.sys', $path, null, true);
    }
}