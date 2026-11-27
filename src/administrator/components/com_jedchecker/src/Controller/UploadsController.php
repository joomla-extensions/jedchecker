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

namespace Joomla\Component\Jedchecker\Administrator\Controller;

use Joomla\Archive\Archive;
use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\Component\Jedchecker\Administrator\Helper\CheckerHelper;
use Joomla\Component\Jedchecker\Administrator\Model\UploadsModel;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * UploadsController handles file upload, extraction, rule execution, and cleanup.
 *
 * @since  3.0.0
 */
class UploadsController extends BaseController
{
    /** Maximum nesting depth of archives-within-archives to extract. */
    private const MAX_EXTRACTION_DEPTH = 5;

    /** Maximum number of nested archives to extract per upload. */
    private const MAX_NESTED_ARCHIVES = 200;

    /** Maximum cumulative decompressed size (bytes) to extract per upload. */
    private const MAX_EXTRACTED_BYTES = 500 * 1024 * 1024;

    /**
     * Run a single rule against all unzipped folders and return HTML as JSON.
     *
     * @return  void
     *
     * @since   3.0.0
     */
    public function check(): void
    {
        // An AJAX call: answer 403 (the script shows it) rather than exiting with an empty 200.
        if (! $this->checkToken('get', false) || ! $this->app->getIdentity()->authorise('core.manage', 'com_jedchecker')) {
            $this->app->setHeader('status', 403, true);
            $this->app->sendHeaders();
            echo htmlspecialchars(Text::_('JERROR_ALERTNOAUTHOR'), ENT_QUOTES, 'UTF-8');
            $this->app->close();
        }

        $shortName = $this->app->getInput()->get('rule', '', 'string');

        /** @var UploadsModel $model */
        $model = $this->getModel('Uploads', 'Administrator');

        $model->runRule($shortName);

        $this->app->close();
    }

    /**
     * Delete the temporary jed_checker directory.
     *
     * @return  void
     *
     * @since   3.0.0
     */
    public function clear(): void
    {
        $this->checkToken();
        $this->assertCanManage();

        /** @var UploadsModel $model */
        $model = $this->getModel('Uploads', 'Administrator');

        if (! $model->clearPaths()) {
            $this->app->enqueueMessage(Text::_('COM_JEDCHECKER_DELETE_FAILED'), 'error');
        }

        $this->setRedirect('index.php?option=com_jedchecker&view=uploads');
    }

    /**
     * Upload a ZIP file and immediately extract it.
     *
     * @return  boolean
     *
     * @since  3.0.0
     */
    public function upload(): bool
    {
        $app   = $this->app;
        $input = $app->getInput();

        $this->checkToken();
        $this->assertCanManage();

        /** @var UploadsModel $model */
        $model = $this->getModel('Uploads', 'Administrator');

        $file = $input->files->get('extension', null, 'raw');

        if (empty($file['tmp_name']) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ! is_uploaded_file($file['tmp_name'])) {
            if (! empty($file['name'])) {
                $app->enqueueMessage(Text::_('COM_JEDCHECKER_ERROR_UNABLE_TO_UPLOAD_FILE'), 'error');
            }

            $this->setRedirect('index.php?option=com_jedchecker&view=uploads');

            return false;
        }

        $archivePath = $model->getArchivePath();

        // Protect the whole work folder before anything from the package lands in it.
        Folder::create($model->getBasePath());
        CheckerHelper::protectDirectory($model->getBasePath());

        if (! is_dir($archivePath)) {
            Folder::create($archivePath);
        } else {
            foreach (Folder::files($archivePath) as $existing) {
                File::delete($archivePath . '/' . $existing);
            }
        }

        // The file name becomes a folder name and is shown in the report: keep it plain.
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', basename((string) $file['name']));

        if (! preg_match('/\.(?:zip|tar|tgz|tbz2|gz|gzip|bz2|bzip2)$/i', $filename)) {
            $app->enqueueMessage(Text::_('COM_JEDCHECKER_ERROR_UNABLE_TO_UPLOAD_FILE'), 'error');
            $app->redirect('index.php?option=com_jedchecker&view=uploads');

            return false;
        }

        $file['filepath'] = $archivePath . '/' . strtolower($filename);

        if (! File::upload($file['tmp_name'], $file['filepath'], false, true)) {
            $app->enqueueMessage(Text::_('COM_JEDCHECKER_ERROR_UNABLE_TO_UPLOAD_FILE'), 'error');
            $app->redirect('index.php?option=com_jedchecker&view=uploads');

            return false;
        }

        $message = $this->unzip(basename($file['filepath']));

        if ($message !== 'COM_JEDCHECKER_UNZIP_SUCCESS') {
            $app->enqueueMessage(Text::_($message), 'error');
        }

        $this->setRedirect('index.php?option=com_jedchecker&view=uploads');

        return true;
    }

