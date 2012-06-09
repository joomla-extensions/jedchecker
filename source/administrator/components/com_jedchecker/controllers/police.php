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
        require_once JPATH_COMPONENT_ADMINISTRATOR . '/libraries/rules/htmlindexes.php';
        $path = JFactory::getConfig()->get('tmp_path') . '/jed_checker/unzipped';
        $police = new jedcheckerRulesHtmlindexes;
        $folders = JFolder::folders($path);

        $police->check($path.'/'.$folders[0]);

    }
}