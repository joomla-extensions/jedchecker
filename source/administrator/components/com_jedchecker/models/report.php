<?php
/**
 * @author     eaxs <support@projectfork.net>
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 * @date       07/06/2012
 * @copyright  Copyright (C) 2008 - 2012 compojoom.com . All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');

/**
 * Class JEDcheckerReport
 *
 * This class is meant to be used by JED rules to
 * create a report.
 *
 * @since  1.0
 */
class JEDcheckerReport extends JObject
{
	/**
	 * Contains the report data.
	 *
	 * @see    reset
	 * @var    array
	 */
	protected $data;

	/**
	 * The absolute path to the target extension.
	 *
	 * @var    string
	 */
	protected $basedir;

	/**
	 * Constructor. Initialises variables.
	 *
	 * @param   mixed  $properties  Either and associative array or another
	 *                              object to set the initial properties of the object.
	 */
	public function __construct($properties = null)
	{
		// Construct JObject
		parent::__construct($properties);

		// Initialise vars
		if (empty($this->data))
		{
			$this->reset();
		}
	}

	/**
	 * Resets the report data.
	 *
	 * @return    void
	 */
	public function reset()
	{
		$this->data = array();

		$this->data['errors'] = array();
		$this->data['compat'] = array();
		$this->data['info'] = array();

		$this->data['count'] = new stdClass;
		$this->data['count']->total = 0;
		$this->data['count']->errors = 0;
		$this->data['count']->compat = 0;
		$this->data['count']->info = 0;
	}

	/**
	 * Adds an error to the report.
	 *
	 * @param   string   $location  - The location of the error. Can be a path to a file or dir.
	 * @param   string   $text      - An optional description of the error.
	 * @param   integer  $line      - If $location is a file, you may specify the line where the
	 *                                   error occurred.
	 *
	 * @return    void
	 */
	public function addError($location, $text = null, $line = 0)
	{
		$item = new stdClass;
		$item->location = $location;
		$item->line = $line;
		$item->text = $text;

		$this->addItem($item, 'errors');
	}

	/**
	 * Adds an error to the report.
	 *
	 * @param   string   $location  - The location of the error. Can be a path to a file or dir.
	 * @param   string   $text      - An optional description of the error.
	 * @param   integer  $line      - If $location is a file, you may specify the line where the
	 *                                   error occurred.
	 *
	 * @return    void
	 */
	public function addInfo($location, $text = null, $line = 0)
	{
		$item = new stdClass;
		$item->location = $location;
		$item->line = $line;
		$item->text = $text;

		$this->addItem($item, 'info');
	}

	/**
	 * Adds a compatibility issue to the report.
	 *
	 * @param   string   $location  - The location of the issue. Can be a path to a file or dir.
	 * @param   string   $text      - An optional description of the issue
	 * @param   integer  $line      - If $location is a file, you may specify the line where the
	 *                                   issue occurred.
	 *
	 * @return    void
	 */
	public function addCompat($location, $text = null, $line = 0)
	{
		$item = new stdClass;
		$item->location = $location;
		$item->line = $line;
		$item->text = $text;

		$this->addItem($item, 'compat');
	}

	/**
	 * Formats the existing report data into HTML and returns it.
	 *
	 * @return    string    The HTML report data
	 */
	public function getHTML()
	{
		$html = array();

		if ($this->data['count']->total == 0)
		{
			// No errors or compatibility issues found
			$html[] = '<span class="success">';
			$html[] = JText::_('COM_JEDCHECKER_EVERYTHING_SEEMS_TO_BE_FINE_WITH_THAT_RULE');
			$html[] = '</span>';
		}
		else
		{
			$error_count = $this->data['count']->errors;
			$compat_count = $this->data['count']->compat;
			$info_count = $this->data['count']->info;

			// Go through the error list
			if ($error_count > 0)
			{
				$html[] = '<strong>' . $error_count . ' ' . JText::_('COM_JEDCHECKER_ERRORS') . '</strong>';
				$html[] = '<ul class="jedchecker-rule-errors">';

				foreach ($this->data['errors'] AS $i => $item)
				{
					$num = $i + 1;

					// Add the error count number
					$html[] = '<li><strong>#' . str_pad($num, 3, '0', STR_PAD_LEFT) . ':</strong>&nbsp;';
					$html[] = $item->location;

					// Add line information if given
					if ($item->line > 0)
					{
						$html[] = ' ' . JText::_('COM_JEDCHECKER_IN_LINE') . ': <strong>' . $item->line . '</strong>';
					}

					// Add text if given
					if (!empty($item->text))
					{
						$html[] = '<br /><small>' . $item->text . '</small>';
					}

					$html[] = '</li>';
				}

				$html[] = '</ul>';
			}

			// Go through the compat list
			if ($compat_count > 0)
			{
				$html[] = '<strong>' . $error_count . ' ' . JText::_('COM_JEDCHECKER_COMPAT_ISSUES') . '</strong>';
				$html[] = '<ul class="jedchecker-rule-compat">';

				foreach ($this->data['compat'] AS $i => $item)
				{
					$num = $i + 1;

					// Add the error count number
					$html[] = '<li><p><strong>#' . str_pad($num, 3, '0', STR_PAD_LEFT) . '</strong> ';
					$html[] = $item->location;

					// Add line information if given
					if ($item->line > 0)
					{
						$html[] = ' ' . JText::_('COM_JEDCHECKER_IN_LINE') . ': <strong>' . $item->line . '</strong>';
					}

					$html[] = '</p>';

					// Add text if given
					if (!empty($item->text))
					{
						$html[] = '<small>' . $item->text . '</small>';
					}

					$html[] = '</li>';
				}

				$html[] = '</ul>';
			}

			// Go through the compat list
			if ($info_count > 0)
			{
				$html[] = '<strong>' . $info_count . ' ' . JText::_('COM_JEDCHECKER_INFO') . '</strong>';
				$html[] = '<ul class="jedchecker-info-message">';

				foreach ($this->data['info'] AS $i => $item)
				{
					$num = $i + 1;

					// Add the error count number
					$html[] = '<li><p><strong>#' . str_pad($num, 3, '0', STR_PAD_LEFT) . '</strong> ';
					$html[] = $item->location;

					// Add line information if given
					if ($item->line > 0)
					{
						$html[] = ' ' . JText::_('COM_JEDCHECKER_IN_LINE') . ': <strong>' . $item->line . '</strong>';
					}

					$html[] = '</p>';

					// Add text if given
					if (!empty($item->text))
					{
						$html[] = '<small>' . $item->text . '</small>';
					}

					$html[] = '</li>';
				}

				$html[] = '</ul>';
			}
		}

		return implode('', $html);
	}

	/**
	 * Adds an item to the report data
	 *
	 * @param   object  $item  - The item to add.
	 * @param   string  $type  - Optional item type. Can be 'errors' or 'compat'.
	 *                              Defaults to 'errors'.
	 *
	 * @return    void
	 */
	protected function addItem($item, $type = 'errors')
	{
		// Remove the base dir from the location
		if (!empty($this->basedir))
		{
			$item->location = str_replace($this->basedir, '', $item->location);

			if ($item->location == '')
			{
				$item->location = '/';
			}
		}

		// Add the item to the report data
		$this->data[$type][] = $item;

		$this->data['count']->total++;
		$this->data['count']->$type++;
	}
}
