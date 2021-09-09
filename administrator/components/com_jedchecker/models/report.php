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
	 * Rule's issue levels
	 * @since 2.4.1
	 */
	const LEVEL_ERROR   = 'error';
	const LEVEL_WARNING = 'warning';
	const LEVEL_COMPAT  = 'compatibility';
	const LEVEL_NOTICE  = 'notice';
	const LEVEL_INFO    = 'info';
	const LEVEL_PASSED  = 'passed';

	/**
	 * Contains the report data.
	 *
	 * @var    array
	 * @see    reset
	 */
	protected $data;

	/**
	 * The absolute path to the target extension.
	 *
	 * @var    string
	 */
	protected $basedir;

	/**
	 * Default rule subtype (e.g. PH1)
	 *
	 * @var string
	 * @since 2.4.1
	 */
	protected $defaultSubtype = '';

	/**
	 * Bootstrap5 styles for issue levels
	 *
	 * @var string[]
	 * @since 2.4.1
	 */
	protected $issueBootstrapStyles = array(
		self::LEVEL_ERROR   => 'danger',
		self::LEVEL_WARNING => 'warning',
		self::LEVEL_COMPAT  => 'secondary',
		self::LEVEL_NOTICE  => 'info',
		self::LEVEL_INFO    => 'info',
		self::LEVEL_PASSED  => 'info',
	);

	/**
	 * Translations for issue levels.
	 *
	 * @var string[]
	 * @see reset
	 * @since 2.4.1
	 */
	protected $issueLangTitles;

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
		// Initialize language strings
		$this->issueLangTitles = array(
			self::LEVEL_ERROR   => JText::_('COM_JEDCHECKER_LEVEL_ERROR'),
			self::LEVEL_WARNING => JText::_('COM_JEDCHECKER_LEVEL_WARNING'),
			self::LEVEL_COMPAT  => JText::_('COM_JEDCHECKER_LEVEL_COMPATIBILITY'),
			self::LEVEL_NOTICE  => JText::_('COM_JEDCHECKER_LEVEL_NOTICE'),
			self::LEVEL_INFO    => JText::_('COM_JEDCHECKER_LEVEL_INFO'),
			self::LEVEL_PASSED  => JText::_('COM_JEDCHECKER_LEVEL_PASSED'),
		);

		$this->data = array();

		$this->data['count'] = new stdClass;
		$this->data['count']->total = 0;

		foreach ($this->issueLangTitles as $key => $_dummy)
		{
			$this->data[$key] = array();
			$this->data['count']->$key = 0;
		}
	}

	/**
	 * Sets default rule's subtype (e.g. PH1)
	 *
	 * @param   string $subtype
	 *
	 * @since 2.4.1
	 */
	public function setDefaultSubtype($subtype)
	{
		$this->defaultSubtype = $subtype;
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
	public function addError($location, $text = null, $line = null, $code = null)
	{
		$this->addIssue(self::LEVEL_ERROR, $this->defaultSubtype, $location, $text, $line, $code);
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
	public function addWarning($location, $text = null, $line = null, $code = null)
	{
		$this->addIssue(self::LEVEL_WARNING, $this->defaultSubtype, $location, $text, $line, $code);
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
	public function addCompat($location, $text = null, $line = null, $code = null)
	{
		$this->addIssue(self::LEVEL_COMPAT, $this->defaultSubtype, $location, $text, $line, $code);
	}

	/**
	 * Adds a notice to the report.
	 *
	 * @param   string   $location  - The location of the error. Can be a path to a file or dir.
	 * @param   string   $text      - An optional description of the error.
	 * @param   integer  $line      - If $location is a file, you may specify the line where the
	 *                                   error occurred.
	 * @param   string   $code      - Code at that location (to be displayed below the description)
	 *
	 * @return    void
	 */
	public function addNotice($location, $text = null, $line = null, $code = null)
	{
		$this->addIssue(self::LEVEL_NOTICE, $this->defaultSubtype, $location, $text, $line, $code);
	}

	/**
	 * Adds an info message to the report.
	 *
	 * @param   string   $location  - The location of the error. Can be a path to a file or dir.
	 * @param   string   $text      - An optional description of the error.
	 * @param   integer  $line      - If $location is a file, you may specify the line where the
	 *                                   error occurred.
	 * @param   string   $code      - Code at that location (to be displayed below the description)
	 *
	 * @return    void
	 */
	public function addInfo($location, $text = null, $line = null, $code = null)
	{
		$this->addIssue(self::LEVEL_INFO, $this->defaultSubtype, $location, $text, $line, $code);
	}

	/**
	 * Adds a "passed" message to the report.
	 *
	 * @param   string   $location  - The location of the error. Can be a path to a file or dir.
	 * @param   string   $text      - An optional description of the error.
	 * @param   integer  $line      - If $location is a file, you may specify the line where the
	 *                                   error occurred.
	 * @param   string   $code      - Code at that location (to be displayed below the description)
	 *
	 * @return    void
	 */
	public function addPassed($location, $text = null, $line = null, $code = null)
	{
		$this->addIssue(self::LEVEL_PASSED, $this->defaultSubtype, $location, $text, $line, $code);
	}

	/**
	 * Add an issue to the report
	 *
	 * @param   string  $type     Issue type (see LEVEL_* constants)
	 * @param   string  $subtype  Issue subtype (e.g. PH1)
	 * @param   string  $location The location of the issue. Can be a path to a file or dir.
	 * @param   ?string $text     An optional description of the issue
	 * @param   ?int    $line     If $location is a file, you may specify the line where the issue occurred
	 * @param   ?string $code     Code at that location (to be displayed below the description)
	 *
	 * @return    void
	 *
	 * @since 2.4.1
	 */
	public function addIssue($type, $subtype, $location, $text = null, $line = null, $code = null)
	{
		$item = new JEDCheckerReportItem($type, $subtype, $location, $text, $line, $code);

		$this->addItem($item, $type);
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
			foreach ($this->issueBootstrapStyles as $type => $bsStyle)
			{
				// Go through the error list
				if ($this->data['count']->{$type} > 0)
				{
					$html[] = $this->formatItems($this->data[$type], $bsStyle, $this->issueLangTitles[$type]);
				}
			}
		}

		return implode('', $html);
	}

	/**
	 * Adds an item to the report data
	 *
	 * @param   JEDCheckerReportItem $item  - The item to add.
	 * @param   string               $type  - The item type (see LEVEL_* constants).
	 *
	 * @return    void
	 */
	protected function addItem($item, $type)
	{
		// Remove the base dir from the location
		if (!empty($this->basedir))
		{
			$item->location = str_replace($this->basedir, '', $item->location);

			if ($item->location === '')
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
	 * @param   JEDCheckerReportItem[] $items       List or reports
	 * @param   string                 $alertStyle  Style of alert blocks
	 * @param   string                 $alertName   Type of alert blocks
	 *
	 * @return  string
	 */
	protected function formatItems($items, $alertStyle, $alertName)
	{
		$html = array();

		foreach ($items as $i => $item)
		{
			$num = $i + 1;

			$title = $alertName . (empty($item->subtype) ? '' : ': ' . $item->subtype);

			$html[] = '<div class="alert alert-' . $alertStyle . '" data-level="' . $title . '">';

			// Add count number
			$html[] = '<strong>#' . str_pad($num, 3, '0', STR_PAD_LEFT) . '</strong> ';
			$html[] = $item->location;

			// Add line information if given
			if ($item->line !== null)
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
