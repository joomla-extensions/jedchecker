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
use Joomla\Component\Jedchecker\Administrator\Helper\CheckerHelper;
use Joomla\Component\Jedchecker\Administrator\Rule\AbstractRule;
use Joomla\Filesystem\Folder;
use stdClass;

/**
 * PhpRule checks for PHP compatibility issues — deprecated and removed functions.
 *
 * @since  3.0.0
 */
class PhpRule extends AbstractRule
{
    /**
     * Data files remain in libraries/rules/ during the transitional migration period
     *
     * @var string
     * @since 3.0.0
     */
    private const DATA_DIR = '/components/com_jedchecker/src/Rule/Rules/data/';
    /**
     * Rule ordering.
     *
     * @var integer
     * @since 3.0.0
     */
    public static int $ordering = 725;
    /**
     * Rule ID.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $id = 'PHP';
    /**
     * Rule title.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $title = 'COM_JEDCHECKER_RULE_PHP';
    /**
     * Description of the rule.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $description = 'COM_JEDCHECKER_RULE_PHP_DESC';

    /**
     * Whether the tests have been loaded
     *
     * @var bool
     * @since 3.0.0
     */
    protected $tests = false;

    /**
     * check
     *
     * Runs the rule.
     *
     * @since  3.0.0
     */
    public function check(): void
    {
        $files = Folder::files($this->basedir, '\.php$', true, true);

        foreach ($files as $file) {
            $this->find($file);
        }
    }

    /**
     * find
     *
     * Finds and processes a manifest file.
     *
     * @param   string  $file
     *
     * @return bool
     *
     * @since  3.0.0
     */
    protected function find(string $file): bool
    {
        $origContent = (array)file($file);

        if (count($origContent) === 0) {
            return false;
        }

        $content = file_get_contents($file);

        $cleanContent = CheckerHelper::cleanPhpCode(
            $content,
            CheckerHelper::CLEAN_HTML | CheckerHelper::CLEAN_COMMENTS | CheckerHelper::CLEAN_STRINGS
        );
        $cleanContent = CheckerHelper::splitLines($cleanContent);

        $result = false;

        foreach ($this->getTests() as $testObject) {
            if ($this->runTest($file, $origContent, $cleanContent, $testObject)) {
                $result = true;
            }
        }

        return $result;
    }

    /**
     * getTests
     *
     * Retrieves the list of tests for PHP code analysis.
     *
     * @return array
     *
     * @since  3.0.0
     */
    private function getTests(): array
    {
        if (! $this->tests) {
            $this->tests = [];
            $this->loadDeprecatedPatterns();
        }

        return $this->tests;
    }

    /**
     * runTest
     *
     * Runs a single test on a line of PHP code.
     *
     * @param   string  $file
     * @param   array   $origContent
     * @param   array   $cleanContent
     * @param   object  $testObject
     *
     * @return bool
     *
     * @since  3.0.0
     */
    private function runTest(string $file, array $origContent, array $cleanContent, object $testObject): bool
    {
        $error_count = 0;

        foreach ($cleanContent as $line_number => $line) {
            $origLine = $origContent[$line_number];

            foreach ($testObject->tests as $singleTest) {
                if (preg_match($singleTest->regex, $line)) {
                    $highlightedLine = str_ireplace($singleTest->test, '<b>' . $singleTest->test . '</b>', $origLine);
                    $highlightedLine = htmlspecialchars($highlightedLine, ENT_NOQUOTES);
                    $highlightedLine = str_replace(['&lt;b&gt;', '&lt;/b&gt;'], ['<b>', '</b>'], $highlightedLine);

                    $langKey       = (strpos(
                        $testObject->group,
                        'deprecated'
                    ) === 0) ? 'COM_JEDCHECKER_ERROR_PHP_DEPRECATED' : 'COM_JEDCHECKER_ERROR_PHP_REMOVED';
                    $error_message = sprintf(
                        Text::_($langKey),
                        $testObject->version
                    ) . ':<pre>' . $highlightedLine . '</pre>';

                    if ($singleTest->replacement !== false) {
                        $error_message .= Text::_('COM_JEDCHECKER_ERROR_PHP_INSTEAD_USE') . ': ' . $singleTest->replacement;
                    }

                    switch ($testObject->kind) {
                        case 'error':
                            $this->report->addError($file, $error_message, $line_number);
                            break;
                        default:
                            $this->report->addWarning($file, $error_message, $line_number);
                            break;
                    }

                    $error_count++;
                }

                if ($error_count > 100) {
                    return true;
                }
            }
        }

        return $error_count > 0;
    }

    /**
     * loadDeprecatedPatterns
     *
     * Loads the deprecated patterns from a JSON file and populates the test objects.
     *
     *
     * @since  3.0.0
     */
    private function loadDeprecatedPatterns(): void
    {
        $deprecatedFile = JPATH_ADMINISTRATOR . self::DATA_DIR . 'php_deprecated.json';

        if (! file_exists($deprecatedFile)) {
            return;
        }

        $jsonContent = file_get_contents($deprecatedFile);

        if ($jsonContent === false) {
            return;
        }

        $sections = json_decode($jsonContent, true);

        if (! is_array($sections)) {
            return;
        }

        foreach ($sections as $sectionName => $patterns) {
            $newTest        = new stdClass();
            $newTest->group = $sectionName;
            $newTest->kind  = (strpos($sectionName, 'removed') === 0) ? 'error' : 'warning';
            $newTest->tests = [];

            if (preg_match('/(?:deprecated|removed)-in-php-(.+)/', $sectionName, $matches)) {
                $newTest->version = $matches[1];
            } else {
                $newTest->version = null;
            }

            foreach ($patterns as $pattern => $replacement) {
                $testObj              = new stdClass();
                $testObj->test        = $pattern;
                $testObj->regex       = $this->generateRegex($pattern);
                $testObj->replacement = ($replacement !== '') ? $replacement : false;

                $newTest->tests[] = $testObj;
            }

            $this->tests[] = $newTest;
        }
    }

    /**
     * generateRegex
     *
     * Generates a regular expression pattern for a given test string.
     *
     * @param   string  $test
     *
     * @return string
     *
     * @since  3.0.0
     */
    private function generateRegex(string $test): string
    {
        $regex = preg_quote($test, '/');

        if (ctype_alpha($test[0])) {
            $regex = '\b' . $regex;
        }

        if (ctype_alpha($test[strlen($test) - 1])) {
            $regex .= '\b';
        }

        return '/' . $regex . '/i';
    }
}
