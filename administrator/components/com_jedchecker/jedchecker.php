<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2019 Open Source Matters, Inc. All rights reserved.
 * 			   Copyright (C) 2008 - 2016 compojoom.com . All rights reserved.
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 * 			   02.06.12
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;

if (!Factory::getUser()->authorise('core.manage', 'com_jedchecker'))
{
	throw new Exception(Text::_('JERROR_ALERTNOAUTHOR'));
}

$input = Factory::getApplication()->input;
$view = $input->getCmd('view', '');

if ($view === '' && $input->getCmd('task', '') === '')
{
	$input->set('view', 'uploads');
}

$controller = BaseController::getInstance('jedchecker');
$controller->execute($input->getCmd('task', ''));
$controller->redirect();
