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

namespace Joomla\Component\Jedchecker\Administrator\Rule\Rules;

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Language\Text;
use Joomla\Component\Jedchecker\Administrator\Helper\CheckerHelper;
use Joomla\Component\Jedchecker\Administrator\Rule\AbstractRule;
use Joomla\Filesystem\Folder;

/**
 * EncodingRule checks if base64 encoding is used in extension files.
 *
 * @since  3.0
 */
class EncodingRule extends AbstractRule
{
	protected string $id          = 'encoding';
	protected string $title       = 'COM_JEDCHECKER_RULE_ENCODING';
	protected string $description = 'COM_JEDCHECKER_RULE_ENCODING_DESC';

	public static int $ordering = 900;

	protected string $encodingsRegex;

	public function check(): void
	{
		$encodings = explode(',', $this->params->get('encodings'));

		foreach ($encodings as $i => $encoding)
		{
			$encodings[$i] = preg_quote(trim($encoding), '/');
		}

		$this->encodingsRegex = '/' . implode('|', $encodings) . '/i';

		$files = Folder::files($this->basedir, '\.php$', true, true);

		foreach ($files as $file)
		{
			$this->find($file);
		}
	}

	protected function find(string $file): bool
	{
		$content     = file_get_contents($file);
		$origContent = CheckerHelper::splitLines($content);

		$content = CheckerHelper::cleanPhpCode(
			$content,
			CheckerHelper::CLEAN_HTML | CheckerHelper::CLEAN_COMMENTS
		);
		$content = CheckerHelper::splitLines($content);

		$found = false;

		foreach ($content as $i => $line)
		{
			if (preg_match($this->encodingsRegex, $line))
			{
				$found = true;
				$this->report->addWarning($file, Text::_('COM_JEDCHECKER_ERROR_ENCODING'), $i + 1, $origContent[$i]);
			}
		}

		return $found;
	}
}
