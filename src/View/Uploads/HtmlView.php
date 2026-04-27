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

namespace Joomla\Component\Jedchecker\Administrator\View\Uploads;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Jedchecker\Administrator\Rule\RuleDiscovery;

/**
 * HtmlView for the uploads layout.
 *
 * @since  3.0
 */
class HtmlView extends BaseHtmlView
{
	/** @var string */
	protected string $path;

	/** @var array */
	protected array $jsOptions = [];

	/** @var string[] Array of FQCNs for use in the template */
	public array $ruleClasses = [];

	public function display($tpl = null): void
	{
		$app        = Factory::getApplication();
		$this->path = $app->getConfig()->get('tmp_path') . '/jed_checker';

		$app->getLanguage()->load('com_jedchecker.sys', JPATH_ADMINISTRATOR);

		$this->ruleClasses = RuleDiscovery::getRules();

		$this->jsOptions['url']   = Uri::base();
		$this->jsOptions['rules'] = $this->getRuleShortNames();

		$this->setToolbar();

		parent::display($tpl);
	}

	/**
	 * Return all rule FQCNs sorted by ordering (delegates to RuleDiscovery).
	 *
	 * @return  string[]
	 */
	public function getRules(): array
	{
		return RuleDiscovery::getRules();
	}

	/**
	 * Return lowercase short names (e.g. 'jexec', 'xmlmanifest') for the JS AJAX calls.
	 *
	 * @return  string[]
	 */
	public function getRuleShortNames(): array
	{
		$names = [];

		foreach ($this->ruleClasses as $fqcn)
		{
			$parts  = explode('\\', $fqcn);
			$names[] = strtolower(preg_replace('/Rule$/', '', end($parts)));
		}

		return $names;
	}

	public function setToolbar(): void
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

		if (Factory::getApplication()->getIdentity()->authorise('core.admin', 'com_jedchecker'))
		{
			ToolbarHelper::preferences('com_jedchecker');
		}
	}

	private function filesExist(string $type): bool
	{
		$path = Factory::getApplication()->getConfig()->get('tmp_path') . '/jed_checker/' . $type;

		if (is_dir($path))
		{
			if (\Joomla\Filesystem\Folder::folders($path) || \Joomla\Filesystem\Folder::files($path))
			{
				return true;
			}
		}
		else
		{
			$local = Factory::getApplication()->getConfig()->get('tmp_path') . '/jed_checker/local.txt';

			if ($type === 'unzipped' && is_file($local))
			{
				return true;
			}
		}

		return false;
	}
}
