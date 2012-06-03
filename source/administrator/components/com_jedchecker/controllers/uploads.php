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
jimport('joomla.filesystem.archive');

class jedcheckerControllerUploads extends JController
{
    public function __construct()
    {
        $this->path = JPATH_COMPONENT_ADMINISTRATOR . '/tmp/';
        $this->pathArchive = $this->path . 'arhives/';
        $this->pathUnzipped = $this->path . 'unzipped/';
        parent::__construct();
    }

    /**
     * basic upload
     * @return bool
     */
    public function upload()
    {
        $file = JRequest::getVar('extension', '', 'files', 'array');
        if ($file['tmp_name']) {
            $path = $this->pathArchive;
            $filepath = $path . strtolower($file['name']);
//          let us remove all previous uploads
            $archiveFiles = JFolder::files($path);

            foreach ($archiveFiles as $archive) {
                if (!JFile::delete($this->pathArchive . $archive)) {
                    echo 'could not delete' . $archive;
                }
            }

            $object_file = new JObject($file);
            $object_file->filepath = $filepath;
            $file = (array)$object_file;

//          let us try to upload
            if (!JFile::upload($file['tmp_name'], $file['filepath'])) {
                // Error in upload
                JError::raiseWarning(100, JText::_('COM_JEDCHECKER_ERROR_UNABLE_TO_UPLOAD_FILE'));
                return false;
            }

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

        // let us remove all previous unzipped files
        $folders = JFolder::folders($this->pathUnzipped);
        foreach ($folders as $folder) {
            JFolder::delete($this->pathUnzipped . $folder);
        }


        $file = JFolder::files($this->pathArchive);
        $result = JArchive::extract($this->pathArchive . $file[0], $this->pathUnzipped . $file[0]);

        if ($result) {
            // scan unzipped folders if we find zip file -> unzip them as well
            $this->unzipAll($this->pathUnzipped . $file[0]);
        }

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
                $extension = $file->getExtension();
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
            } else {
                $this->unzipAll($file->getPathname());

            }
        }
    }
}
