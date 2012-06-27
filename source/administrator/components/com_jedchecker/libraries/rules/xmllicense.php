<?php
/**
 * @author     eaxs
 * @date       06/26/2012
 * @copyright  Copyright (C) 2008 - 2012 compojoom.com . All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');


/**
 * This class searches all xml manifestes for a valid license.
 *
 */
class jedcheckerRulesXMLlicense
{
    /**
     * Holds all files that failed to pass the check
     * @var    array
     */
    protected $missing;


    /**
     * Initiates the search and check
     *
     * @param     string    $basedir    The base directory of the package to check
     * @return    void
     */
    public function check($basedir)
    {
        $this->missing = array();
        $files = JFolder::files($basedir, '.xml$', true, true);

        // Iterate through all files in the package
        foreach ($files as $file)
        {
            // Try to find the license in the file
            if (!$this->find($file)) {
                $this->missing[] = $file;
            }
        }


        echo '<span class="rule">'.JText::_('COM_JEDCHECKER_RULE_PH3') .'</span><br/>';

        // Echo all files which failed the check
        if (count($this->missing)) {
            foreach ($this->missing AS $file)
            {
                echo $file.'<br/>';
            }
        }
        else {
            echo '<span class="success">'.JText::_('COM_JEDCHECKER_EVERYTHING_SEEMS_TO_BE_FINE_WITH_THAT_RULE').'</span>';
        }

    }


    /**
     * Reads a file and searches for the license
     *
     * @param     string    $file    The path to the file
     * @return    boolean            True if the license was found, otherwise False.
     */
    protected function find($file)
    {
        $xml = JFactory::getXML($file);

        // Failed to parse the xml file.
        // Assume that this is not a extension manifest
        if (!$xml) return true;

        // Check if this is an extension manifest
        // 1.5 uses 'install', 1.6 uses 'extension'
		if ($xml->getName() != 'install' && $xml->getName() != 'extension')
		{
			return true;
		}

        // Check if there's a license tag
        if (!isset($xml->license)) return false;

        // Check if the license is gpl
        if (stripos($xml->license, 'gpl') === false &&
            stripos($xml->license, 'general public license') === false)
        {
            return false;
        }


        // All checks passed. Return true
        return true;
    }
}
