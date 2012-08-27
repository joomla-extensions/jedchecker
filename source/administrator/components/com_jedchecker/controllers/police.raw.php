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
            echo 'Class '.$class.' does not exist';
            return false;
        }

        // Loop through each folder and police it
        $folders    = $this->getFolders();
        foreach($folders as $folder) {
            $this->police($class, $folder);
        }

        return true;
    }

    protected function police($class, $folder)
    {
        // Create instance of the rule
        $properties = array('basedir' => $folder);
        $police = new $class($properties);

        // Perform check
        $police->check();

        // Get the report and then print it
        $report = $police->get('report');

        echo '<span class="rule">'
           .  JText::_('COM_JEDCHECKER_RULE') .' ' . JText::_($police->get('id'))
           . ' - '. JText::_($police->get('title'))
           . '</span><br/>'
           . '<span class="folder">Folder: '.str_replace(JPATH_ROOT, '', $folder).'</span><br/>'
           . $report->getHTML()
           . '<br/>&nbsp;<br/>'
        ;

        flush();
        ob_flush();
    }

    protected function getFolders()
    {
        $folders = array();

        // Add the folders in the "jed_checked/unzipped" folder
        $path = JFactory::getConfig()->get('tmp_path') . '/jed_checker/unzipped';
        $tmp_folders = JFolder::folders($path);
        if(!empty($tmp_folders)) {
            foreach($tmp_folders as $tmp_folder) {
                $folders[] = $path.'/'.$tmp_folder;
            }
        }

        // Parse the local.ini file and parse it
        $local = JFactory::getConfig()->get('tmp_path') . '/jed_checker/local.txt';
        if(JFile::exists($local)) {
            $content = JFile::read($local);
            if(!empty($content)) {
                $lines = explode("\n", $content);
                if(!empty($lines)) {
                    foreach($lines as $line) {
                        if(!empty($line) && JFolder::exists(JPATH_ROOT.'/'.$line)) {
                            $folders[] = JPATH_ROOT.'/'.$line;
                        }
                    }
                }
            }
        }

        return $folders;
    }
}
