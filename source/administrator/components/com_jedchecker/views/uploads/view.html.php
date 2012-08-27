<?php
/**
 * @author Daniel Dimitrov - compojoom.com
 * @date: 02.06.12
 *
 * @copyright  Copyright (C) 2008 - 2012 compojoom.com . All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.view');

class jedcheckerViewUploads extends JView
{

    public function display($tpl = null)
    {
        $this->setToolbar();
        $this->jsOptions['url'] = JURI::base();
        $this->jsOptions['rules'] = $this->getRules();
        parent::display($tpl);
    }

    public function getRules()
    {
        $rules = array();
        $files = JFolder::files(JPATH_COMPONENT_ADMINISTRATOR . '/libraries/rules', '.php$', false, false);
        JLoader::discover('jedcheckerRules',JPATH_COMPONENT_ADMINISTRATOR . '/libraries/rules/');

        foreach ($files as $file)
        {
            $rules[] = substr($file, 0, -4);
        }

        return $rules;
    }

    public function setToolbar()
    {
        if($this->filesExist('archives')) {
            JToolBarHelper::custom('uploads.unzip', 'unzip', 'unzip', 'unzip', false);
        }
        if($this->filesExist('unzipped')) {
            JToolBarHelper::custom('police.check', 'police-check', 'police-check', 'check', false);
        }

        JToolBarHelper::title('JED checker');
    }

    /**
     * Checks if folder + files exist in the jed_checker tmp path
     * @param $type
     * @return bool
     */
    private function filesExist($type) {
        $path = JFactory::getConfig()->get('tmp_path') . '/jed_checker/'.$type;
        if(JFolder::exists($path)) {
            if(JFolder::folders($path, '.', false) || JFolder::files($path, '.', false)) {
                return true;
            }
        } else {
            $local = JFactory::getConfig()->get('tmp_path') . '/jed_checker/local.txt';
            if ($type == 'unzipped' && JFile::exists($local)) {
                return true;
            }
        }
        return false;
    }
}
