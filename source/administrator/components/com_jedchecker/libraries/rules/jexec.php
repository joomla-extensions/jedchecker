<?php
/**
 * @author     eaxs
 * @date       06/08/2012
 * @copyright  Copyright (C) 2008 - 2012 compojoom.com . All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');


/**
 * This class searches all files for the _JEXEC check
 * which prevents direct file access.
 *
 */
class jedcheckerRulesJexec
{
    /**
     * Holds all file names that failed to pass the check
     * @var    array
     */
    protected $missing;


    /**
     * Initiates the file search and check
     *
     * @param     string    $basedir    The base directory of the package to check
     * @return    void
     */
    public function check($basedir)
    {
        $this->missing = array();
        $files = JFolder::files($basedir, '.php', true, true);

        // Iterate through all files in the package
        foreach($files as $file)
        {
            // Try to find the _JEXEC check in the file
            if(!$this->findJExec($file)) {
                $this->missing[] = $file;
            }
        }


        echo '<span class="rule">'.JText::_('COM_JEDCHECKER_RULE_PH2') .'</span><br/>';
        if(count($this->missing)) {
            // Echo all files which don't have the _JEXEC check
            foreach($this->missing AS $file)
            {
                echo $file.'<br/>';
            }
        } else {
            echo '<span class="success">'.JText::_('COM_JEDCHECKER_EVERYTHING_SEEMS_TO_BE_FINE_WITH_THAT_RULE').'</span>';
        }

    }


    /**
     * Reads a file and searches for the _JEXEC statement
     *
     * @param     string    $file    The path to the file
     * @return    boolean            True if the statement was found, otherwise False.
     */
    protected function findJexec($file)
    {
        $content = (array) file($file);

        foreach($content AS $line)
        {
            $pos_2 = strpos($line, '_JEXEC');

            // Skip the line if _JEXEC is not found
            if($pos_2 === false) {
                // Alternatively search for JPATH_PLATFORM
                $pos_2 = strpos($line, 'JPATH_PLATFORM');

                // Nothing found, skip the line
                if($pos_2 === false) continue;
            }

            // Search for "defined" and "die". "or" may not be present
            // depending on syntax
            $pos_1 = stripos($line, 'defined');
            $pos_3 = stripos($line, 'die');

            // Both words must be present
            if($pos_1 === false || $pos_3 === false) {
                continue;
            }

            // Check the position of the words
            if($pos_2 > $pos_1 && $pos_3 > $pos_2) {
                unset($content);
                return true;
            }
        }

        unset($content);

        return false;
    }
}
