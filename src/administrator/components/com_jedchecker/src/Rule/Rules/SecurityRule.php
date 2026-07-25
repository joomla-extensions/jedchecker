<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2026 Open Source Matters, Inc. All rights reserved.
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
 * SecurityRule checks for obfuscated loaders, executable files, and badly named files.
 *
 * @since  3.0.0
 */
class SecurityRule extends AbstractRule
{
    /**
     * Rule ordering.
     *
     * @var integer
     * @since 3.0.0
     */
    public static int $ordering = 750;
    /**
     * Rule ID.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $id = 'Security';
    /**
     * Rule title.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $title = 'COM_JEDCHECKER_RULE_SECURITY';
    /**
     * Description of the rule.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $description = 'COM_JEDCHECKER_RULE_SECURITY_DESC';
    /**
     * Regex for matching obfuscated code.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $obfuscatedRegex = '';
    /**
     * Extensions that are considered executable.
     *
     * @var array
     * @since 3.0.0
     */
    protected array $executableExts = [];
    /**
     * Extensions that are considered shell scripts.
     *
     * @var array
     * @since 3.0.0
     */
    protected array $shellExts = [];
    /**
     * Characters that are not allowed in filenames.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $badChars = '';

    /**
     * check
     *
     * Runs the rule.
     *
     * @since  3.0.0
     */
    public function check(): void
    {
        $this->executableExts = array_map('trim', explode(',', $this->params->get('executable_extensions', '')));
        $this->shellExts      = array_map('trim', explode(',', $this->params->get('shell_extensions', '')));
        $this->badChars       = $this->params->get('bad_filename_chars', '');

        $this->buildObfuscatedRegex();

        $files = Folder::files($this->basedir, ' . ', true, true);

        foreach ($files as $file) {
            $this->checkBadFilename($file);
            $this->checkExecutableFile($file);

            if (preg_match('/\.php$/i', $file)) {
                $this->checkObfuscatedCode($file);
            }
        }
    }

    /**
     * buildObfuscatedRegex
     *
     * Builds a regular expression pattern based on obfuscated code patterns.
     *
     *
     * @since  3.0.0
     */
    protected function buildObfuscatedRegex(): void
    {
        $patterns   = explode(',', $this->params->get('obfuscated_patterns', ''));
        $regexParts = [];

        foreach ($patterns as $pattern) {
            $pattern = trim($pattern);

            if (empty($pattern)) {
                continue;
            }

            $regexParts[] = $this->generateRegex($pattern);
        }

        if (! empty($regexParts)) {
            $this->obfuscatedRegex = '/(?:' . implode('|', $regexParts) . ')/';
        }
    }

    /**
     * checkBadFilename
     *
     * Checks for bad characters in filenames.
     *
     * @param   string  $file
     *
     * @since  3.0.0
     */
    protected function checkBadFilename(string $file): void
    {
        if (empty($this->badChars)) {
            return;
        }

        $filename = basename($file);
        $chars    = strpbrk($filename, $this->badChars);

        if ($chars !== false) {
            $char        = $chars[0];
            $displayChar = ($char === ' ') ? 'space' : $char;
            $this->report->addNotice(
                $file,
                Text::sprintf('COM_JEDCHECKER_ERROR_SECURITY_BAD_FILENAME_CHAR', $displayChar)
            );
        }
    }

    /**
     * checkExecutableFile
     *
     * Checks for executable files and shell scripts.
     *
     * @param   string  $file
     *
     * @since  3.0.0
     */
    protected function checkExecutableFile(string $file): void
    {
        $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

        if (\in_array($extension, $this->executableExts)) {
            $this->report->addWarning($file, Text::_('COM_JEDCHECKER_ERROR_SECURITY_EXECUTABLE_FILE'));

            return;
        }

        if (\in_array($extension, $this->shellExts)) {
            $this->report->addWarning($file, Text::_('COM_JEDCHECKER_ERROR_SECURITY_SHELL_SCRIPT'));

            return;
        }

        $this->checkShebang($file);
    }

    /**
     * checkObfuscatedCode
     *
     * Checks for obfuscated code patterns in files.
     *
     * @param   string  $file
     *
     * @since  3.0.0
     */
    protected function checkObfuscatedCode(string $file): void
    {
        if (empty($this->obfuscatedRegex)) {
            return;
        }

        $content = file_get_contents($file);

        if (empty($content)) {
            return;
        }

        if (preg_match($this->obfuscatedRegex, $content, $matches)) {
            $this->report->addError($file, Text::sprintf('COM_JEDCHECKER_ERROR_SECURITY_OBFUSCATED_CODE', $matches[0]));
        }
    }

    /**
     * generateRegex
     *
     * Generates a regular expression pattern from a given pattern.
     *
     * @param   string  $pattern
     *
     * @return string
     *
     * @since  3.0.0
     */
    protected function generateRegex(string $pattern): string
    {
        $regex = preg_quote($pattern, '/');

        if (preg_match('/\w/', $pattern[0])) {
            $regex = '\b' . $regex;
        }

        if (preg_match('/\w/', $pattern[\strlen($pattern) - 1])) {
            $regex .= '\b';
        }

        return $regex;
    }

    /**
     * checkShebang
     *
     * Looks for #! in the first two bytes of a file.
     *
     * @param   string  $file
     *
     * @since  3.0.0
     */
    protected function checkShebang(string $file): void
    {
        $handle = @fopen($file, 'r');

        if ($handle === false) {
            return;
        }

        $firstBytes = fread($handle, 2);
        fclose($handle);

        if ($firstBytes === '#!') {
            $this->report->addWarning($file, Text::_('COM_JEDCHECKER_ERROR_SECURITY_SHEBANG_FILE'));
        }
    }
}
