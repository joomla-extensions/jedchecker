<?php

require_once "phing/Task.php";

class listJPackageFilesTask extends Task
{

    public $file;
    public $type = 'component';

    public function setType($type) {
        $this->type = $type;
    }

    public function setFile($str)
    {
        $this->file = $str;
    }

    public function setSourceDir($dir)
    {
        $this->sourceDir = $dir;
    }

    public function setComponent($name)
    {
        $this->component = $name;
    }

    /**
     * The init method: Do init steps.
     */
    public function init()
    {
        // nothing to do here
    }

    /**
     * The main entry point method.
     */
    public function main()
    {
        $content = file_get_contents($this->file);

        $content = preg_replace_callback('/##PACKAGEFILESPLUGIN##/', 'self::findPluginPackageFiles', $content);

        if (preg_match('/##PACKAGEFILESMODULE##/', $content)) {
            $content = preg_replace('/##PACKAGEFILESMODULE##/',
                call_user_func('self::findModulePackageFiles'), $content);
        }

        if (preg_match('/##ADMINLANGUAGEFILES##/', $content)) {
            $content = preg_replace('/##ADMINLANGUAGEFILES##/',
                call_user_func('self::languageFiles', true), $content);
        }

        if (preg_match('/##FRONTENDLANGUAGEFILES##/', $content)) {
            $content = preg_replace('/##FRONTENDLANGUAGEFILES##/',
                call_user_func('self::languageFiles', false), $content);
        }

        if (preg_match('/##ADMINCOMPONENTPACKAGEFILES##/', $content)) {
            $content = preg_replace('/##ADMINCOMPONENTPACKAGEFILES##/',
                call_user_func('self::findComponentPackagefiles', true), $content);
        }


        if (preg_match('/##FRONTENDCOMPONENTPACKAGEFILES##/', $content)) {
            $content = preg_replace('/##FRONTENDCOMPONENTPACKAGEFILES##/',
                call_user_func('self::findComponentPackagefiles', false), $content);
        }

        if (preg_match('/##MEDIAPACKAGEFILES##/', $content)) {
            $content = preg_replace('/##MEDIAPACKAGEFILES##/',
                call_user_func('self::findMediaPackagefiles', false), $content);
        }

        file_put_contents($this->file, $content);
    }

    public function languageFiles($admin = false)
    {

        if($this->type == 'module') {
            $this->sourceDir = $this->sourceDir . '/../../';
        }
        if($this->type == 'plugin') {
            $this->sourceDir = $this->sourceDir . '/../../../';
        }
        $languageFolder = $this->sourceDir . '/language';
        if ($admin) {
            $languageFolder = $this->sourceDir . '/administrator/language';
        }
        $list = array();
        if (file_exists($languageFolder)) {
            $dir = new DirectoryIterator($languageFolder);

            foreach ($dir as $element) {
                if (!$element->isDot()) {
                    if ($element->isDir()) {
                        $langDir = new DirectoryIterator($element->getPath() . '/' . $element->getFileName());

                        foreach ($langDir as $langElement) {
                            if (!$langElement->isDot()) {
                                if ($langElement->isFile()) {
                                    if ($this->component) {
//	                                    if(strstr($this->component,'sys'))
	                                    $name = explode('.', $langElement->getFileName());
	                                    $name = $name[1];
                                        if ($name == $this->component) {
                                            $list[] = '<language tag="' . $element->getFileName() . '">'
                                                . $element->getFileName() . '/' . $langElement->getFileName() . '</language>';
                                        }
                                    }
//                                    else {
//                                        $list[] = '<language tag="' . $element->getFileName() . '">'
//                                            . $element->getFileName() . '/' . $langElement->getFileName() . '</language>';
//                                    }
                                    //                                    $packageMainFile = basename($this->file, '.xml');
                                    //                                if ($element->getFileName() == $packageMainFile . '.php') {
                                    //                                     . $element->getFileName() . '/' . $langElement->getFileName() . '</language>';
                                }
                            }
                        }
                    }
                }
            }
        } else {
            echo 'Folder ' . $languageFolder . ' doesn\'t exist';
        }

        return implode("\n", $list);
    }

    public function findComponentPackagefiles($admin = false)
    {
        $list = array();
        $componentFolder = $this->sourceDir . '/components/' . $this->component;
        if ($admin) {
            $componentFolder = $this->sourceDir . '/administrator/components/' . $this->component;
        }

        if (file_exists($componentFolder)) {
            $dir = new DirectoryIterator($componentFolder);
            foreach ($dir as $element) {
                if (!$element->isDot()) {
                    if ($element->isDir()) {
                        $list[] = '<folder>' . $element->getFileName() . '</folder>';
                    }
                    if ($element->isFile()) {
                        $list[] = '<file>' . $element->getFileName() . '</file>';
                    }
                }

            }
        } else {
            echo 'Folder ' . $componentFolder . ' doesn\'t exist';
        }

        return implode("\n", $list);
    }

