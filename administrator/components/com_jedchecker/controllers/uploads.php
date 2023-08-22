<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2019 Open Source Matters, Inc. All rights reserved.
 * 			   Copyright (C) 2008 - 2016 compojoom.com . All rights reserved.
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 * 			   02.06.12
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\Archive\Archive;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Session\Session;

/**
 * Class JedcheckerControllerUploads
 *
 * @since  1.0
 */
class JedcheckerControllerUploads extends BaseController
{
	/** @var string */
	public $path;

	/** @var string */
	public $pathArchive;

	/** @var string */
	public $pathUnzipped;

	/**
	 * Constructor.
	 *
	 */
	public function __construct()
	{
		$this->path         = Factory::getConfig()->get('tmp_path') . '/jed_checker';
		$this->pathArchive  = $this->path . '/archives';
		$this->pathUnzipped = $this->path . '/unzipped';
		parent::__construct();
	}

	/**
	 * basic upload
	 *
	 * @return boolean
	 */
	public function upload()
	{
		$appl  = Factory::getApplication();
		$input = $appl->input;

		// Check the sent token by the form
		Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

		// Gets the uploaded file from the sent form
		$file = $input->files->get('extension', null, 'raw');

		if ($file['tmp_name'])
		{
			$path = $this->pathArchive;

			// If the archive folder doesn't exist - create it!
			if (!Folder::exists($path))
			{
				Folder::create($path);
			}
			else
			{
				// Let us remove all previous uploads
				$archiveFiles = Folder::files($path);

				foreach ($archiveFiles as $archive)
				{
					if (!File::delete($this->pathArchive . '/' . $archive))
					{
						echo 'could not delete' . $archive;
					}
				}
			}

			$file['filepath'] = $path . '/' . strtolower($file['name']);

			// Let us try to upload
			if (!File::upload($file['tmp_name'], $file['filepath'], false, true))
			{
				// Error in upload - redirect back with an error notice
				$appl->enqueueMessage(Text::_('COM_JEDCHECKER_ERROR_UNABLE_TO_UPLOAD_FILE'), 'error');
				$appl->redirect('index.php?option=com_jedchecker&view=uploads');

				return false;
			}

			// Unzip uploaded files
			$unzip_result = $this->unzip();

			$this->setRedirect('index.php?option=com_jedchecker&view=uploads');

			return true;
		}
		else
		{
			$this->setRedirect('index.php?option=com_jedchecker&view=uploads');
		}

		return false;
	}

	/**
	 * unzip the file
	 *
	 * @return boolean
	 */
	public function unzip()
	{
		$appl  = Factory::getApplication();

		// Form check token
		Session::checkToken() or jexit(Text::_('JINVALID_TOKEN'));

		// If folder doesn't exist - create it!
		if (!Folder::exists($this->pathUnzipped))
		{
			Folder::create($this->pathUnzipped);
		}
		else
		{
			// Let us remove all previous unzipped files
			$folders = Folder::folders($this->pathUnzipped);

			foreach ($folders as $folder)
			{
				Folder::delete($this->pathUnzipped . '/' . $folder);
			}
		}

		$file   = Folder::files($this->pathArchive);

		$origin = $this->pathArchive . DIRECTORY_SEPARATOR . $file[0];
		$destination = $this->pathUnzipped . DIRECTORY_SEPARATOR . $file[0];

		try
		{
			$archive = new Archive;
			$result = $archive->extract($origin, $destination);
		}
		catch (Exception $e)
		{
			$result = false;
		}

		if ($result)
		{
			// Scan unzipped folders if we find zip file -> unzip them as well
			$this->unzipAll($this->pathUnzipped . '/' . $file[0]);
			$message = 'COM_JEDCHECKER_UNZIP_SUCCESS';
			$appl->enqueueMessage(Text::_($message));
		}
		else
		{
			$message = 'COM_JEDCHECKER_UNZIP_FAILED';
		}

		// $appl->redirect('index.php?option=com_jedchecker&view=uploads', Text::_($message));
		$message = 'COM_JEDCHECKER_UNZIP_FAILED';

		return $message;
	}

	/**
	 * Recursively go through each folder and extract the archives
	 *
	 * @param   string $start - the directory where we start the unzipping from
	 *
	 * @return  void
	 */
	public function unzipAll($start)
	{
		$iterator = new RecursiveDirectoryIterator($start);

		foreach ($iterator as $file)
		{
			if ($file->isFile())
			{
				if (preg_match('/\.(?:zip|tar|tgz|tbz2|tar\.(?:gz|gzip|bz2|bzip2))$/', $file->getFilename(), $matches))
				{
					$unzip  = $file->getPath() . '/' . $file->getBasename($matches[0]);

					try
					{
						$archive = new Archive;
						$result = $archive->extract($file->getPathname(), $unzip);
					}
					catch (Exception $e)
					{
						$result = false;
					}

					// Delete the archive once we extract it
					if ($result)
					{
						File::delete($file->getPathname());

						// Now check the new extracted folder for archive files
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
	 * clear tmp folders
	 *
	 * @return    void
	 */
	public function clear()
	{
		if (file_exists($this->path))
		{
			$result = Folder::delete($this->path);

			if (!$result)
			{
				echo 'could not delete ' . $this->path;
				$message = 'COM_JEDCHECKER_DELETE_FAILED';
			}

			$message = 'COM_JEDCHECKER_DELETE_SUCCESS';

			// Factory::getApplication()->redirect('index.php?option=com_jedchecker&view=uploads', Text::_($message));
			$this->setRedirect('index.php?option=com_jedchecker&view=uploads');
		}
	}
}
