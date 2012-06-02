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
    public function __construct() {
        $this->path = JPATH_COMPONENT_ADMINISTRATOR . '/tmp/';
        parent::__construct();
    }

    /**
     * basic upload
     * @return bool
     */
    public function upload() {
        $file = JRequest::getVar('extension', '', 'files', 'array');
        if($file['tmp_name']) {
            $path = JPATH_COMPONENT_ADMINISTRATOR . '/tmp/';
            $filepath = $path . strtolower($file['name']);
//          let us remove all previous uplaods
            $folders = JFolder::folders($path);
            foreach($folders as $folder) {
                JFolder::delete($folder);
            }

            $object_file = new JObject($file);
            $object_file->filepath = $filepath;
            $file = (array) $object_file;

//          let us try to upload
            if (!JFile::upload($file['tmp_name'], $file['filepath']))
            {
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
    public function unzip() {

        $file = JFolder::files($this->path);

        $result = JArchive::extract($this->path.$file[0], $this->path);

        return $result;
    }
}
