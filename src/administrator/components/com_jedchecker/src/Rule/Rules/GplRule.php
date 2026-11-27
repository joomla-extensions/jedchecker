<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 * @copyright  Copyright (C) 2017 - 2026 Open Source Matters, Inc. All rights reserved.
 *             Copyright (C) 2008 - 2016 compjoom.com All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see License.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Rule\Rules;

use Joomla\CMS\Language\Text;
use Joomla\Component\Jedchecker\Administrator\Helper\CheckerHelper;
use Joomla\Component\Jedchecker\Administrator\Rule\AbstractRule;
use Joomla\Filesystem\Folder;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * GplRule searches all files for GPL/compatible Licenses.
 *
 * @since  3.0.0
 */
class GplRule extends AbstractRule
{
    /**
     * Rule ordering.
     *
     * @var integer
     * @since 3.0.0
     */
    public static int $ordering = 500;
    /**
     * Rule ID.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $id = 'PH1';
    /**
     * Rule title.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $title = 'COM_JEDCHECKER_RULE_PH1';
    /**
     * Description of the rule.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $description = 'COM_JEDCHECKER_RULE_PH1_DESC';
    /**
     * Regex for matching obfuscated code.
     *
     * @var string|null
     * @since 3.0.0
     */
    protected ?string $regexGPLLicenses;

    /**
     * Regex for matching obfuscated code.
     *
     * @var string|null
     * @since 3.0.0
     */
    protected ?string $regexCompatLicenses;

    /**
     * check
     *
     * Runs the rule.
     *
     * @since  3.0.0
     */
    public function check(): void
    {
        $this->report->setDefaultSubtype($this->id);
        $this->init();

        $files = CheckerHelper::filterExcludedPaths(Folder::files($this->basedir, '\.php$', true, true));

        foreach ($files as $file) {
            if (! $this->find($file)) {
                $this->report->addError($file, Text::_('COM_JEDCHECKER_ERROR_GPL_NOT_FOUND'));
            }
        }
    }

    /**
     * init
     *
     * Initializes the rule with necessary data and regex patterns.
     *
     *
     * @since  3.0.0
     */
    protected function init(): void
    {
        $dataDir = __DIR__ . '/data/gpl/';

        $GPLLicenses            = (array)file($dataDir . 'gnu.txt');
        $this->regexGPLLicenses = $this->generateRegexp($GPLLicenses);

        $compatLicenses = (array)file($dataDir . 'compat.txt');
        $extraLicenses  = explode(',', $this->params->get('constants', ''));
        $compatLicenses = array_merge($compatLicenses, $extraLicenses);

        $this->regexCompatLicenses = $this->generateRegexp($compatLicenses);
    }

    /**
     * find
     *
     * Searches for GPL and compatible licenses in the given file content.
     *
     * @param   string  $file
     *
     * @return bool
     *
     * @since  3.0.0
     */
    protected function find(string $file): bool
    {
        $content = php_strip_whitespace($file);

        if (empty($content) || preg_match('#^<\?php\s+(?:$|(?:die|exit)(?:\(\))?;)#', $content)) {
            return true;
        }

        $content = file_get_contents($file);
        // Strip the leading "*" of docblock lines. Not \s: it would also remove line breaks before
        // the "*" and make the reported line number too low.
        $content = preg_replace('/^[ \t]*\*/m', '', $content);

        if (preg_match($this->regexGPLLicenses, $content, $match, PREG_OFFSET_CAPTURE)) {
            $lineno = substr_count($content, "\n", 0, $match[0][1]) + 1;
            $this->report->addPassed($file, Text::_('COM_JEDCHECKER_PH1_License_FOUND'), $lineno, $match[0][0]);

            return true;
        }

        if (preg_match($this->regexCompatLicenses, $content, $match, PREG_OFFSET_CAPTURE)) {
            $lineno = substr_count($content, "\n", 0, $match[0][1]) + 1;
            $this->report->addWarning(
                $file,
                Text::_('COM_JEDCHECKER_GPL_COMPATIBLE_License_WAS_FOUND'),
                $lineno,
                $match[0][0]
            );

            return true;
        }

        return false;
    }

    /**
     * generateRegexp
     *
     * Generates a regular expression pattern based on provided lines of text.
     *
     * @param   array  $lines
     *
     * @return string|null
     *
     * @since  3.0.0
     */
    protected function generateRegexp(array $lines): ?string
    {
        $titles = [];
        $ids    = [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $title = $line;

            if (substr($line, -1, 1) === ')') {
                $pos = strrpos($line, '(');

                if ($pos !== false) {
                    $title = trim(substr($line, 0, $pos));
                    $id    = trim(substr($line, $pos + 1, -1));

                    if ($id !== '') {
                        $id       = preg_quote($id, '#');
                        $ids[$id] = 1;
                    }
                }
            }

            if ($title !== '') {
                $title = preg_quote($title, '#');
                $title = preg_replace('/(?<=\S)\s+v(?=\d)/', ',?\s+(?:v\.?\s*|version\s+)?', $title);
                $title = preg_replace('/\s+/', '\s+', $title);
                // Accept both the US ("License") and the British/Commonwealth ("Licence") spelling.
                $title = preg_replace('/\bLicen[cs]e\b/i', 'Licen[cs]e', $title);

                $titles[$title] = 1;
            }
        }

        if (\count($titles) === 0) {
            return null;
        }

        $titles = implode('|', array_keys($titles));

        if (\count($ids)) {
            $ids    = implode('|', array_keys($ids));
            // The regex is case-insensitive, so one set of alternatives covers "License"/"license";
            // Licen[cs]e additionally accepts the British/Commonwealth spelling "Licence".
            $titles .=
                    '|\bLicen[cs]e\b.+?(?:' . $ids . ')' .
                    '|\b(?:' . $ids . ')\s+Licen[cs]e\b';
        }

        return '#^.*?(?:' . $titles . ').*?$#im';
    }
}
