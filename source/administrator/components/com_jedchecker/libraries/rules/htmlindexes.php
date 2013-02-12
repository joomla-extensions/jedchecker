<?php
/**
 * @author Daniel Dimitrov - compojoom.com
 * @date: 06.07.12
 *
 * @copyright  Copyright (C) 2008 - 2012 compojoom.com . All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');

// Include the rule base class
require_once(JPATH_COMPONENT_ADMINISTRATOR.'/models/rule.php');


class jedcheckerRulesHtmlindexes extends JEDcheckerRule
{
    /**
     * The formal ID of this rule. For example: SE1.
     *
     * @var    string
     */
    protected $id = 'SE1';

    /**
     * The title or caption of this rule.
     *
     * @var    string
     */
    protected $title = 'COM_JEDCHECKER_RULE_SE1';

    /**
     * The description of this rule.
     *
     * @var    string
     */
    protected $description = 'COM_JEDCHECKER_RULE_SE1_DESC';

    public $folders = array();
    public $indexes = array();

    public function check()
    {
        $this->findHtml($this->basedir, 1);

        /**
         * let us "merge" the 2 arrays
         * If a folder has an index.html file, then the value of the folders array will be true
         */
        $indexes = array_replace($this->folders, $this->indexes);

        if(count($indexes) && in_array(false, $indexes)) {
            foreach($indexes as $key => $index)
            {
                if(!$index) {
                    $this->report->addError($key, 'COM_JEDCHECKER_ERROR_HTML_INDEX_NOT_FOUND');
                }
            }
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
        $system_folders = explode(',', $this->params->get('sysfolders'));

        // Make sure there are no spaces
        array_walk($system_folders, create_function('&$v', '$v = trim($v);'));

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
            } else if ($file->getFileName() == '.svn') {
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
