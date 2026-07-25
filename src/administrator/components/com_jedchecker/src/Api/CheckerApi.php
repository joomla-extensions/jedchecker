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
    /**
     * Run all JEDChecker rules against an extension zip file.
     *
     * @param   string  $zipFile  Absolute path to the extension zip file
     *
     * @return  array{success: bool, error: string, rules: array, html: string}
     *
     * @since   3.0.0
     */
    public static function check(string $zipFile): array
    {
        if (! is_file($zipFile)) {
            return [
                    'success' => false,
                    'error'   => 'File not found: ' . $zipFile,
                    'rules'   => [],
                    'html'    => '',
            ];
        }

        $baseTmpPath  = sys_get_temp_dir() . '/jed_checker_api_' . md5($zipFile . microtime(true));
        $archivePath  = $baseTmpPath . '/archives';
        $unzippedPath = $baseTmpPath . '/unzipped';

        try {
            if (! mkdir($archivePath, 0755, true) || ! mkdir($unzippedPath, 0755, true)) {
                return [
                        'success' => false,
                        'error'   => 'Failed to create temporary directories',
                        'rules'   => [],
                        'html'    => '',
                ];
            }

            $archiveFile = $archivePath . '/' . basename($zipFile);
            copy($zipFile, $archiveFile);

            $destination = $unzippedPath . '/' . basename($zipFile);
            $archive     = new Archive();
            $result      = $archive->extract($archiveFile, $destination);

            if (! $result) {
                self::cleanup($baseTmpPath);

                return [
                        'success' => false,
                        'error'   => 'Failed to extract archive',
                        'rules'   => [],
                        'html'    => '',
                ];
            }

            self::extractNested($destination);

            $folders = self::getFolders($unzippedPath);

            if (empty($folders)) {
                self::cleanup($baseTmpPath);

                return [
                        'success' => false,
                        'error'   => 'No extension folders found in archive',
                        'rules'   => [],
                        'html'    => '',
                ];
            }

            $ruleClasses  = RuleDiscovery::getRules();
            $results      = [];
            $combinedHtml = '';

            foreach ($ruleClasses as $ruleClass) {
                $ruleHtml = '';
                $ruleData = null;
                $instance = null;

                foreach ($folders as $folder) {
                    $instance = new $ruleClass(Path::clean($folder));
                    $instance->check();

                    $report = $instance->getReport();
                    $html   = $report->getHTML();
                    $data   = $report->getData();

                    $ruleHtml .= $html;

                    if ($ruleData === null) {
                        $ruleData = $data;
                    } else {
                        foreach ($data['count'] as $key => $value) {
                            $ruleData['count'][$key] = ($ruleData['count'][$key] ?? 0) + $value;
                        }

                        $ruleData['issues'] = array_merge($ruleData['issues'], $data['issues']);
                    }
                }

                if ($instance !== null) {
                    $results[] = [
                            'id'          => $instance->getId(),
                            'title'       => $instance->getTitle(),
                            'description' => $instance->getDescription(),
                            'data'        => $ruleData,
                            'html'        => $ruleHtml,
                    ];
                }

                $combinedHtml .= $ruleHtml;
            }

            self::cleanup($baseTmpPath);

            return [
                    'success' => true,
                    'error'   => '',
                    'rules'   => $results,
                    'html'    => $combinedHtml,
            ];
        } catch (\Exception $e) {
            self::cleanup($baseTmpPath);

            return [
                    'success' => false,
                    'error'   => $e->getMessage(),
                    'rules'   => [],
                    'html'    => '',
            ];
        }
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
     * Recursive extraction of nested archives within a directory.
     *
     * @param   string  $dir  Folder to search in
     *
     * @return  void
     *
     * @since  3.0.0
     */
    protected static function extractNested(string $dir): void
    {
        $iterator = new \RecursiveDirectoryIterator($dir);

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                if (
                    preg_match(
                        '/\.(?:zip|tar|tgz|tbz2|tar\.(?:gz|gzip|bz2|bzip2))$/',
                        $file->getFilename(),
                        $matches
                    )
                ) {
                    $target = $file->getPath() . '/' . $file->getBasename($matches[0]);

                    try {
                        $archive = new Archive();
                        $result  = $archive->extract($file->getPathname(), $target);
                    } catch (\Exception $e) {
                        $result = false;
                    }

                    if ($result) {
                        File::delete($file->getPathname());
                        self::extractNested($target);
                    }
                }
            } elseif (! $iterator->isDot()) {
                self::extractNested($file->getPathname());
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
