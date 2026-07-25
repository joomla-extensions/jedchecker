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

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\Adapter\ComponentAdapter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Folder;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Class Com_JedcheckerInstallerScript
 *
 * @since  1.5
 */
class Com_JedcheckerInstallerScript
{
    protected $extension  = 'com_jedchecker';
    protected $min_php    = '8.1.0';
    protected $min_joomla = '4.3.0';
    protected $parent;

    /**
     * Function executed before the the installation
     *
     * @param   string            $type    - the installation type
     * @param   ComponentAdapter  $parent  - the parent class
     */
    public function preflight($type, $parent)
    {
        $this->parent = $parent;

        if (version_compare(PHP_VERSION, $this->min_php, '<')) {
            $this->loadLanguage();

            $msg = Text::sprintf('COM_JEDCHECKER_PHP_VERSION_INCOMPATIBLE', PHP_VERSION, $this->min_php);
            Log::add($msg, Log::WARNING, 'jerror');

            return false;
        }

        if (version_compare(JVERSION, $this->min_joomla, '<')) {
            $this->loadLanguage();

            $msg = Text::sprintf('COM_JEDCHECKER_JOOMLA_VERSION_INCOMPATIBLE', JVERSION, $this->min_joomla);
            Log::add($msg, Log::WARNING, 'jerror');

            return false;
        }
    }

    /**
     * Load language necessary during the installation
     *
     * @return void
     */
    public function loadLanguage()
    {
        $extension = $this->extension;
        $jlang     = Factory::getApplication()->getLanguage();
        $path      = $this->parent->getParent()->getPath('source') . '/administrator/components/' . $extension;
        $jlang->load($extension, $path, 'en-GB', true);
        $jlang->load($extension, $path, $jlang->getDefault(), true);
        $jlang->load($extension, $path, null, true);
        $jlang->load($extension . '.sys', $path, 'en-GB', true);
        $jlang->load($extension . '.sys', $path, $jlang->getDefault(), true);
        $jlang->load($extension . '.sys', $path, null, true);
    }

    /**
     * Update cleans out legacy directories and old rules.
     *
     * @param   ComponentAdapter  $parent  Is the class calling this method.
     *
     * @return  bool|null  If this returns false, Joomla will abort the update and undo everything already done.
     */
    public function update($parent)
    {
        $this->loadLanguage();

        $componentPath = JPATH_ADMINISTRATOR . '/components/' . $this->extension;

        // Remove legacy top-level files replaced by src/ structure
        $legacyFiles = [
                $componentPath . '/controller.php',
                $componentPath . '/jedchecker.php',
        ];

        foreach ($legacyFiles as $legacyFile) {
            if (file_exists($legacyFile)) {
                File::delete($legacyFile);
            }
        }

        // Remove legacy directories replaced by src/ structure
        $legacyDirs = [
                $componentPath . '/controllers',
                $componentPath . '/models',
                $componentPath . '/libraries',
                $componentPath . '/views',
        ];

        foreach ($legacyDirs as $legacyDir) {
            if (is_dir($legacyDir)) {
                Folder::delete($legacyDir);
            }
        }

        // Remove any old individual rules that are no longer included
        $oldRules = ['htmlindexes'];

        foreach ($oldRules as $rule) {
            $rulePhpFile = $componentPath . '/src/Rule/Rules/' . ucfirst($rule) . 'Rule.php';

            if (file_exists($rulePhpFile)) {
                File::delete($rulePhpFile);
            }
        }
    }
}
