<?php
/**
 * @author     Daniel Dimitrov - compojoom.com
 * @date       : 02.06.12
 *
 * @copyright  Copyright (C) 2008 - 2012 compojoom.com . All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');
jimport('joomla.application.component.controllerlegacy');

// We'll need jfile and JFolder all through the compoenent so let us load them here
jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.file');

$input = JFactory::getApplication()->input;
$view = $input->getCmd('view', '');

if ($view == '' && $input->getCmd('task', '') == '')
{
	$input->set('view', 'uploads');
}

$controller = JControllerLegacy::getInstance('jedchecker');
$controller->execute($input->getCmd('task', ''));
$controller->redirect();