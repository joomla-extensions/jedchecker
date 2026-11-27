<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public Licence version 2 or later; see LICENCE.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Api;

use Joomla\Archive\Archive;
use Joomla\CMS\Language\Text;
use Joomla\Component\Jedchecker\Administrator\Helper\CheckerHelper;
use Joomla\Component\Jedchecker\Administrator\Rule\RuleDiscovery;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;
use Joomla\Filesystem\Path;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * CheckerApi provides a static entry point to unpack a ZIP and run all JEDChecker rules.
 *
 * @since  3.0.0
 */
class CheckerApi
{
    /** Maximum nesting depth of archives-within-archives to extract. */
    private const MAX_EXTRACTION_DEPTH = 5;

    /** Maximum number of nested archives to extract per check. */
    private const MAX_NESTED_ARCHIVES = 200;

    /** Maximum cumulative decompressed size (bytes) to extract per check. */
    private const MAX_EXTRACTED_BYTES = 500 * 1024 * 1024;

    /**
     * Run all JEDChecker rules against an extension zip file.
     *
     * The temporary folder is always removed, whatever happens (a `finally`, and `\Throwable` rather
     * than `\Exception`, so that a PHP Error - a TypeError on an unusual file, say - neither escapes
     * to the caller nor leaves the unpacked package behind). Each rule runs on its own: a rule that
     * fails is reported as failed, with its message in `error`, and the other rules still run.
     *
     * Locations in the findings are relative to the folder the package was unpacked into, so they
     * start at the package's file name: "com_foo_1.0.0.zip/site/foo.php".
     *
     * @param   string       $zipFile  Absolute path to the extension zip file
     * @param   string|null  $workDir  Folder to unpack in (e.g. Joomla's tmp_path); the system temp
     *                                 folder when null. Useful where open_basedir excludes the latter.
     *
     * @return  array{success: bool, error: string, rules: array, html: string}
     *          Each rule: id, title, description (language keys), data (count + issues), html, and
     *          error ('' unless the rule itself failed).
     *
     * @since   3.0.0
     */
    public static function check(string $zipFile, ?string $workDir = null): array
    {
        if (! is_file($zipFile)) {
            return self::failure('File not found: ' . $zipFile);
        }

        $workDir = rtrim($workDir !== null && $workDir !== '' ? $workDir : sys_get_temp_dir(), '/\\');

        if (! is_dir($workDir) || ! is_writable($workDir)) {
            return self::failure('The temporary folder is not writable: ' . $workDir);
        }

        $baseTmpPath  = $workDir . '/jed_checker_api_' . md5($zipFile . microtime(true) . random_int(0, PHP_INT_MAX));
        $archivePath  = $baseTmpPath . '/archives';
        $unzippedPath = $baseTmpPath . '/unzipped';

        try {
            if (! @mkdir($archivePath, 0755, true) || ! @mkdir($unzippedPath, 0755, true)) {
                return self::failure('Failed to create temporary directories in ' . $workDir);
            }

            // The package contains PHP; should the work folder be inside the web root (a tmp_path
            // given as $workDir), nothing in it may be executable over the web.
            CheckerHelper::protectDirectory($baseTmpPath);

            $archiveFile = $archivePath . '/' . basename($zipFile);

            if (! @copy($zipFile, $archiveFile)) {
                return self::failure('Failed to copy the package to the temporary directory');
            }

            if (CheckerHelper::exceedsSizeLimit($archiveFile, self::MAX_EXTRACTED_BYTES)) {
                return self::failure('The package unpacks to more than ' . (int) (self::MAX_EXTRACTED_BYTES / 1048576) . ' MB');
            }

            $destination = $unzippedPath . '/' . basename($zipFile);
            $archive     = new Archive(['tmp_path' => $workDir]);
            $result      = $archive->extract($archiveFile, $destination);

            if (! $result) {
                return self::failure('Failed to extract archive');
            }

            self::extractNested($destination);

            // Again after unpacking: disables any .htaccess / web.config the package brought along.
            CheckerHelper::protectDirectory($baseTmpPath);

            $folders = self::getFolders($unzippedPath);

            if (empty($folders)) {
                return self::failure('No extension folders found in archive');
            }

            $results      = [];
            $combinedHtml = '';

            foreach (RuleDiscovery::getRules() as $ruleClass) {
                $rule          = self::runRule($ruleClass, $folders, $unzippedPath);
                $results[]     = $rule;
                $combinedHtml .= $rule['html'];
            }

            return [
                    'success' => true,
                    'error'   => '',
                    'rules'   => $results,
                    'html'    => $combinedHtml,
            ];
        } catch (\Throwable $e) {
            return self::failure($e->getMessage());
        } finally {
            self::cleanup($baseTmpPath);
        }
    }

