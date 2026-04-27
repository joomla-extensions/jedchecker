<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2025 Open Source Matters, Inc. All rights reserved.
 *             Copyright (C) 2008 - 2016 compojoom.com . All rights reserved.
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 *             eaxs <support@projectfork.net>
 *
 * @license    GNU General Public Licence version 2 or later; see LICENCE.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Report;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;

/**
 * Report collects JEDChecker rule results and renders them as HTML or structured data.
 *
 * @since  3.0
 */
class Report
{
	const LEVEL_ERROR   = 'error';
	const LEVEL_WARNING = 'warning';
	const LEVEL_COMPAT  = 'compatibility';
	const LEVEL_NOTICE  = 'notice';
	const LEVEL_INFO    = 'info';
	const LEVEL_PASSED  = 'passed';

	protected array  $data;
	protected string $basedir;
	protected string $defaultSubtype = '';

	protected array $issueBootstrapStyles = [
		self::LEVEL_ERROR   => 'danger',
		self::LEVEL_WARNING => 'warning',
		self::LEVEL_COMPAT  => 'secondary',
		self::LEVEL_NOTICE  => 'info',
		self::LEVEL_INFO    => 'info',
		self::LEVEL_PASSED  => 'info',
	];

	protected array $issueLangTitles;

	public function __construct($properties = null)
	{
		if (empty($this->data))
		{
			$this->reset();
		}
	}

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

		$this->data = [];
		$this->data['count'] = new \stdClass;
		$this->data['count']->total = 0;

		foreach ($this->issueLangTitles as $key => $_dummy)
		{
			$this->data[$key] = [];
			$this->data['count']->$key = 0;
		}
	}

	public function setDefaultSubtype(string $subtype): void
	{
		$this->defaultSubtype = $subtype;
	}

	public function addError(string $location, ?string $text = null, ?int $line = null, ?string $code = null): void
	{
		$this->addIssue(self::LEVEL_ERROR, $this->defaultSubtype, $location, $text, $line, $code);
	}

	public function addWarning(string $location, ?string $text = null, ?int $line = null, ?string $code = null): void
	{
		$this->addIssue(self::LEVEL_WARNING, $this->defaultSubtype, $location, $text, $line, $code);
	}

	public function addCompat(string $location, ?string $text = null, ?int $line = null, ?string $code = null): void
	{
		$this->addIssue(self::LEVEL_COMPAT, $this->defaultSubtype, $location, $text, $line, $code);
	}

	public function addNotice(string $location, ?string $text = null, ?int $line = null, ?string $code = null): void
	{
		$this->addIssue(self::LEVEL_NOTICE, $this->defaultSubtype, $location, $text, $line, $code);
	}

	public function addInfo(string $location, ?string $text = null, ?int $line = null, ?string $code = null): void
	{
		$this->addIssue(self::LEVEL_INFO, $this->defaultSubtype, $location, $text, $line, $code);
	}

	public function addPassed(string $location, ?string $text = null, ?int $line = null, ?string $code = null): void
	{
		$this->addIssue(self::LEVEL_PASSED, $this->defaultSubtype, $location, $text, $line, $code);
	}

	public function addIssue(string $type, string $subtype, string $location, ?string $text = null, ?int $line = null, ?string $code = null): void
	{
		$item = new ReportItem($type, $subtype, $location, $text, $line, $code);
		$this->addItem($item, $type);
	}

	public function getData(): array
	{
		$result = [
			'count'  => (array) $this->data['count'],
			'issues' => [],
		];

		foreach ($this->issueBootstrapStyles as $type => $_dummy)
		{
			if ($this->data['count']->{$type} > 0)
			{
				foreach ($this->data[$type] as $item)
				{
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

	public function getHTML(): string
	{
		$html = [];

		if ($this->data['count']->total === 0)
		{
			$html[] = '<div class="alert alert-success">';
			$html[] = Text::_('COM_JEDCHECKER_EVERYTHING_SEEMS_TO_BE_FINE_WITH_THAT_RULE');
			$html[] = '</div>';
		}
		else
		{
			foreach ($this->issueBootstrapStyles as $type => $bsStyle)
			{
				if ($this->data['count']->{$type} > 0)
				{
					$html[] = $this->formatItems($this->data[$type], $bsStyle, $this->issueLangTitles[$type]);
				}
			}
		}

		return implode('', $html);
	}

	protected function addItem(ReportItem $item, string $type): void
	{
		if (!empty($this->basedir))
		{
			$item->location = str_replace($this->basedir, '', $item->location);

			if ($item->location === '')
			{
				$item->location = '/';
			}
		}

		$this->data[$type][] = $item;
		$this->data['count']->total++;
		$this->data['count']->$type++;
	}

	protected function formatItems(array $items, string $alertStyle, string $alertName): string
	{
		$html = [];

		foreach ($items as $i => $item)
		{
			$num   = $i + 1;
			$title = $alertName . (empty($item->subtype) ? '' : ': ' . $item->subtype);

			$html[] = '<div class="alert alert-' . $alertStyle . '" data-level="' . $title . '">';
			$html[] = '<strong>#' . str_pad($num, 3, '0', STR_PAD_LEFT) . '</strong> ';
			$html[] = $item->location;

			if ($item->line !== null)
			{
				$html[] = ' ' . Text::_('COM_JEDCHECKER_IN_LINE') . ': <strong>' . $item->line . '</strong>';
			}

			$html[] = '<br />';

			if (!empty($item->text))
			{
				$html[] = '<small>' . $item->text;

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
