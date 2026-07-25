<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 *              eaxs <support@projectfork.net>
 * @copyright  Copyright (C) 2017 - 2026 Open Source Matters, Inc. All rights reserved.
 *             Copyright (C) 2008 - 2016 compjoom.com All rights reserved.
 *
 * @license    GNU General Public Licence version 2 or later; see LICENCE.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Report;

use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Report collects JEDChecker rule results and renders them as HTML or structured data.
 *
 * @since  3.0.0
 */
class Report
{
    public const LEVEL_ERROR   = 'error';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_COMPAT  = 'compatibility';
    public const LEVEL_NOTICE  = 'notice';
    public const LEVEL_INFO    = 'info';
    public const LEVEL_PASSED  = 'passed';

    /**
     * Report data.
     *
     * @var array
     * @since 3.0.0
     */
    protected array $data;

    /**
     * Base directory of the JEDChecker installation.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $basedir;

    /**
     * Default subtype for issues.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $defaultSubtype = '';

    /**
     * Bootstrap style for each issue level.
     *
     * @var array|string[]
     * @since 3.0.0
     */
    protected array $issueBootstrapStyles = [
            self::LEVEL_ERROR   => 'danger',
            self::LEVEL_WARNING => 'warning',
            self::LEVEL_COMPAT  => 'secondary',
            self::LEVEL_NOTICE  => 'info',
            self::LEVEL_INFO    => 'info',
            self::LEVEL_PASSED  => 'info',
    ];

    /**
     * Language titles for each issue level.
     *
     * @var array
     * @since 3.0.0
     */
    protected array $issueLangTitles;

    /**
     * addCompat
     *
     * Adds a compatibility issue to the report.
     *
     * @param   string       $location
     * @param   string|null  $text
     * @param   int|null     $line
     * @param   string|null  $code
     *
     * @since  3.0.0
     */
    public function addCompat(string $location, ?string $text = null, ?int $line = null, ?string $code = null): void
    {
        $this->addIssue(self::LEVEL_COMPAT, $this->defaultSubtype, $location, $text, $line, $code);
    }

    /**
     * addError
     *
     * Adds an error issue to the report.
     *
     * @param   string       $location
     * @param   string|null  $text
     * @param   int|null     $line
     * @param   string|null  $code
     *
     * @since  3.0.0
     */
    public function addError(string $location, ?string $text = null, ?int $line = null, ?string $code = null): void
    {
        $this->addIssue(self::LEVEL_ERROR, $this->defaultSubtype, $location, $text, $line, $code);
    }

    /**
     * addIssue
     *
     * Adds an issue to the report.
     *
     * @param   string       $type
     * @param   string       $subtype
     * @param   string       $location
     * @param   string|null  $text
     * @param   int|null     $line
     * @param   string|null  $code
     *
     * @since  3.0.0
     */
    public function addIssue(
        string $type,
        string $subtype,
        string $location,
        ?string $text = null,
        ?int $line = null,
        ?string $code = null
    ): void {
        $item = new ReportItem($type, $subtype, $location, $text, $line, $code);
        $this->addItem($item, $type);
    }

    /**
     * addItem
     *
     * Adds an item to the report.
     *
     * @param   ReportItem  $item
     * @param   string      $type
     *
     * @since  3.0.0
     */
    protected function addItem(ReportItem $item, string $type): void
    {
        if (! empty($this->basedir)) {
            $item->location = str_replace($this->basedir, '', $item->location);

            if ($item->location === '') {
                $item->location = '/';
            }
        }

        $this->data[$type][] = $item;
        $this->data['count']->total++;
        $this->data['count']->$type++;
    }

    /**
     * addInfo
     *
     * Adds an informational issue to the report.
     *
     * @param   string       $location
     * @param   string|null  $text
     * @param   int|null     $line
     * @param   string|null  $code
     *
     * @since  3.0.0
     */
    public function addInfo(string $location, ?string $text = null, ?int $line = null, ?string $code = null): void
    {
        $this->addIssue(self::LEVEL_INFO, $this->defaultSubtype, $location, $text, $line, $code);
    }

    /**
     * addNotice
     *
     * Adds a notice issue to the report.
     *
     * @param   string       $location
     * @param   string|null  $text
     * @param   int|null     $line
     * @param   string|null  $code
     *
     * @since  3.0.0
     */
    public function addNotice(string $location, ?string $text = null, ?int $line = null, ?string $code = null): void
    {
        $this->addIssue(self::LEVEL_NOTICE, $this->defaultSubtype, $location, $text, $line, $code);
    }

    /**
     * addPassed
     *
     * Adds a passed issue to the report.
     *
     * @param   string       $location
     * @param   string|null  $text
     * @param   int|null     $line
     * @param   string|null  $code
     *
     * @since  3.0.0
     */
    public function addPassed(string $location, ?string $text = null, ?int $line = null, ?string $code = null): void
    {
        $this->addIssue(self::LEVEL_PASSED, $this->defaultSubtype, $location, $text, $line, $code);
    }

