<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jedchecker\Tests\Unit;

use Joomla\Component\Jedchecker\Administrator\Rule\AbstractRule;
use PHPUnit\Framework\TestCase;

/**
 * Base class for the tests: builds a package folder from an array of files and runs a rule on it.
 */
abstract class RuleTestCase extends TestCase
{
    /**
     * The package folder of the current test.
     *
     * @var string
     */
    protected string $packageDir = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->packageDir = sys_get_temp_dir() . '/jedchecker_test_' . bin2hex(random_bytes(6));
        mkdir($this->packageDir, 0777, true);
    }

    protected function tearDown(): void
    {
        self::removeDirectory($this->packageDir);

        parent::tearDown();
    }

    /**
     * Skip the test on Windows: Joomla\Filesystem\Folder returns paths with "\" there, and some
     * rules only handle "/" in paths.
     *
     * @param   string  $what  What does not work with "\"
     *
     * @return  void
     */
    protected function requireForwardSlashPaths(string $what): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped($what . ' only handles "/" path separators (Windows paths use "\\").');
        }
    }

    /**
     * Write files into the package folder.
     *
     * @param   array<string, string>  $files  Relative path => content
     *
     * @return  void
     */
    protected function createFiles(array $files): void
    {
        foreach ($files as $path => $content) {
            $fullPath = $this->packageDir . '/' . $path;

            if (! is_dir(\dirname($fullPath))) {
                mkdir(\dirname($fullPath), 0777, true);
            }

            file_put_contents($fullPath, $content);
        }
    }

    /**
     * Run a rule on the package folder.
     *
     * @param   class-string<AbstractRule>  $ruleClass
     *
     * @return  array  The report data: ['count' => [...], 'issues' => [...]]
     */
    protected function runRule(string $ruleClass): array
    {
        /** @var AbstractRule $rule */
        $rule = new $ruleClass($this->packageDir);
        $rule->getReport()->setBasedir($this->packageDir);
        $rule->check();

        return $rule->getReport()->getData();
    }

    /**
     * The issues of one level ('error', 'warning', ...), optionally only those for one file.
     *
     * @param   array        $data      Report data from runRule()
     * @param   string       $type      Level
     * @param   string|null  $location  Path relative to the package folder
     *
     * @return  array
     */
    protected static function issues(array $data, string $type, ?string $location = null): array
    {
        return array_values(array_filter(
            $data['issues'],
            static fn (array $issue): bool => $issue['type'] === $type
                && ($location === null || str_replace('\\', '/', $issue['location']) === $location)
        ));
    }

    /**
     * Assert that one of the issues has a text starting with the given language key.
     *
     * @param   string  $key
     * @param   array   $issues
     *
     * @return  void
     */
    protected static function assertHasMessage(string $key, array $issues): void
    {
        foreach ($issues as $issue) {
            if (str_contains((string) $issue['text'], $key)) {
                self::assertTrue(true);

                return;
            }
        }

        self::fail(\sprintf(
            "No issue with the message %s; found:\n%s",
            $key,
            implode("\n", array_map(static fn (array $issue): string => (string) $issue['text'], $issues))
        ));
    }

    protected static function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($dir);
    }
}
