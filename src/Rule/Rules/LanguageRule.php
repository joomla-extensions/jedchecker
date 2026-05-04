<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2021 - 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public Licence version 2 or later; see LICENCE.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Rule\Rules;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;
use Joomla\Component\Jedchecker\Administrator\Rule\AbstractRule;
use Joomla\Filesystem\Folder;

/**
 * LanguageRule validates language INI files.
 *
 * @since  3.0.0
 */
class LanguageRule extends AbstractRule
{
    /**
     * Rule ordering.
     *
     * @var integer
     * @since 3.0.0
     */
    public static int $ordering = 1100;
    /**
     * Rule ID.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $id = 'LANG';
    /**
     * Rule title.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $title = 'COM_JEDCHECKER_LANG';
    /**
     * Description of the rule.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $description = 'COM_JEDCHECKER_LANG_DESC';
    /**
     * List of language keys.
     * @var array
     * @since 3.0.0
     */
    protected array $langKeys = [];

    /**
     * check
     *
     * Runs the rule.
     *
     * @since  3.0.0
     */
    public function check(): void
    {
        $files = Folder::files($this->basedir, '\.ini$', true, true);

        foreach ($files as $file) {
            if (preg_match('#(?:^|/)([a-z]{2,3}-[A-Z]{2})(?:[./]\w+)?(?:\.sys)?\.ini$#', $file, $match)) {
                $tag = $match[1];
                $this->find($file, $tag);

                if ($tag === 'en-GB') {
                    $this->populateLangKeys($file);
                }
            }
        }

        $files = version_compare(JVERSION, '4.0', '>=')
                ? ['joomla.ini', 'lib_joomla.ini']
                : ['en-GB.ini', 'en-GB.lib_joomla.ini'];

        foreach ($files as $file) {
            $this->populateLangKeys(JPATH_ROOT . '/language/en-GB/' . $file);
            $this->populateLangKeys(JPATH_ADMINISTRATOR . '/language/en-GB/' . $file);
        }

        $files = Folder::files($this->basedir, '\.php$', true, true);

        foreach ($files as $file) {
            $this->findJText($file);
        }
    }
    /**
     * find
     *
     * Finds and processes a manifest file.
     *
     * @param   string  $file
     * @param   string  $tag
     *
     * @return bool
     *
     * @since  3.0.0
     */
    protected function find(string $file, string $tag): bool
    {
        $content = file_get_contents($file);

        if ($content === false) {
            return false;
        }

        if (strpos($content, "\r") !== false) {
            $this->report->addNotice($file, Text::_('COM_JEDCHECKER_LANG_INCORRECT_EOL', false, false));
        }

        $lines = file($file);

        if ($lines === false) {
            return false;
        }

        $nLines = count($lines);
        $keys = [];
        $mbExists = function_exists('mb_check_encoding');

        for ($lineno = 0; $lineno < $nLines; $lineno++) {
            $startLineno = $lineno + 1;
            $line        = trim($lines[$lineno]);

            if ($lineno === 0 && strncmp($line, "\xEF\xBB\xBF", 3) === 0) {
                $this->report->addWarning($file, Text::_('COM_JEDCHECKER_LANG_BOM_FOUND'), $startLineno);
                $line = substr($line, 3);
            }

            if ($line === '' || $line[0] === ';' || $line[0] === '[') {
                continue;
            }

            if ($line[0] === '#') {
                $this->report->addError($file, Text::_('COM_JEDCHECKER_LANG_INCORRECT_COMMENT'), $startLineno, $line);
                continue;
            }

            if (strpos($line, '=') === false) {
                $this->report->addError($file, Text::_('COM_JEDCHECKER_LANG_WRONG_LINE'), $startLineno, $line);
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = rtrim($key);

            if ($key === '') {
                $this->report->addError($file, Text::_('COM_JEDCHECKER_LANG_KEY_EMPTY'), $startLineno, $line);
                continue;
            }

            if (preg_match('/\s/', $key)) {
                $this->report->addError($file, Text::_('COM_JEDCHECKER_LANG_KEY_WHITESPACE'), $startLineno, $line);
                continue;
            }

            if (strpbrk($key, '{}|&~![()^"') !== false) {
                $this->report->addError(
                    $file,
                    Text::_('COM_JEDCHECKER_LANG_KEY_INVALID_CHARACTER'),
                    $startLineno,
                    $line
                );
                continue;
            }

            if (in_array($key, ['null', 'yes', 'no', 'true', 'false', 'on', 'off', 'none'], true)) {
                $this->report->addError($file, Text::_('COM_JEDCHECKER_LANG_KEY_RESERVED'), $startLineno, $line);
                continue;
            }

            if (preg_match('/[\x00-\x1F\x80-\xFF]/', $key)) {
                $this->report->addWarning($file, Text::_('COM_JEDCHECKER_LANG_KEY_NOT_ASCII'), $startLineno, $line);
            }

            if ($key !== strtoupper($key)) {
                $this->report->addWarning($file, Text::_('COM_JEDCHECKER_LANG_KEY_NOT_UPPERCASE'), $startLineno, $line);
            }

            if (isset($keys[$key])) {
                $this->report->addWarning(
                    $file,
                    Text::sprintf('COM_JEDCHECKER_LANG_KEY_DUPLICATED', $keys[$key]),
                    $startLineno,
                    $line
                );
            } else {
                $keys[$key] = $startLineno;
            }

            $value = ltrim($value);

            if (! preg_match('/^"((?>[^"\\\\]+|\\\\.)*)"\s*(;[^"]*)?$/', $value, $matches)) {
                $this->report->addError($file, Text::_('COM_JEDCHECKER_LANG_TRANSLATION_ERROR'), $startLineno, $line);
                continue;
            }

            $value = $matches[1];

            if ($value === '') {
                $this->report->addNotice($file, Text::_('COM_JEDCHECKER_LANG_TRANSLATION_EMPTY'), $startLineno, $line);
                continue;
            }

            $validUTF8 = $mbExists ? mb_check_encoding($value, 'UTF-8') : preg_match('//u', $value);

            if (! $validUTF8) {
                $this->report->addWarning($file, Text::_('COM_JEDCHECKER_LANG_INVALID_UTF8'), $startLineno, $line);
            }

            if (preg_match('/\\\\[\\\\\\$]/', $value)) {
                $this->report->addWarning($file, Text::_('COM_JEDCHECKER_LANG_JOOMLA501_BC'), $startLineno, $line);
            }

            $count = preg_match_all('/(?<=^|[^%])%(\d+)\w/', $value, $matches, PREG_SET_ORDER);

            if ($count) {
                $maxNumber = 0;

                foreach ($matches as $match) {
                    $maxNumber = max($maxNumber, (int)$match[1]);
                }

                if ($maxNumber === $count) {
                    $this->report->addWarning(
                        $file,
                        Text::_('COM_JEDCHECKER_LANG_INCORRECT_ARGNUM'),
                        $startLineno,
                        $line
                    );
                }
            }

            if ($tag === 'en-GB') {
                if (preg_match('/^\s|[^:]\s+$/', $value)) {
                    $this->report->addNotice($file, Text::_('COM_JEDCHECKER_LANG_SPACES_AROUND'), $startLineno, $line);
                }
            }
        }

        return true;
    }

    /**
     * populateLangKeys
     *
     * Populates the language keys array with language keys from a given file.
     *
     * @param   string  $file
     *
     * @since  3.0.0
     */
    protected function populateLangKeys(string $file): void
    {
        if (is_file($file)) {
            $data = @parse_ini_file($file);

            if (is_array($data)) {
                $this->langKeys = array_replace($this->langKeys, $data);
            }
        }
    }

    /**
     * findJText
     *
     * Finds and processes JText usage in a given file.
     *
     * @param   string  $file
     *
     * @return bool
     *
     * @since  3.0.0
     */
    protected function findJText(string $file): bool
    {
        $content = file_get_contents($file);

        if (
            ! preg_match_all(
                '/\bJ?Text::(?:_|s?printf|alt|plural|script)\s*\(\s*([\'])([^\'"]+)\1\s*[\),]/',
                $content,
                $matches,
                PREG_OFFSET_CAPTURE
            )
        ) {
            return true;
        }

        $lines = explode("\n", $content);

        foreach ($matches[2] as $match) {
            $key = strtoupper($match[0]);

            if (! isset($this->langKeys[$key])) {
                $lineno = substr_count($content, "\n", 0, $match[1]);
                $this->report->addNotice(
                    $file,
                    Text::sprintf('COM_JEDCHECKER_LANG_UNKNOWN_KEY_IN_CODE', htmlspecialchars($key)),
                    $lineno + 1,
                    $lines[$lineno]
                );
            }
        }

        return true;
    }
}
