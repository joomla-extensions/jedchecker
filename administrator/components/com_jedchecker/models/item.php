<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2021 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');

/**
 * Class JEDcheckerReportItem
 *
 * This is a data class to store JEDChecker report item.
 *
 * @since  2.4.1
 */

class JEDCheckerReportItem
{
	/** @var string */
	public $type;

	/** @var string */
	public $subtype;

	/** @var string */
	public $location;

	/** @var ?string */
	public $text;

	/** @var ?int */
	public $line;

	/** @var ?string */
	public $code;

	/**
	 * Constructor. Initialises data.
	 *
	 * @param string $type
	 * @param string $subtype
	 * @param string $text
	 * @param ?string $location
	 * @param ?int $line
	 * @param ?string $code
	 */
	public function __construct($type, $subtype, $location, $text = null, $line = null, $code = null)
	{
		$this->type = $type;
		$this->subtype = $subtype;
		$this->text = $text;
		$this->location = $location;
		$this->line = $line;
		$this->code = $code;
	}
}
