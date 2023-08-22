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

use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;

/**
 * Class JedcheckerViewUploads
 *
 * @since  1.0
 */
class JedcheckerViewUploads extends HtmlView
{
	/** @var string */
	protected $path;

	/** @var array */
	protected $jsOptions;

	/**
	 * Display method
	 *
	 * @param   string  $tpl  - the template
	 *
	 * @return mixed|void
	 */
	public function display($tpl = null)
	{
		$this->path = Factory::getConfig()->get('tmp_path') . '/jed_checker';

		// Load translation for "JED Checker" title from sys.ini file
		Factory::getLanguage()->load('com_jedchecker.sys', JPATH_ADMINISTRATOR);

		$this->setToolbar();
		$this->jsOptions['url'] = Uri::base();
		$this->jsOptions['rules'] = $this->getRules();
		parent::display($tpl);
	}

	/**
	 * Get the component rules
	 *
	 * @return array
	 */
	public function getRules()
	{
		$rules = array();
		$files = Folder::files(JPATH_COMPONENT_ADMINISTRATOR . '/libraries/rules', '\.php$', false, false);

		JLoader::discover('jedcheckerRules', JPATH_COMPONENT_ADMINISTRATOR . '/libraries/rules/');

		foreach ($files as $file)
		{
			$rule = substr($file, 0, -4);
			$class = 'jedcheckerRules' . ucfirst($rule);

			if (class_exists($class) && is_subclass_of($class, 'JEDcheckerRule'))
			{
				$rules[$rule] = $class::$ordering;
			}
		}

		asort($rules, SORT_NUMERIC);
		$rules = array_keys($rules);

		return $rules;
	}

	/**
	 * Creates the toolbar options
	 *
	 * @return void
	 */
	public function setToolbar()
	{
		if ($this->filesExist('unzipped'))
		{
			ToolbarHelper::custom('check', 'search', 'search', Text::_('COM_JEDCHECKER_TOOLBAR_CHECK'), false);
		}

		ToolbarHelper::title(Text::_('COM_JEDCHECKER'));

		if (file_exists($this->path))
		{
			ToolbarHelper::custom('uploads.clear', 'delete', 'delete', Text::_('COM_JEDCHECKER_TOOLBAR_CLEAR'), false);
		}

		if (Factory::getUser()->authorise('core.admin', 'com_jedchecker'))
		{
			ToolbarHelper::preferences('com_jedchecker');
		}
	}

	/**
	 * Checks if folder + files exist in the jed_checker tmp path
	 *
	 * @param   string  $type  - action
	 *
	 * @return boolean
	 */
	private function filesExist($type)
	{
		$path = Factory::getConfig()->get('tmp_path') . '/jed_checker/' . $type;

		if (Folder::exists($path))
		{
			if (Folder::folders($path) || Folder::files($path))
			{
				return true;
			}
		}
		else
		{
			$local = Factory::getConfig()->get('tmp_path') . '/jed_checker/local.txt';

			if ($type === 'unzipped' && File::exists($local))
			{
				return true;
			}
		}

		return false;
	}
}
