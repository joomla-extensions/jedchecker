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

    public function check($startFolder)
    {
        $this->findHtml($startFolder, 1);

        /**
         * let us "merge" the 2 arrays
         * If a folder has an index.html file, then the value of the folders array will be true
         */
        $indexes = array_replace($this->folders, $this->indexes);

        echo '<span class="rule">'.JText::_('COM_JEDCHECKER_RULE_SE1') . '</span><br />';
        if(count($indexes) && in_array(false, $indexes)) {
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
    public function findHtml($start, $root = 0)
    {

        // array of system folders (regex)
        // will match paths ending with these folders
        $system_folders = array(
            'administrator',
            'components',
            'language',
            'language/.*',
            'media',
            'modules',
            'plugins',
            'plugins/content',
            'plugins/editors',
            'plugins/editors-xtd',
            'plugins/finder',
            'plugins/search',
            'plugins/system',
            'plugins/user'
        );

        $iterator = new DirectoryIterator($start);

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                continue;
            }

            $path = $file->getPathname();

            // set the path to the root start path only for first time
            if ($root && $file->getFileName() == '.') {
                $path = $start;
            } else if ($file->isDot()) {
                continue;
            }

            $this->folders[$path] = true;
            // only check index.html in non-system folders
            if (!preg_match('#/('.implode('|', $system_folders).')$#i', str_replace('\\', '/', $path))) {
                if (!file_exists($path.'/index.html')) {
                    $this->folders[$path] = false;
                }
            } else {
                // set parent to true too
                $this->folders[dirname($path)] = true;
            }
            // search in subfolders if not same as start
            if ($path != $start) {
                $this->findHtml($path);
            }
        }
    }
}