	public function findMediaPackagefiles()
	{
		$list = array();
		$source = $this->sourceDir;
		if(strstr($source, '/administrator/')) {
			if($this->type == 'module') {
				$source = $source . '..';
			}
		}
		$mediaFolder = $source . '/media/' . $this->component;
		if (file_exists($mediaFolder)) {
			$dir = new DirectoryIterator($mediaFolder);
			foreach ($dir as $element) {
				if (!$element->isDot() && substr($element, 0, 1) != ".") {
					if ($element->isDir()) {
						$list[] = '<folder>' . $element->getFileName() . '</folder>';
					}
					if ($element->isFile()) {
						$list[] = '<file>' . $element->getFileName() . '</file>';
					}
				}

			}

		} else {
			echo 'Folder ' . $mediaFolder . ' doesn\'t exist';
		}
		return implode("\n", $list);
	}

    public function findPluginPackageFiles()
    {
        $list = array();
        if (file_exists($this->sourceDir)) {
            $dir = new DirectoryIterator($this->sourceDir);
            foreach ($dir as $element) {
                if (!$element->isDot()) {
                    if ($element->isDir()) {
                        $skip = false;
                        if ($element->getFileName() == 'administrator') {
                            /**
                             * we need to handle the language folder in the plugin
                             * differently. If the administrator folder contains
                             * just the language folder we don't need to list it.
                             * Otherwise when the user installs the plugin he will have
                             * administrator/language in his plugi folder which is lame...
                             */
                            $adminDir = new DirectoryIterator($this->sourceDir . '/administrator');
                            $i = 0;
                            $language = false;
                            foreach ($adminDir as $adminElement) {
                                if ($adminElement->isDir() && !$adminElement->isDot()) {
                                    if ($adminElement->getFileName() == 'language') {
                                        $language = true;
                                    }
                                    $i++;
                                }
                            }
                            /**
                             * so we have just one folder and it is
                             * the language one???
                             */
                            if ($i == 1 && $language == true) {
                                $skip = true;
                            }
                        }

                        if (!$skip) {
                            $list[] = '<folder>' . $element->getFileName() . '</folder>';
                        }
                    }
                    if ($element->isFile()) {
                        $packageMainFile = basename($this->file, '.xml');
                        if ($element->getFileName() == $packageMainFile . '.php') {
                            $list[] = '<file plugin="' . $packageMainFile . '">' . $element->getFilename() . '</file>';
                        } elseif ($element->getFileName() != basename($this->file)) {
                            $list[] = '<file>' . $element->getFileName() . '</file>';
                        }
                    }
                }
            }
        } else {
            echo 'Folder ' . $this->sourceDir . ' doesn\'t exist';
        }

        return implode("\n", $list);
    }


    public function findModulePackageFiles()
    {
        $list = array();
        if (file_exists($this->sourceDir)) {
            $dir = new DirectoryIterator($this->sourceDir);
            foreach ($dir as $element) {
                if (!$element->isDot()) {
                    if ($element->isDir()) {
                        $skip = false;
                        if ($element->getFileName() == 'administrator') {
                            /**
                             * we need to handle the language folder in the plugin
                             * differently. If the administrator folder contains
                             * just the language folder we don't need to list it.
                             * Otherwise when the user installs the plugin he will have
                             * administrator/language in his plugi folder which is lame...
                             */
                            $adminDir = new DirectoryIterator($this->sourceDir . '/administrator');
                            $i = 0;
                            $language = false;
                            foreach ($adminDir as $adminElement) {
                                if ($adminElement->isDir() && !$adminElement->isDot()) {
                                    if ($adminElement->getFileName() == 'language') {
                                        $language = true;
                                    }
                                    $i++;
                                }
                            }
                            /**
                             * so we have just one folder and it is
                             * the language one???
                             */
                            if ($i == 1 && $language == true) {
                                $skip = true;
                            }
                        }

                        if (!$skip) {
                            $list[] = '<folder>' . $element->getFileName() . '</folder>';
                        }
                    }
                    if ($element->isFile()) {
                        $packageMainFile = basename($this->file, '.xml');
                        if ($element->getFileName() == $packageMainFile . '.php') {
                            $list[] = '<file module="' . $packageMainFile . '">' . $element->getFilename() . '</file>';
                        } elseif ($element->getFileName() != basename($this->file)) {
                            $list[] = '<file>' . $element->getFileName() . '</file>';
                        }
                    }
                }
            }
        } else {
            echo 'Folder ' . $this->sourceDir . ' doesn\'t exist';
        }

        return implode("\n", $list);
    }

}