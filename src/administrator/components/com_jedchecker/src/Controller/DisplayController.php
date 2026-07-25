<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 * @copyright  Copyright (C) 2017 - 2026 Open Source Matters, Inc. All rights reserved.
 *             Copyright (C) 2008 - 2016 compojoom.com All rights reserved.
 *
 * @license    GNU General Public Licence version 2 or later; see LICENCE.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Controller;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\MVC\Controller\BaseController;

/**
 * DisplayController routes to the default uploads view.
 *
 * @since  3.0.0
 */
class DisplayController extends BaseController
{
    protected $default_view = 'uploads';

    /**
     * display
     *
     * Displays the view
     *
     * @param $cachable
     * @param $urlparams
     *
     * @return $this
     * @since  3.0.0
     * @throws \Exception
     */
    public function display($cachable = false, $urlparams = []): static
    {
        if (! $this->app->getIdentity()->authorise('core.manage', 'com_jedchecker')) {
            throw new \RuntimeException($this->app->getLanguage()->_('JERROR_ALERTNOAUTHOR'), 403);
        }

        return parent::display($cachable, $urlparams);
    }
}
