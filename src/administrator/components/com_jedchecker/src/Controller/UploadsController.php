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
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;
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
        Session::checkToken('get') || $this->app->close(403);
        $this->app->getIdentity()->authorise('core.manage', 'com_jedchecker') || $this->app->close(403);

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
        Session::checkToken() || $this->app->close(403);
        $this->app->getIdentity()->authorise('core.manage', 'com_jedchecker') || $this->app->close(403);

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

        Session::checkToken() || $app->close(403);
        $app->getIdentity()->authorise('core.manage', 'com_jedchecker') || $app->close(403);

        /** @var UploadsModel $model */
        $model = $this->getModel('Uploads', 'Administrator');

        $file = $input->files->get('extension', null, 'raw');

        if (empty($file['tmp_name'])) {
            $this->setRedirect('index.php?option=com_jedchecker&view=uploads');

            return false;
        }

        $archivePath = $model->getArchivePath();

        if (! is_dir($archivePath)) {
            Folder::create($archivePath);
        } else {
            foreach (Folder::files($archivePath) as $existing) {
                File::delete($archivePath . '/' . $existing);
            }
        }

        $this->protectDirectory($archivePath);

        $filename = basename($file['name']);

        if (! preg_match('/\.(?:zip|tar|tgz|tbz2|gz|gzip|bz2|bzip2)$/i', $filename)) {
            $app->enqueueMessage(Text::_('COM_JEDCHECKER_ERROR_UNABLE_TO_UPLOAD_FILE'), 'error');
            $app->redirect('index.php?option=com_jedchecker&view=uploads');

            return false;
        }

        $file['filepath'] = $archivePath . '/' . strtolower($filename);

        if (! File::upload($file['tmp_name'], $file['filepath'], false)) {
            $app->enqueueMessage(Text::_('COM_JEDCHECKER_ERROR_UNABLE_TO_UPLOAD_FILE'), 'error');
            $app->redirect('index.php?option=com_jedchecker&view=uploads');

            return false;
        }

        $this->unzip();
        $this->setRedirect('index.php?option=com_jedchecker&view=uploads');

        return true;
    }

    /**
     * Extract the uploaded archive into the unzipped path.
     *
     * @return  string  Language key of the result message
     *
     * @since   3.0.0
     */
    public function unzip(): string
    {
        $app = $this->app;

        Session::checkToken() || $app->close(403);
        $app->getIdentity()->authorise('core.manage', 'com_jedchecker') || $app->close(403);

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

        $files = Folder::files($archivePath);

        if (empty($files)) {
            return 'COM_JEDCHECKER_UNZIP_FAILED';
        }

        $origin      = $archivePath . DIRECTORY_SEPARATOR . $files[0];
        $destination = $unzippedPath . DIRECTORY_SEPARATOR . $files[0];

        try {
            $archive = new Archive();
            $result  = $archive->extract($origin, $destination);
        } catch (\Exception $e) {
            $result = false;
        }

        if ($result) {
            $this->unzipAll($unzippedPath . '/' . $files[0]);
            $this->protectDirectory($unzippedPath);
            $message = 'COM_JEDCHECKER_UNZIP_SUCCESS';
            $app->enqueueMessage(Text::_($message));
        } else {
            $message = 'COM_JEDCHECKER_UNZIP_FAILED';
        }

        return $message;
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
    public function unzipAll(string $start, int $depth = 0, ?\stdClass $state = null): void
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

                    try {
                        $archive = new Archive();
                        $result  = $archive->extract($file->getPathname(), $unzip);
                    } catch (\Exception $e) {
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
     * Prevent execution of any extracted/uploaded file if this directory (or a subdirectory of it)
     * is ever reachable over the web, e.g. because Joomla's temp_path was left at its default,
     * inside the site's webroot. The package contents are fully attacker-controlled, so any
     * .htaccess/web.config bundled inside the archive is removed first to stop it overriding
     * the rules written here.
     *
     * @param   string  $path  Directory to protect
     *
     * @return  void
     *
     * @since   3.0.0
     */
    protected function protectDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (Folder::files($path, '^(\.htaccess|web\.config)$', true, true, [], []) as $existing) {
            File::delete($existing);
        }

        File::write(
            $path . '/.htaccess',
            "<FilesMatch \"\\.(php[0-9]*|phtml|pl|py|jsp|asp|aspx|sh|cgi|exe|dll|so)\$\">\n"
            . "    <IfModule mod_authz_core.c>\n"
            . "        Require all denied\n"
            . "    </IfModule>\n"
            . "    <IfModule !mod_authz_core.c>\n"
            . "        Order allow,deny\n"
            . "        Deny from all\n"
            . "    </IfModule>\n"
            . "</FilesMatch>\n"
            . "<IfModule mod_php.c>\n"
            . "    php_flag engine off\n"
            . "</IfModule>\n"
            . "<IfModule mod_php7.c>\n"
            . "    php_flag engine off\n"
            . "</IfModule>\n"
            . "<IfModule mod_php8.c>\n"
            . "    php_flag engine off\n"
            . "</IfModule>\n"
        );

        File::write(
            $path . '/web.config',
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<configuration>\n"
            . "    <system.webServer>\n"
            . "        <handlers>\n"
            . "            <clear />\n"
            . "        </handlers>\n"
            . "        <security>\n"
            . "            <requestFiltering>\n"
            . "                <fileExtensions>\n"
            . "                    <add fileExtension=\".php\" allowed=\"false\" />\n"
            . "                    <add fileExtension=\".phtml\" allowed=\"false\" />\n"
            . "                    <add fileExtension=\".asp\" allowed=\"false\" />\n"
            . "                    <add fileExtension=\".aspx\" allowed=\"false\" />\n"
            . "                    <add fileExtension=\".exe\" allowed=\"false\" />\n"
            . "                    <add fileExtension=\".dll\" allowed=\"false\" />\n"
            . "                </fileExtensions>\n"
            . "            </requestFiltering>\n"
            . "        </security>\n"
            . "    </system.webServer>\n"
            . "</configuration>\n"
        );
    }
}
