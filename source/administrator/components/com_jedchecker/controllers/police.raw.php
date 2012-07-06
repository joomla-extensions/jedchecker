<?php
/**
 * @author Daniel Dimitrov - compojoom.com
 * @date: 02.06.12
 *
 * @copyright  Copyright (C) 2008 - 2012 compojoom.com . All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');


jimport('joomla.filesystem');
jimport('joomla.filesystem.archive');


class jedcheckerControllerPolice extends JController
{
    public function check()
    {
        $rule = JRequest::getString('rule');

        JLoader::discover('jedcheckerRules',JPATH_COMPONENT_ADMINISTRATOR . '/libraries/rules/');

        $path   = JFactory::getConfig()->get('tmp_path') . '/jed_checker/unzipped';
        $class  = 'jedcheckerRules'.ucfirst($rule);

        // Stop if the class does not exist
        if(!class_exists($class)) {
            return false;
        }

        // Prepare rule properties
        $folders    = JFolder::folders($path);
        $properties = array('basedir' => $path.'/'.$folders[0]);

        // Create instance of the rule
        $police = new $class($properties);

        // Perform check
        $police->check();

        // Get the report and then print it
        $report = $police->get('report');

        echo '<span class="rule">'
           .  JText::_('COM_JEDCHECKER_RULE') .' ' . JText::_($police->get('id'))
           . ' - '. JText::_($police->get('title'))
           . '</span><br/>'
           . $report->getHTML();
    }
}
