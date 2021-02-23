<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2019 Open Source Matters, Inc. All rights reserved.
 * 			   Copyright (C) 2008 - 2016 compojoom.com . All rights reserved.
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 * 			   eaxs <support@projectfork.net>
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
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
		$this->data['warning'] = array();

		$this->data['count'] = new stdClass;
		$this->data['count']->total = 0;
		$this->data['count']->errors = 0;
		$this->data['count']->compat = 0;
		$this->data['count']->warning = 0;
		$this->data['count']->info = 0;
	}

	/**
	 * Adds an error to the report.
	 *
	 * @param   string   $location  - The location of the error. Can be a path to a file or dir.
	 * @param   string   $text      - An optional description of the error.
	 * @param   integer  $line      - If $location is a file, you may specify the line where the
	 *                                   error occurred.
	 * @param   string   $code      - Code at that location (to be displayed below the description)
	 *
	 * @return    void
	 */
	public function addError($location, $text = null, $line = 0, $code = null)
	{
		$item = new stdClass;
		$item->location = $location;
		$item->line = $line;
		$item->text = $text;
		$item->code = $code;

		$this->addItem($item, 'errors');
	}

	/**
	 * Adds an error to the report.
	 *
	 * @param   string   $location  - The location of the error. Can be a path to a file or dir.
	 * @param   string   $text      - An optional description of the error.
	 * @param   integer  $line      - If $location is a file, you may specify the line where the
	 *                                   error occurred.
	 * @param   string   $code      - Code at that location (to be displayed below the description)
	 *
	 * @return    void
	 */
	public function addInfo($location, $text = null, $line = 0, $code = null)
	{
		$item = new stdClass;
		$item->location = $location;
		$item->line = $line;
		$item->text = $text;
		$item->code = $code;

		$this->addItem($item, 'info');
	}

	/**
	 * Adds a compatibility issue to the report.
	 *
	 * @param   string   $location  - The location of the issue. Can be a path to a file or dir.
	 * @param   string   $text      - An optional description of the issue
	 * @param   integer  $line      - If $location is a file, you may specify the line where the
	 *                                   issue occurred.
	 * @param   string   $code      - Code at that location (to be displayed below the description)
	 *
	 * @return    void
	 */
	public function addCompat($location, $text = null, $line = 0, $code = null)
	{
		$item = new stdClass;
		$item->location = $location;
		$item->line = $line;
		$item->text = $text;
		$item->code = $code;

		$this->addItem($item, 'compat');
	}

	/**
	 * Adds a warning issue to the report.
	 *
	 * @param   string   $location  - The location of the issue. Can be a path to a file or dir.
	 * @param   string   $text      - An optional description of the issue
	 * @param   integer  $line      - If $location is a file, you may specify the line where the
	 *                                   issue occurred.
	 * @param   string   $code      - Code at that location (to be displayed below the description)
	 *
	 * @return    void
	 */
	public function addWarning($location, $text = null, $line = 0, $code = null)
	{
		$item = new stdClass;
		$item->location = $location;
		$item->line = $line;
		$item->text = $text;
		$item->code = $code;

		$this->addItem($item, 'warning');
	}

	/**
	 * Formats the existing report data into HTML and returns it.
	 *
	 * @return    string    The HTML report data
	 */
	public function getHTML()
	{
		$html = array();

		if ($this->data['count']->total === 0)
		{
			// No errors or compatibility issues found
			$html[] = '<div class="alert alert-success">';
			$html[] = JText::_('COM_JEDCHECKER_EVERYTHING_SEEMS_TO_BE_FINE_WITH_THAT_RULE');
			$html[] = '</div>';
		}
		else
		{
			// Go through the error list
			if ($this->data['count']->errors > 0)
			{
				$html[] = $this->formatItems($this->data['errors'], 'danger');
			}
			// Go through the warning list
			if ($this->data['count']->warning > 0)
			{
				$html[] = $this->formatItems($this->data['warning'], 'warning');

			// Go through the compat list
			if ($this->data['count']->compat > 0)
			{
				$html[] = $this->formatItems($this->data['compat'], 'secondary');
			}

			// Go through the info list
			if ($this->data['count']->info > 0)
			{
				$html[] = $this->formatItems($this->data['info'], 'info');
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

	/**
	 * Converts an item to the string representation
	 *
	 * @param   array   $items       List or reports
	 * @param   string  $alertStyle  Type of alert blocks
	 *
	 * @return  string
	 */
	protected function formatItems($items, $alertStyle)
	{
		$html = array();

		foreach ($items as $i => $item)
		{
			$num = $i + 1;

			$html[] = '<div class="alert alert-' . $alertStyle . '">';

			// Add count number
			$html[] = '<strong>#' . str_pad($num, 3, '0', STR_PAD_LEFT) . '</strong> ';
			$html[] = $item->location;

			// Add line information if given
			if ($item->line > 0)
			{
				$html[] = ' ' . JText::_('COM_JEDCHECKER_IN_LINE') . ': <strong>' . $item->line . '</strong>';
			}

			$html[] = '<br />';

			// Add text if given
			if (!empty($item->text))
			{
				$html[] = '<small>' . $item->text;

				// Add code if given
				if (!empty($item->code))
				{
					$html[] = '<pre>' . htmlspecialchars(rtrim($item->code)) . '</pre>';
				}

				$html[] = '</small>';
			}

			$html[] = '</div>';
		}

		return implode('', $html);
	}
}