    /**
     * Extract the uploaded archive into the unzipped path.
     *
     * Not a task: only upload() calls it, after its token and permission checks.
     *
     * @param   string  $filename  The uploaded archive's name in the archive folder
     *
     * @return  string  Language key of the result message
     *
     * @since   3.0.0
     */
    protected function unzip(string $filename): string
    {
        /** @var UploadsModel $model */
        $model        = $this->getModel('Uploads', 'Administrator');
        $archivePath  = $model->getArchivePath();
        $unzippedPath = $model->getUnzippedPath();

        if (! is_dir($unzippedPath)) {
            Folder::create($unzippedPath);
        } else {
            foreach (Folder::folders($unzippedPath) as $folder) {
                Folder::delete($unzippedPath . '/' . $folder);
            }
        }

        // By name: the archive folder also holds the web.config protectDirectory() writes, which
        // sorts after e.g. "web_links.zip" and used to be picked as "the" archive.
        $origin = $archivePath . '/' . $filename;

        if (! is_file($origin)) {
            return 'COM_JEDCHECKER_UNZIP_FAILED';
        }

        if (CheckerHelper::exceedsSizeLimit($origin, self::MAX_EXTRACTED_BYTES)) {
            return 'COM_JEDCHECKER_ERROR_ARCHIVE_TOO_LARGE';
        }

        $destination = $unzippedPath . '/' . $filename;

        try {
            $archive = new Archive(['tmp_path' => $this->app->get('tmp_path')]);
            $result  = $archive->extract($origin, $destination);
        } catch (\Throwable $e) {
            $result = false;
        }

        if (! $result) {
            return 'COM_JEDCHECKER_UNZIP_FAILED';
        }

        $this->unzipAll($destination);

        // Again after unpacking: disables any .htaccess / web.config the package brought along.
        CheckerHelper::protectDirectory($model->getBasePath());
        $this->app->enqueueMessage(Text::_('COM_JEDCHECKER_UNZIP_SUCCESS'));

        return 'COM_JEDCHECKER_UNZIP_SUCCESS';
    }

    /**
     * Recursively extract nested archives, bounded by depth, archive count, and cumulative
     * decompressed size, since the archive contents are fully attacker-controlled and an
     * unbounded recursion is a decompression-bomb denial-of-service vector.
     *
     * @param   string          $start  Directory to start from
     * @param   int             $depth  Current nesting depth (internal use on recursive calls)
     * @param   \stdClass|null  $state  Shared counters across the recursive call tree (internal use)
     *
     * @return  void
     *
     * @since   3.0.0
     */
    protected function unzipAll(string $start, int $depth = 0, ?\stdClass $state = null): void
    {
        if ($state === null) {
            $state = (object)['archives' => 0, 'bytes' => CheckerHelper::directorySize($start)];
        }

        if ($depth >= self::MAX_EXTRACTION_DEPTH || $state->bytes >= self::MAX_EXTRACTED_BYTES) {
            return;
        }

        $iterator = new \RecursiveDirectoryIterator($start);

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
                    $unzip = $file->getPath() . '/' . $file->getBasename($matches[0]);

                    if (CheckerHelper::exceedsSizeLimit($file->getPathname(), self::MAX_EXTRACTED_BYTES - $state->bytes)) {
                        continue;
                    }

                    try {
                        $archive = new Archive(['tmp_path' => $this->app->get('tmp_path')]);
                        $result  = $archive->extract($file->getPathname(), $unzip);
                    } catch (\Throwable $e) {
                        $result = false;
                    }

                    if ($result) {
                        File::delete($file->getPathname());
                        $state->archives++;
                        $state->bytes += CheckerHelper::directorySize($unzip);

                        $this->unzipAll($unzip, $depth + 1, $state);
                    }
                }
            } elseif (! $iterator->isDot()) {
                $this->unzipAll($file->getPathname(), $depth, $state);
            }
        }
    }

    /**
     * Refuse with Joomla's "not allowed" page (HTTP 403) unless the user may use JED Checker.
     *
     * @return  void
     *
     * @throws  NotAllowed
     *
     * @since   __DEPLOY_VERSION__
     */
    protected function assertCanManage(): void
    {
        if (! $this->app->getIdentity()->authorise('core.manage', 'com_jedchecker')) {
            throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }
    }
}
