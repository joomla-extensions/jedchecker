<?php
/**
 * @author Daniel Dimitrov - compojoom.com
 * @date: 02.06.12
 *
 * @copyright  Copyright (C) 2008 - 2012 compojoom.com . All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');

jimport('joomla.filesystem');
jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.archive');

class jedcheckerControllerUploads extends JControllerlegacy
{
    public function __construct()
    {
        $this->path = JFactory::getConfig()->get('tmp_path') . '/jed_checker';
        $this->pathArchive = $this->path . '/archives';
        $this->pathUnzipped = $this->path . '/unzipped';
        parent::__construct();
    }

    /**
     * basic upload
     * @return bool
     */
    public function upload()
    {
        JRequest::checkToken() or die( 'Invalid Token' );
        $appl = JFactory::getApplication();
        $file = JRequest::getVar('extension', '', 'files', 'array');
        if ($file['tmp_name']) {
            $path = $this->pathArchive;
            // if the archive folder doesn't exist - create it!
            if(!JFolder::exists($path)) {
                JFolder::create($path);
            } else {
            // let us remove all previous uploads
                $archiveFiles = JFolder::files($path);

                foreach ($archiveFiles as $archive) {
                    if (!JFile::delete($this->pathArchive . '/'.$archive)) {
                        echo 'could not delete' . $archive;
                    }
                }
            }
            $filepath = $path .'/'. strtolower($file['name']);


            $object_file = new JObject($file);
            $object_file->filepath = $filepath;
            $file = (array)$object_file;

//          let us try to upload
            if (!JFile::upload($file['tmp_name'], $file['filepath'])) {
                // Error in upload
                JError::raiseWarning(100, JText::_('COM_JEDCHECKER_ERROR_UNABLE_TO_UPLOAD_FILE'));
                return false;
            }
            $appl->redirect('index.php?option=com_jedchecker&view=uploads', JText::_('COM_JEDCHECKER_UPLOAD_WAS_SUCCESSFUL'));
            return true;
        }

        return false;
    }

    /**
     * unzip the file
     * @return bool
     */
    public function unzip()
    {
        JRequest::checkToken() or die( 'Invalid Token' );
        $appl = JFactory::getApplication();
        // if folder doesn't exist - create it!
        if(!JFolder::exists($this->pathUnzipped)) {
            JFolder::create($this->pathUnzipped);
        } else {
            // let us remove all previous unzipped files
            $folders = JFolder::folders($this->pathUnzipped);
            foreach ($folders as $folder) {
                JFolder::delete($this->pathUnzipped .'/'. $folder);
            }
        }

        $file = JFolder::files($this->pathArchive);
        $result = JArchive::extract($this->pathArchive .'/'. $file[0], $this->pathUnzipped .'/'. $file[0]);

        if ($result) {
            // scan unzipped folders if we find zip file -> unzip them as well
            $this->unzipAll($this->pathUnzipped .'/'. $file[0]);
            $message = 'COM_JEDCHECKER_UNZIP_SUCCESS';
        } else {
            $message = 'COM_JEDCHECKER_UNZIP_FAILED';
        }

        $appl->redirect('index.php?option=com_jedchecker&view=uploads', JText::_($message));

        return $result;
    }

    /**
     * Recursively go through each folder and extract the archives
     *
     * @param $start
     */
    public function unzipAll($start)
    {
        $iterator = new RecursiveDirectoryIterator($start);

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $extension = pathinfo($file->getFilename(), PATHINFO_EXTENSION);;
                if ($extension == 'zip') {
                    $unzip = $file->getPath() . '/' . $file->getBasename('.' . $extension);
                    $result = JArchive::extract($file->getPathname(), $unzip);
//                     delete the archive once we extract it
                    if ($result) {
                        JFile::delete($file->getPathname());

//                      now check the new extracted folder for archive files
                        $this->unzipAll($unzip);
                    }
                }
            } else if (!$iterator->isDot()) {
                $this->unzipAll($file->getPathname());

            }
        }
    }
}
