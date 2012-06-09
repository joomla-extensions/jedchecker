<?php
/**
 * @author Daniel Dimitrov - compojoom.com
 * @date: 02.06.12
 *
 * @copyright  Copyright (C) 2008 - 2012 compojoom.com . All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');
jimport('joomla.application.component.controller');

$view = JRequest::getCmd('view','');
if($view == '' && JRequest::getCmd('task') == '') {
    JRequest::setVar('view', 'uploads');
}
$controller = JController::getInstance('jedchecker');
$controller->execute(JRequest::getCmd('task'));
$controller->redirect();