<?php
/**
 * @author Daniel Dimitrov - compojoom.com
 * @date: 02.06.12
 *
 * @copyright  Copyright (C) 2008 - 2012 compojoom.com . All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');

class jedcheckerRulesHtmlindexes {
    public $folders = array();
    public $indexes = array();

    public function check($startFolder){
        $this->findHtml($startFolder);

        /**
         * let us "merge" the 2 arrays
         * If a folder has an index.html file, then the value of the folders array will be true
         */
        $indexes = array_replace($this->folders, $this->indexes);

        echo '<span class="rule">'.JText::_('COM_JEDCHECKER_RULE_SE1') . '</span><br />';
        if(count($indexes)) {
            foreach($indexes as $key => $index) {
                if(!$index) {
                    echo $key . '<br />';
                }
            }
        } else {
            echo '<span class="success">'.JText::_('COM_JEDCHECKER_EVERYTHING_SEEMS_TO_BE_FINE_WITH_THAT_RULE').'</span>';
        }


    }

    /**
     * Recursively checking if each folder in the package has index.html files
     * if it has it saves the info the indexes array (folder_name => true)
     * + it also saves all folders names in the folders array (folder_name => false)
     * @param $start
     */
    public function findHtml($start) {
        $iterator = new RecursiveDirectoryIterator($start);

        // there should be a better way to find out if the main directory has an index.html file...
        if(file_exists($start.'/index.html')) {
            $this->folders[$start] = true;
        } else {
            $this->folders[$start] = false;
        }

        foreach($iterator as $file){
            if($file->isFile()) {
                if($file->getFileName() == 'index.html') {
                    // fill an array with the tables that contain an index.html file
                    $this->indexes[$file->getPath()] = true;
                }
            } else {
                //let us save all folders in an array
                $this->folders[$file->getPathname()] = false;
                $this->findHtml($file->getPathname());

            }
        }


    }
}