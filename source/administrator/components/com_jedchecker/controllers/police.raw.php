<?php
/**
 * @author Daniel Dimitrov - compojoom.com
 * @date: 02.06.12
 *
 * @copyright  Copyright (C) 2008 - 2012 compojoom.com . All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');

defined('_JEXEC') or die('Restricted access');

jimport('joomla.filesystem');
jimport('joomla.filesystem.archive');

class jedcheckerControllerPolice extends JController {
    public function check() {
        $rule = JRequest::getString('rule');
        JLoader::discover('jedcheckerRules',JPATH_COMPONENT_ADMINISTRATOR . '/libraries/rules/');
//        require_once JPATH_COMPONENT_ADMINISTRATOR . '/libraries/rules/'.$rule.'.php';
        $path = JFactory::getConfig()->get('tmp_path') . '/jed_checker/unzipped';
        $class = 'jedcheckerRules'.ucfirst($rule);
        $police = new $class;

        $folders = JFolder::folders($path);
        $police->check($path.'/'.$folders[0]);

    }
}