<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2019 Open Source Matters, Inc. All rights reserved.
 * 			   Copyright (C) 2008 - 2016 compojoom.com . All rights reserved.
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');


jimport('joomla.filesystem');
jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.archive');

/**
 * Class jedcheckerControllerPolice
 *
 * @since  1.0
 */
class JedcheckerControllerPolice extends JControllerLegacy
{
	/**
	 * Runs all the rules on the given directory
	 *
	 * @return boolean
	 */
	public function check()
	{
		$rule = JFactory::getApplication()->input->get('rule');

		JLoader::discover('jedcheckerRules', JPATH_COMPONENT_ADMINISTRATOR . '/libraries/rules/');

		$path  = JFactory::getConfig()->get('tmp_path') . '/jed_checker/unzipped';
		$class = 'jedcheckerRules' . ucfirst($rule);

		// Stop if the class does not exist
		if (!class_exists($class))
		{
			return false;
		}

		// Loop through each folder and police it
		$folders = $this->getFolders();

		foreach ($folders as $folder)
		{
			$this->police($class, $folder);
		}

		return true;
	}

	/**
	 * Run each rule and echo the result
	 *
	 * @param   string  $class   - the class name
	 * @param   string  $folder  - the folder where the component is located
	 *
	 * @return void
	 */
	protected function police($class, $folder)
	{
		// Prepare rule properties
		$properties = array('basedir' => JPath::clean($folder));

		// Create instance of the rule
		$police = new $class($properties);

		// Perform check
		$police->check();

		// Get the report and then print it
		$report = $police->get('report');

		echo $report->getHTML();

		flush();
		ob_flush();
	}

	/**
	 * Get the folders that should be checked
	 *
	 * @return array
	 */
	protected function getFolders()
	{
		$folders = array();

		// Add the folders in the "jed_checked/unzipped" folder
		$path        = JFactory::getConfig()->get('tmp_path') . '/jed_checker/unzipped';
		$tmp_folders = JFolder::folders($path);

		if (!empty($tmp_folders))
		{
			foreach ($tmp_folders as $tmp_folder)
			{
				$folders[] = $path . '/' . $tmp_folder;
			}
		}

		// Parse the local.txt file and parse it
		$local = JFactory::getConfig()->get('tmp_path') . '/jed_checker/local.txt';

		if (JFile::exists($local))
		{
			$content = file_get_contents($local);

			if (!empty($content))
			{
				$lines = explode("\n", $content);

				if (!empty($lines))
				{
					foreach ($lines as $line)
					{
						$line = trim($line);

						if (!empty($line))
						{
							if (JFolder::exists(JPATH_ROOT . '/' . $line))
							{
								$folders[] = JPATH_ROOT . '/' . $line;
							}
							elseif (JFolder::exists($line))
							{
								$folders[] = $line;
							}
						}
					}
				}
			}
		}

		return $folders;
	}
}
