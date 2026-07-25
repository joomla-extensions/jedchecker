<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 * @copyright  Copyright (C) 2017 - 2026 Open Source Matters, Inc. All rights reserved.
 *             Copyright (C) 2008 - 2016 compjoom.com All rights reserved.
 *             eaxs <support@projectfork.net>
 *
 * @license    GNU General Public Licence version 2 or later; see LICENCE.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Rule\Rules;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;
use Joomla\Component\Jedchecker\Administrator\Rule\AbstractRule;

/**
 * JexecRule searches all files for the _JEXEC guard which prevents direct file access.
 *
 * @since  3.0.0
 */
class JexecRule extends AbstractRule
{
    /**
        * The formal ID of this rule. For example: SE1.
        *
        * @var string
        * @since 3.0.0
        */
    protected string $id = 'PH2';
    /**
         * The title or caption of this rule.
         *
         * @var string
         * @since 3.0.0
         */
    protected string $title = 'COM_JEDCHECKER_RULE_PH2';

    /**
     * Description of the rule.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $description = 'COM_JEDCHECKER_RULE_PH2_DESC';
    /**
        * Rule ordering.
        *
        * @var integer
        * @since 3.0.0
        */
    public static int $ordering = 600;



    /**
     * Regexp to match _JEXEC-like guard
     *
     * @var string
     * @since 3.0.0
     */
    protected string $regex;

    /**
     * Regexp to match directories to skip
     *
     * @var string
     * @since 3.0.0
     */
    protected string $regexExcludeFolders;

    /**
     * List of files related to libraries
     *
     * @var array
     * @since 3.0.0
     */
    protected array $libFiles;

    /**
* Initiates the file search and check
     *
* @return    void
     * @since  3.0.0
     */
    public function check(): void
    {
        $this->report->setDefaultSubtype($this->id);

        $this->initJexec();

        // Find all php files of the extension
        $files = $this->files($this->basedir);

        // Iterate through all files
        foreach ($files as $file) {
            // Try to find the _JEXEC check in the file
            if (!$this->find($file)) {
                // Add as error to the report if it was not found
                $this->report->addError($file, Text::_('COM_JEDCHECKER_ERROR_JEXEC_NOT_FOUND'));
            }
        }
    }

    /**
     * Reads a file and searches for the _JEXEC statement
     *
     * @param   string  $file - The path to the file
     *
     * @return bool True if the statement was found, otherwise False.
     *
     * @since  3.0.0
     */
    protected function find(string $file): bool
    {
        // Load file and strip comments
        $content = php_strip_whitespace($file);

        // Strip BOM (it is checked separately)
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        // Skip empty files
        if ($content === '' || preg_match('#^\s*<\?php\s+$#', $content)) {
            return true;
        }

        // Check guards
        if (preg_match($this->regex, $content)) {
            return true;
        }

        return false;
    }
    /**
     * initJexec
     *
     * Initializes the JEXEC rule by setting up regular expressions and constants.
     *
     *
     * @since  3.0.0
     */
    protected function initJexec(): void
    {
        // Generate regular expression to match JEXEC quard
        $defines = $this->params->get('constants');
        $defines = explode(',', $defines);

        foreach ($defines as $i => $define) {
            $defines[$i] = preg_quote(trim($define), '#');
        }

        $use_single_ns = '[0-9A-Za-z_\\\\]+(?: as [0-9A-Za-z_]+?)? ?';
        $use_group_ns  = '[0-9A-Za-z_\\\\]+\\\\\\{ ?' . $use_single_ns . '(?:, ?' . $use_single_ns . ' ?)*\\} ?';
        $use_ns        = '(?:' . $use_single_ns . '|' . $use_group_ns . ')(?:, ?(?:' . $use_single_ns . '|' . $use_group_ns . '))*';
        $this->regex
            = '#^\s*' // at the beginning of the file
            . '<\?php\s+' // there is an opening php tag
            . '(?:declare ?\(strict_types ?= ?1 ?\) ?; ?)?' // optionally followed by declare(strict_types=1) directive
            . '(?:namespace [0-9A-Za-z_\\\\]+ ?; ?)?' // optionally followed by namespace directive
            . '(?:use (?:function |const )?' . $use_ns . '; ?)*' // optionally followed by use directives
            . '\\\\?defined ?\( ?' // followed by defined test
            . '([\'"])(?:' . implode('|', $defines) . ')\1' // of any of given constant
            . ' ?\) ?(?:or |\|\| ?)(?:die|exit)\b' // or exit
            . '#i'; // (case insensitive)

        // Generate regular expression to match excluded directories
        $libfolders = $this->params->get('libfolders');
        $libfolders = explode(',', $libfolders);

        foreach ($libfolders as &$libfolder) {
            $libfolder = preg_quote(trim($libfolder), '#');
        }

        // Prepend libFolders with default Joomla's exclude list
        $this->regexExcludeFolders = '#^(?:\.svn|CVS|\.DS_Store|__MACOSX|' . implode('|', $libfolders) . ')$#';

        // Generate list of libraries fingerprint files
        $libFiles       = $this->params->get('libfiles');
        $this->libFiles = array_map('trim', explode(',', $libFiles));
    }

    /**
     * Collect php files to check (excluding external library directories)
     *
     * @param   string $path The path of the folder to read.
     * @param   int $level The current hierarchy level.
     *
     * @return array
     *
     * @since  3.0.0
     */
    protected function files(string $path, int $level = 0): array
    {
        $arr = [];

        // Read the source directory
        if ($handle = @opendir($path)) {
            while (($file = readdir($handle)) !== false) {
                // Skip excluded directories
                if ($file !== '.' && $file !== '..' && !preg_match($this->regexExcludeFolders, $file)) {
                    $fullpath = $path . '/' . $file;

                    if (is_dir($fullpath)) {
                        if ($level > 0) {
                            // Detect and skip external library directories
                            foreach ($this->libFiles as $libFile) {
                                if (is_file($fullpath . '/' . $libFile)) {
                                    // Skip processing of this directory
                                    continue 2;
                                }
                            }
                        }

                        $arr = array_merge($arr, $this->files($fullpath, $level + 1));
                    } elseif (preg_match('/\.php$/', $file)) {
                        $arr[] = $fullpath;
                    }
                }
            }

            closedir($handle);
        }

        return $arr;
    }
}
