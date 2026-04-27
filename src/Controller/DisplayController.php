<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2025 Open Source Matters, Inc. All rights reserved.
 *             Copyright (C) 2008 - 2016 compojoom.com . All rights reserved.
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 *
 * @license    GNU General Public Licence version 2 or later; see LICENCE.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Controller;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\MVC\Controller\BaseController;

/**
 * DisplayController routes to the default uploads view.
 *
 * @since  3.0
 */
class DisplayController extends BaseController
{
	protected $default_view = 'uploads';

	public function display($cachable = false, $urlparams = []): static
	{
		if (!$this->app->getIdentity()->authorise('core.manage', 'com_jedchecker'))
		{
			throw new \RuntimeException($this->app->getLanguage()->_('JERROR_ALERTNOAUTHOR'), 403);
		}

		return parent::display($cachable, $urlparams);
	}
}
