<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2021 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Text;

// Include the rule base class
require_once JPATH_COMPONENT_ADMINISTRATOR . '/models/rule.php';


/**
 * class JedcheckerRulesLanguage
 *
 * This class validates language ini file
 *
 * @since  3.0
 */
class JedcheckerRulesLanguage extends JEDcheckerRule
{
	/**
	 * The formal ID of this rule. For example: SE1.
	 *
	 * @var    string
	 */
	protected $id = 'LANG';

	/**
	 * The title or caption of this rule.
	 *
	 * @var    string
	 */
	protected $title = 'COM_JEDCHECKER_LANG';

	/**
	 * The description of this rule.
	 *
	 * @var    string
	 */
	protected $description = 'COM_JEDCHECKER_LANG_DESC';

	/**
	 * The ordering value to sort rules in the menu.
	 *
	 * @var    integer
	 */
	public static $ordering = 1100;

	/**
	 * Key-value map for language translations
	 *
	 * @var    array
	 */
	protected $langKeys = array();

	/**
	 * Initiates the search and check
	 *
	 * @return    void
	 */
	public function check()
	{
		// Find all INI files of the extension
		$files = Folder::files($this->basedir, '\.ini$', true, true);

		// Iterate through all the ini files
		foreach ($files as $file)
		{
			/* Language file format is either tag.extension.ini or tag.extension.sys.ini
			   (where "tag" is a language code, e.g. en-GB, and "extension" is the extension element name, e.g. com_content)
			   Joomla!4 allows to skip tag prefix inside of the tag directory
			   (i.e. to name files as extension.ini and extension.sys.ini) */
			if (preg_match('#(?:^|/)([a-z]{2,3}-[A-Z]{2})(?:[./]\w+)?(?:\.sys)?\.ini$#', $file, $match))
			{
				$tag = $match[1];

				// Try to validate the file
				$this->find($file, $tag);

				if ($tag === 'en-GB')
				{
					$this->populateLangKeys($file);
				}
			}
		}

		// Load default Joomla's translations
		$files = version_compare(JVERSION, '4.0', '>=') ? array('joomla.ini', 'lib_joomla.ini') : array('en-GB.ini', 'en-GB.lib_joomla.ini');

		foreach ($files as $file)
		{
			$this->populateLangKeys(JPATH_ROOT . '/language/en-GB/' . $file);
			$this->populateLangKeys(JPATH_ADMINISTRATOR . '/language/en-GB/' . $file);
		}

		// Check JText usage
		$files = Folder::files($this->basedir, '\.php$', true, true);

		foreach ($files as $file)
		{
			$this->findJText($file);
		}
	}

