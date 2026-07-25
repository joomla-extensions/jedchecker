<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2011 - 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public Licence version 2 or later; see LICENCE.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Report;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Represents an item in a report with various attributes to describe its type, location, and details.
 *
 * @since 3.0.0
 */
class ReportItem
{
    public string $type;

    public string $subtype;

    public string $location;

    public ?string $text;

    public ?int $line;

    public ?string $code;

    /**
     * Construct.
     *
     * @param   string       $type
     * @param   string       $subtype
     * @param   string       $location
     * @param   string|null  $text
     * @param   int|null     $line
     * @param   string|null  $code
     *
     * @since   3.0.0
     */
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
