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

class jedcheckerViewUploads extends JView {

    public function display($tpl = null) {
        $this->setToolbar();
        parent::display($tpl);
    }

    public function setToolbar() {
        JToolBarHelper::custom('uploads.unzip', 'unzip', 'unzip', 'unzip', false);
    }
}