    /**
     * Run one rule over every unpacked folder. A failure is contained here and reported as the
     * rule's result, so that one rule cannot take the others' findings down with it.
     *
     * @param   string    $ruleClass     The rule's class
     * @param   string[]  $folders       The unpacked folders
     * @param   string    $unzippedPath  Folder the locations are made relative to
     *
     * @return  array{id: string, title: string, description: string, data: array, html: string, error: string}
     *
     * @since   __DEPLOY_VERSION__
     */
    protected static function runRule(string $ruleClass, array $folders, string $unzippedPath): array
    {
        $ruleHtml = '';
        $ruleData = null;
        $instance = null;

        try {
            foreach ($folders as $folder) {
                $instance = new $ruleClass(Path::clean($folder));
                $instance->getReport()->setBasedir($unzippedPath);
                $instance->check();

                $report = $instance->getReport();
                $data   = $report->getData();

                $ruleHtml .= $report->getHTML();

                if ($ruleData === null) {
                    $ruleData = $data;
                } else {
                    foreach ($data['count'] as $key => $value) {
                        $ruleData['count'][$key] = ($ruleData['count'][$key] ?? 0) + $value;
                    }

                    $ruleData['issues'] = array_merge($ruleData['issues'], $data['issues']);
                }
            }

            return [
                    'id'          => $instance !== null ? $instance->getId() : self::shortName($ruleClass),
                    'title'       => $instance !== null ? $instance->getTitle() : self::shortName($ruleClass),
                    'description' => $instance !== null ? $instance->getDescription() : '',
                    'data'        => $ruleData ?? self::emptyData(),
                    'html'        => $ruleHtml,
                    'error'       => '',
            ];
        } catch (\Throwable $e) {
            $message = Text::_('COM_JEDCHECKER_RULE_FAILED');
            $message = $message === 'COM_JEDCHECKER_RULE_FAILED' ? 'This rule could not be run on the package: %s' : $message;

            return [
                    'id'          => $instance !== null ? $instance->getId() : self::shortName($ruleClass),
                    'title'       => $instance !== null ? $instance->getTitle() : self::shortName($ruleClass),
                    'description' => $instance !== null ? $instance->getDescription() : '',
                    'data'        => self::emptyData(),
                    'html'        => '<div class="alert alert-secondary">'
                        . htmlspecialchars(\sprintf($message, $e->getMessage()), ENT_QUOTES, 'UTF-8') . '</div>',
                    'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * The result of a check that could not run.
     *
     * @param   string  $error  Why
     *
     * @return  array{success: bool, error: string, rules: array, html: string}
     *
     * @since   __DEPLOY_VERSION__
     */
    protected static function failure(string $error): array
    {
        return [
                'success' => false,
                'error'   => $error,
                'rules'   => [],
                'html'    => '',
        ];
    }

    /**
     * Report data with nothing in it, in the shape of Report::getData().
     *
     * @return  array{count: array<string, int>, issues: array}
     *
     * @since   __DEPLOY_VERSION__
     */
    protected static function emptyData(): array
    {
        return [
                'count'  => ['total' => 0, 'error' => 0, 'warning' => 0, 'compatibility' => 0, 'notice' => 0, 'info' => 0, 'passed' => 0],
                'issues' => [],
        ];
    }

    /**
     * "XmlInfoRule" for a rule class, when the rule could not even be created.
     *
     * @param   string  $ruleClass  The class
     *
     * @return  string
     *
     * @since   __DEPLOY_VERSION__
     */
    protected static function shortName(string $ruleClass): string
    {
        $parts = explode('\\', $ruleClass);

        return (string) end($parts);
    }

    /**
     * cleanup
     *
     * Remove a directory and all its contents.
     *
     * @param   string  $path  Folder to delete
     *
     * @return  void
     *
     * @since 3.0.0
     */
    protected static function cleanup(string $path): void
    {
        if (is_dir($path)) {
            Folder::delete($path);
        }
    }

    /**
     * extractNested
     *
     * Recursive extraction of nested archives within a directory, bounded by depth, archive
     * count, and cumulative decompressed size, since the archive contents are fully
     * attacker-controlled and an unbounded recursion is a decompression-bomb DoS vector.
     *
     * @param   string          $dir    Folder to search in
     * @param   int             $depth  Current nesting depth (internal use on recursive calls)
     * @param   \stdClass|null  $state  Shared counters across the recursive call tree (internal use)
     *
     * @return  void
     *
     * @since  3.0.0
     */
    protected static function extractNested(string $dir, int $depth = 0, ?\stdClass $state = null): void
    {
        if ($state === null) {
            $state = (object)['archives' => 0, 'bytes' => CheckerHelper::directorySize($dir)];
        }

        if ($depth >= self::MAX_EXTRACTION_DEPTH || $state->bytes >= self::MAX_EXTRACTED_BYTES) {
            return;
        }

        $iterator = new \RecursiveDirectoryIterator($dir);

        foreach ($iterator as $file) {
            if ($state->archives >= self::MAX_NESTED_ARCHIVES || $state->bytes >= self::MAX_EXTRACTED_BYTES) {
                return;
            }

            if ($file->isFile()) {
                if (
                    preg_match(
                        '/\.(?:zip|tar|tgz|tbz2|tar\.(?:gz|gzip|bz2|bzip2))$/',
                        $file->getFilename(),
                        $matches
                    )
                ) {
                    $target = $file->getPath() . '/' . $file->getBasename($matches[0]);

                    if (CheckerHelper::exceedsSizeLimit($file->getPathname(), self::MAX_EXTRACTED_BYTES - $state->bytes)) {
                        continue;
                    }

                    try {
                        $archive = new Archive();
                        $result  = $archive->extract($file->getPathname(), $target);
                    } catch (\Throwable $e) {
                        $result = false;
                    }

                    if ($result) {
                        File::delete($file->getPathname());
                        $state->archives++;
                        $state->bytes += CheckerHelper::directorySize($target);

                        self::extractNested($target, $depth + 1, $state);
                    }
                }
            } elseif (! $iterator->isDot()) {
                self::extractNested($file->getPathname(), $depth, $state);
            }
        }
    }

    /**
     * getFolders
     *
     * Get all folders in the unzipped path.
     *
     * @param   string  $unzippedPath  Folder to search in
     *
     * @return array
     *
     * @since  3.0.0
     */
    protected static function getFolders(string $unzippedPath): array
    {
        $folders    = [];
        $subFolders = Folder::folders($unzippedPath);

        if (! empty($subFolders)) {
            foreach ($subFolders as $subFolder) {
                $folders[] = $unzippedPath . '/' . $subFolder;
            }
        }

        return $folders;
    }
}
