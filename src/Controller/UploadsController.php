<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2025 Open Source Matters, Inc. All rights reserved.
 *             Copyright (C) 2008 - 2016 compojoom.com . All rights reserved.
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 *
 * @license    GNU General Public Licence version 2 or later; see LICENCE.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Controller;

defined('_JEXEC') or die('Restricted access');

use Joomla\Archive\Archive;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Session\Session;
use Joomla\Component\Jedchecker\Administrator\Model\UploadsModel;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

/**
 * UploadsController handles file upload, extraction, rule execution, and cleanup.
 *
 * @since  3.0
 */
class UploadsController extends BaseController
{
	/**
	 * Upload a ZIP file and immediately extract it.
	 *
	 * @return  bool
	 */
	public function upload(): bool
	{
		$app   = $this->app;
		$input = $app->getInput();

		Session::checkToken() or $app->close(403);

		/** @var UploadsModel $model */
		$model = $this->getModel('Uploads', 'Administrator');

		$file = $input->files->get('extension', null, 'raw');

		if (empty($file['tmp_name']))
		{
			$this->setRedirect('index.php?option=com_jedchecker&view=uploads');

			return false;
		}

		$archivePath = $model->getArchivePath();

		if (!is_dir($archivePath))
		{
			Folder::create($archivePath);
		}
		else
		{
			foreach (Folder::files($archivePath) as $existing)
			{
				File::delete($archivePath . '/' . $existing);
			}
		}

		$file['filepath'] = $archivePath . '/' . strtolower($file['name']);

		if (!File::upload($file['tmp_name'], $file['filepath'], false))
		{
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
	 */
	public function unzip(): string
	{
		$app = $this->app;

		Session::checkToken() or $app->close(403);

		/** @var UploadsModel $model */
		$model        = $this->getModel('Uploads', 'Administrator');
		$archivePath  = $model->getArchivePath();
		$unzippedPath = $model->getUnzippedPath();

		if (!is_dir($unzippedPath))
		{
			Folder::create($unzippedPath);
		}
		else
		{
			foreach (Folder::folders($unzippedPath) as $folder)
			{
				Folder::delete($unzippedPath . '/' . $folder);
			}
		}

		$files = Folder::files($archivePath);

		if (empty($files))
		{
			return 'COM_JEDCHECKER_UNZIP_FAILED';
		}

		$origin      = $archivePath . DIRECTORY_SEPARATOR . $files[0];
		$destination = $unzippedPath . DIRECTORY_SEPARATOR . $files[0];

		try
		{
			$archive = new Archive;
			$result  = $archive->extract($origin, $destination);
		}
		catch (\Exception $e)
		{
			$result = false;
		}

		if ($result)
		{
			$this->unzipAll($unzippedPath . '/' . $files[0]);
			$message = 'COM_JEDCHECKER_UNZIP_SUCCESS';
			$app->enqueueMessage(Text::_($message));
		}
		else
		{
			$message = 'COM_JEDCHECKER_UNZIP_FAILED';
		}

		return $message;
	}

	/**
	 * Recursively extract nested archives.
	 *
	 * @param   string  $start  Directory to start from
	 *
	 * @return  void
	 */
	public function unzipAll(string $start): void
	{
		$iterator = new \RecursiveDirectoryIterator($start);

		foreach ($iterator as $file)
		{
			if ($file->isFile())
			{
				if (preg_match('/\.(?:zip|tar|tgz|tbz2|tar\.(?:gz|gzip|bz2|bzip2))$/', $file->getFilename(), $matches))
				{
					$unzip = $file->getPath() . '/' . $file->getBasename($matches[0]);

					try
					{
						$archive = new Archive;
						$result  = $archive->extract($file->getPathname(), $unzip);
					}
					catch (\Exception $e)
					{
						$result = false;
					}

					if ($result)
					{
						File::delete($file->getPathname());
						$this->unzipAll($unzip);
					}
				}
			}
			elseif (!$iterator->isDot())
			{
				$this->unzipAll($file->getPathname());
			}
		}
	}

	/**
	 * Run a single rule against all unzipped folders and return HTML as JSON.
	 *
	 * @return  void
	 */
	public function check(): void
	{
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
	 */
	public function clear(): void
	{
		/** @var UploadsModel $model */
		$model = $this->getModel('Uploads', 'Administrator');

		if (!$model->clearPaths())
		{
			$this->app->enqueueMessage(Text::_('COM_JEDCHECKER_DELETE_FAILED'), 'error');
		}

		$this->setRedirect('index.php?option=com_jedchecker&view=uploads');
	}
}
