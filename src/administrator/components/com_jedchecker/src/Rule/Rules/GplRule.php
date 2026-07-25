<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 * @copyright  Copyright (C) 2017 - 2026 Open Source Matters, Inc. All rights reserved.
 *             Copyright (C) 2008 - 2016 compjoom.com All rights reserved.
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
 * GplRule searches all files for GPL/compatible licences.
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
    protected ?string $regexGPLLicences;

    /**
     * Regex for matching obfuscated code.
     *
     * @var string|null
     * @since 3.0.0
     */
    protected ?string $regexCompatLicences;

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

        $files = Folder::files($this->basedir, '\.php$', true, true);

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

        $GPLLicences            = (array)file($dataDir . 'gnu.txt');
        $this->regexGPLLicences = $this->generateRegexp($GPLLicences);

        $compatLicences = (array)file($dataDir . 'compat.txt');
        $extraLicences  = explode(',', $this->params->get('constants', ''));
        $compatLicences = array_merge($compatLicences, $extraLicences);

        $this->regexCompatLicences = $this->generateRegexp($compatLicences);
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
        $content = preg_replace('/^\s*\*/m', '', $content);

        if (preg_match($this->regexGPLLicences, $content, $match, PREG_OFFSET_CAPTURE)) {
            $lineno = substr_count($content, "\n", 0, $match[0][1]) + 1;
            $this->report->addPassed($file, Text::_('COM_JEDCHECKER_PH1_LICENCE_FOUND'), $lineno, $match[0][0]);

            return true;
        }

        if (preg_match($this->regexCompatLicences, $content, $match, PREG_OFFSET_CAPTURE)) {
            $lineno = substr_count($content, "\n", 0, $match[0][1]) + 1;
            $this->report->addWarning(
                $file,
                Text::_('COM_JEDCHECKER_GPL_COMPATIBLE_LICENCE_WAS_FOUND'),
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

                $titles[$title] = 1;
            }
        }

        if (\count($titles) === 0) {
            return null;
        }

        $titles = implode('|', array_keys($titles));

        if (\count($ids)) {
            $ids    = implode('|', array_keys($ids));
            $titles .=
                    '|\blicence\b.+?(?:' . $ids . ')' .
                    '|\b(?:' . $ids . ')\s+licence\b';
        }

        return '#^.*?(?:' . $titles . ').*?$#im';
    }
}