    /**
     * addWarning
     *
     * Adds a warning issue to the report.
     *
     * @param   string       $location
     * @param   string|null  $text
     * @param   int|null     $line
     * @param   string|null  $code
     *
     * @since  3.0.0
     */
    public function addWarning(string $location, ?string $text = null, ?int $line = null, ?string $code = null): void
    {
        $this->addIssue(self::LEVEL_WARNING, $this->defaultSubtype, $location, $text, $line, $code);
    }

    /**
     * getData
     *
     * Returns the report data in a structured format.
     *
     * @return array|array[]
     *
     * @since  3.0.0
     */
    public function getData(): array
    {
        $result = [
                'count'  => (array)$this->data['count'],
                'issues' => [],
        ];

        foreach ($this->issueBootstrapStyles as $type => $_dummy) {
            if ($this->data['count']->{$type} > 0) {
                foreach ($this->data[$type] as $item) {
                    $result['issues'][] = [
                            'type'     => $item->type,
                            'subtype'  => $item->subtype,
                            'location' => $item->location,
                            'text'     => $item->text,
                            'line'     => $item->line,
                            'code'     => $item->code,
                    ];
                }
            }
        }

        return $result;
    }

    /**
     * getHTML
     *
     * Returns the report data in HTML format.
     *
     * @return string
     *
     * @since  3.0.0
     */
    public function getHTML(): string
    {
        $html = [];

        if ($this->data['count']->total === 0) {
            $html[] = '<div class="alert alert-success">';
            $html[] = Text::_('COM_JEDCHECKER_EVERYTHING_SEEMS_TO_BE_FINE_WITH_THAT_RULE');
            $html[] = '</div>';
        } else {
            foreach ($this->issueBootstrapStyles as $type => $bsStyle) {
                if ($this->data['count']->{$type} > 0) {
                    $html[] = $this->formatItems($this->data[$type], $bsStyle, $this->issueLangTitles[$type]);
                }
            }
        }

        return implode('', $html);
    }

    /**
     * formatItems
     *
     * Formats a list of report items into HTML.
     *
     * @param   array   $items
     * @param   string  $alertStyle
     * @param   string  $alertName
     *
     * @return string
     *
     * @since  3.0.0
     */
    protected function formatItems(array $items, string $alertStyle, string $alertName): string
    {
        $html = [];

        foreach ($items as $i => $item) {
            $num   = $i + 1;
            $title = $alertName . (empty($item->subtype) ? '' : ': ' . $item->subtype);

            $html[] = '<div class="alert alert-' . $alertStyle . '" data-level="' . htmlspecialchars($title, ENT_QUOTES) . '">';
            $html[] = '<strong>#' . str_pad($num, 3, '0', STR_PAD_LEFT) . '</strong> ';
            $html[] = htmlspecialchars($item->location, ENT_QUOTES);

            if ($item->line !== null) {
                $html[] = ' ' . Text::_('COM_JEDCHECKER_IN_LINE') . ': <strong>' . $item->line . '</strong>';
            }

            $html[] = '<br />';

            if (! empty($item->text)) {
                $html[] = '<small>' . $item->text;

                if (! empty($item->code)) {
                    $html[] = '<pre>' . htmlspecialchars(rtrim($item->code)) . '</pre>';
                }

                $html[] = '</small>';
            }

            $html[] = '</div>';
        }

        return implode('', $html);
    }

    /**
     * setDefaultSubtype
     *
     * Sets the default subtype for issues.
     *
     * @param   string  $subtype
     *
     * @since  3.0.0
     */
    public function setDefaultSubtype(string $subtype): void
    {
        $this->defaultSubtype = $subtype;
    }

    /**
     * Constructor.
     *
     * @param $properties
     *
     * @since 3.0.0
     */
    public function __construct($properties = null)
    {
        if (empty($this->data)) {
            $this->reset();
        }
    }

    /**
     * reset
     *
     * Resets the report to its initial state.
     *
     * @since  3.0.0
     */
    public function reset(): void
    {
        $this->issueLangTitles = [
                self::LEVEL_ERROR   => Text::_('COM_JEDCHECKER_LEVEL_ERROR'),
                self::LEVEL_WARNING => Text::_('COM_JEDCHECKER_LEVEL_WARNING'),
                self::LEVEL_COMPAT  => Text::_('COM_JEDCHECKER_LEVEL_COMPATIBILITY'),
                self::LEVEL_NOTICE  => Text::_('COM_JEDCHECKER_LEVEL_NOTICE'),
                self::LEVEL_INFO    => Text::_('COM_JEDCHECKER_LEVEL_INFO'),
                self::LEVEL_PASSED  => Text::_('COM_JEDCHECKER_LEVEL_PASSED'),
        ];

        $this->data                 = [];
        $this->data['count']        = new \stdClass();
        $this->data['count']->total = 0;

        foreach ($this->issueLangTitles as $key => $_dummy) {
            $this->data[$key]          = [];
            $this->data['count']->$key = 0;
        }
    }
}
