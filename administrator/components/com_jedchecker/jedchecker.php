<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');
jimport('joomla.application.component.controllerlegacy');

if (!JFactory::getUser()->authorise('core.manage', 'com_jedchecker'))
{
	throw new Exception(JText::_('JERROR_ALERTNOAUTHOR'));
}

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