	/**
	 * Reads and validates an ini file
	 *
	 * @param   string  $file  - The path to the file
	 * @param   string  $tag   - Language tag code
	 *
	 * @return boolean True on success, otherwise False.
	 */
	protected function find($file, $tag)
	{
		$content = file_get_contents($file);

		if ($content === false)
		{
			return false;
		}

		// Check EOL format is \n (not \r or \n\r)
		if (strpos($content, "\r") !== false)
		{
			$this->report->addNotice($file, Text::_('COM_JEDCHECKER_LANG_INCORRECT_EOL', false, false));
		}

		$lines = file($file);

		if ($lines === false)
		{
			return false;
		}

		$nLines = count($lines);
		$keys = array();

		// Use mb_check_encoding (if exists) to validate UTF-8
		$mbExists = function_exists('mb_check_encoding');

		for ($lineno = 0; $lineno < $nLines; $lineno++)
		{
			$startLineno = $lineno + 1;
			$line = trim($lines[$lineno]);

			// Check for BOM sequence
			if ($lineno === 0 && strncmp($line, "\xEF\xBB\xBF", 3) === 0)
			{
				$this->report->addWarning($file, Text::_('COM_JEDCHECKER_LANG_BOM_FOUND'), $startLineno);

				// Remove BOM for further checks
				$line = substr($line, 3);
			}

			// Skip empty lines, comments, and section names
			if ($line === '' || $line[0] === ';' || $line[0] === '[')
			{
				continue;
			}

			// Report incorrect comment character
			if ($line[0] === '#')
			{
				$this->report->addError($file, Text::_('COM_JEDCHECKER_LANG_INCORRECT_COMMENT'), $startLineno, $line);
				continue;
			}

			// Check for "=" character in the line
			if (strpos($line, '=') === false)
			{
				$this->report->addError($file, Text::_('COM_JEDCHECKER_LANG_WRONG_LINE'), $startLineno, $line);
				continue;
			}

			// Extract key and value
			list ($key, $value) = explode('=', $line, 2);

			// Validate key
			$key = rtrim($key);

			// Check for empty key
			if ($key === '')
			{
				$this->report->addError($file, Text::_('COM_JEDCHECKER_LANG_KEY_EMPTY'), $startLineno, $line);
				continue;
			}

			// Check for spaces in the key name
			if (preg_match('/\s/', $key))
			{
				$this->report->addError($file, Text::_('COM_JEDCHECKER_LANG_KEY_WHITESPACE'), $startLineno, $line);
				continue;
			}

			// Check for invalid characters (see https://www.php.net/manual/en/function.parse-ini-file.php)
			if (strpbrk($key, '{}|&~![()^"') !== false)
			{
				$this->report->addError($file, Text::_('COM_JEDCHECKER_LANG_KEY_INVALID_CHARACTER'), $startLineno, $line);
				continue;
			}

			// Check for invalid key names (see https://www.php.net/manual/en/function.parse-ini-file.php)
			if (in_array($key, array('null', 'yes', 'no', 'true', 'false', 'on', 'off', 'none'), true))
			{
				$this->report->addError($file, Text::_('COM_JEDCHECKER_LANG_KEY_RESERVED'), $startLineno, $line);
				continue;
			}

			// Check key contains ASCII characters only
			if (preg_match('/[\x00-\x1F\x80-\xFF]/', $key))
			{
				$this->report->addWarning($file, Text::_('COM_JEDCHECKER_LANG_KEY_NOT_ASCII'), $startLineno, $line);
			}

			// Check key is uppercase
			if ($key !== strtoupper($key))
			{
				$this->report->addWarning($file, Text::_('COM_JEDCHECKER_LANG_KEY_NOT_UPPERCASE'), $startLineno, $line);
			}

			// Check for duplicated keys
			if (isset($keys[$key]))
			{
				$this->report->addWarning($file, Text::sprintf('COM_JEDCHECKER_LANG_KEY_DUPLICATED', $keys[$key]), $startLineno, $line);
			}
			else
			{
				$keys[$key] = $startLineno;
			}

			// Validate value
			$value = ltrim($value);

			if (!preg_match('/^"((?>[^"\\\\]+|\\\\.)*)"\s*(;[^"]*)?$/', $value, $matches))
			{
				// The value doesn't match INI format
				$this->report->addError($file, Text::_('COM_JEDCHECKER_LANG_TRANSLATION_ERROR'), $startLineno, $line);
				continue;
			}

			// Get value w/o comment
			$value = $matches[1];

			// Check for empty value
			if ($value === '')
			{
				$this->report->addNotice($file, Text::_('COM_JEDCHECKER_LANG_TRANSLATION_EMPTY'), $startLineno, $line);
				continue;
			}

			// Check it's a valid UTF-8 string
			$validUTF8 = $mbExists ? mb_check_encoding($value, 'UTF-8') : preg_match('//u', $value);

			if (!$validUTF8)
			{
				$this->report->addWarning($file, Text::_('COM_JEDCHECKER_LANG_INVALID_UTF8'), $startLineno, $line);
			}

			// Process backwards compatibility break introduced in Joomla 5.0.1
			if (preg_match('/\\\\[\\\\\\$]/', $value))
			{
				$this->report->addWarning($file, Text::_('COM_JEDCHECKER_LANG_JOOMLA501_BC'), $startLineno, $line);
			}

			// The code below detects incorrect format of numbered placeholders (e.g. "%1s" instead of "%1$s")

			// Count numbered placeholders in the string (e.g. "%1s")
			$count = preg_match_all('/(?<=^|[^%])%(\d+)\w/', $value, $matches, PREG_SET_ORDER);

			if ($count)
			{
				// To avoid false-positives (e.g. %10s for a ten-characters-wide output string in a CLI),
				// we check that placeholder numbers form a sequence from 1 to N.

				$maxNumber = 0;

				foreach ($matches as $match)
				{
					$maxNumber = max($maxNumber, (int) $match[1]);
				}

				// If placeholder numbers form a sequence, the maximal value is equal to the number of elements
				if ($maxNumber === $count)
				{
					$this->report->addWarning($file, Text::_('COM_JEDCHECKER_LANG_INCORRECT_ARGNUM'), $startLineno, $line);
				}
			}

			// Some extra checks for en-GB only (to don't duplicate false-positives)
			if ($tag === 'en-GB')
			{
				// Check spaces around (but allow trailing space after colon)
				if (preg_match('/^\s|[^:]\s+$/', $value))
				{
					$this->report->addNotice($file, Text::_('COM_JEDCHECKER_LANG_SPACES_AROUND'), $startLineno, $line);
				}
			}
		}

		// All checks passed. Return true
		return true;
	}

	/**
	 * Appends keys from INI file to the list
	 *
	 * @param   string    $file  Language INI-file name
	 *
	 * @return  void
	 */
	protected function populateLangKeys($file)
	{
		if (is_file($file))
		{
			$data = @parse_ini_file($file);

			if (is_array($data))
			{
				$this->langKeys = array_replace($this->langKeys, $data);
			}
		}
	}

	/**
	 * Reads PHP files and checks JText arguments
	 *
	 * @param   string  $file  - The path to the file
	 *
	 * @return boolean True on success, otherwise False.
	 */
	protected function findJText($file)
	{
		$content = file_get_contents($file);

		// Search for Text/JText calls
		if (!preg_match_all('/\bJ?Text::(?:_|s?printf|alt|plural|script)\s*\(\s*([\'])([^\'"]+)\1\s*[\),]/', $content, $matches, PREG_OFFSET_CAPTURE))
		{
			return true;
		}

		$lines = explode("\n", $content);

		// Check all keys exist in INI files
		foreach ($matches[2] as $match)
		{
			$key = strtoupper($match[0]);

			if (!isset($this->langKeys[$key]))
			{
				$lineno = substr_count($content, "\n", 0, $match[1]);
				$this->report->addNotice($file, Text::sprintf('COM_JEDCHECKER_LANG_UNKNOWN_KEY_IN_CODE', htmlspecialchars($key)), $lineno + 1, $lines[$lineno]);
			}
		}

		return true;
	}
}
