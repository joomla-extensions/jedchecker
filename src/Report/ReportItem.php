<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2021 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public Licence version 2 or later; see LICENCE.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Report;

defined('_JEXEC') or die('Restricted access');

/**
 * ReportItem stores a single JEDChecker report entry.
 *
 * @since  3.0
 */
class ReportItem
{
	public string  $type;
	public string  $subtype;
	public string  $location;
	public ?string $text;
	public ?int    $line;
	public ?string $code;

	public function __construct(
		string $type,
		string $subtype,
		string $location,
		?string $text = null,
		?int $line = null,
		?string $code = null
	) {
		$this->type     = $type;
		$this->subtype  = $subtype;
		$this->location = $location;
		$this->text     = $text;
		$this->line     = $line;
		$this->code     = $code;
	}
}
