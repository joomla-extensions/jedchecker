<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2011 - 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public Licence version 2 or later; see LICENCE.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Rule\Rules;

use Joomla\CMS\Language\Text;
use Joomla\Component\Jedchecker\Administrator\Helper\CheckerHelper;
use Joomla\Component\Jedchecker\Administrator\Rule\AbstractRule;
use SimpleXMLElement;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * XmlFilesRule validates that all files and folders declared in XML manifests actually exist.
 *
 * @since  3.0.0
 */
class XmlFilesRule extends AbstractRule
{
    /**
     * Rule ordering.
     *
     * @var integer
     * @since 3.0.0
     */
    public static int $ordering = 300;
    /**
     * Rule ID.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $id = 'XMLFILES';
    /**
     * Rule title.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $title = 'COM_JEDCHECKER_XML_FILES';
    /**
     * Description of the rule.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $description = 'COM_JEDCHECKER_XML_FILES_DESC';
    /**
     * List of errors.
     *
     * @var array
     * @since 3.0.0
     */
    protected array $errors;
    /**
     * List of warnings.
     *
     * @var array
     * @since 3.0.0
     */
    protected array $warnings;

    /**
     * Directory of the manifest.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $manifestDir;

    /**
     * check
     *
     * Runs the rule.
     *
     * @since  3.0.0
     */
    public function check(): void
    {
        $files = CheckerHelper::findManifests($this->basedir);

        foreach ($files as $file) {
            $this->find($file);
        }
    }

    /**
     * find
     *
     * Finds and processes a manifest file.
     *
     * @param   string  $file
     *
     * @return bool
     *
     * @since  3.0.0
     */
    protected function find(string $file): bool
    {
        $xml = simplexml_load_file($file);

        if (! $xml) {
            return false;
        }

        $this->errors      = [];
        $this->warnings    = [];
        $this->manifestDir = \dirname($file) . '/';

        $sitedir  = '';
        $admindir = '';

        if (isset($xml->files)) {
            $node = $xml->files;
            $this->checkNotEmpty($node);
            $sitedir = $this->getSourceFolder($node);
            $this->checkFiles($node->filename, $sitedir);
            $this->checkFiles($node->file, $sitedir);
            $this->checkFolders($node->folder, $sitedir);
        }

        if (isset($xml->media)) {
            $node = $xml->media;
            $this->checkNotEmpty($node);
            $dir = $this->getSourceFolder($node);
            $this->checkFiles($node->filename, $dir);
            $this->checkFiles($node->file, $dir);
            $this->checkFolders($node->folder, $dir);
        }

        if (isset($xml->fonts)) {
            $node = $xml->fonts;
            $this->checkNotEmpty($node);
            $dir = $this->getSourceFolder($node);
            $this->checkFiles($node->filename, $dir);
            $this->checkFiles($node->file, $dir);
            $this->checkFolders($node->folder, $dir);
        }

        if (isset($xml->languages)) {
            $node = $xml->languages;
            $this->checkNotEmpty($node);
            $dir = $this->getSourceFolder($node);
            $this->checkFiles($node->language, $dir);
        }

        if (isset($xml->administration->files)) {
            $node = $xml->administration->files;
            $this->checkNotEmpty($node);
            $admindir = $this->getSourceFolder($node);
            $this->checkFiles($node->filename, $admindir);
            $this->checkFiles($node->file, $admindir);
            $this->checkFolders($node->folder, $admindir);
        }

        if (isset($xml->administration->media)) {
            $node = $xml->administration->media;
            $this->checkNotEmpty($node);
            $dir = $this->getSourceFolder($node);
            $this->checkFiles($node->filename, $dir);
            $this->checkFiles($node->file, $dir);
            $this->checkFolders($node->folder, $dir);
        }

        if (isset($xml->administration->languages)) {
            $node = $xml->administration->languages;
            $this->checkNotEmpty($node);
            $dir = $this->getSourceFolder($node);
            $this->checkFiles($node->language, $dir);
        }

        if (isset($xml->fileset->files)) {
            $node = $xml->fileset->files;
            $this->checkNotEmpty($node);
            $dir = $this->getSourceFolder($node);
            $this->checkFiles($node->filename, $dir);
            $this->checkFiles($node->file, $dir);
            $this->checkFolders($node->folder, $dir);
        }

        if (isset($xml->api->files)) {
            $node = $xml->api->files;
            $this->checkNotEmpty($node);
            $dir = $this->getSourceFolder($node);
            $this->checkFiles($node->filename, $dir);
            $this->checkFiles($node->file, $dir);
            $this->checkFolders($node->folder, $dir);
        }

        if (isset($xml->scriptfile)) {
            $this->checkFiles($xml->scriptfile);
        }

        if (isset($xml->install->sql->file)) {
            $this->checkFiles($xml->install->sql->file, $admindir);
        }

        if (isset($xml->uninstall->sql->file)) {
            $this->checkFiles($xml->uninstall->sql->file, $admindir);
        }

        if (isset($xml->update->schemas->schemapath)) {
            $this->checkFolders($xml->update->schemas->schemapath, $admindir);
        }

        if (isset($xml->config)) {
            $attributes    = ['addfieldpath', 'addformpath', 'addrulepath'];
            $element       = CheckerHelper::getElementName($xml);
            $extensionPath = false;
            $type          = (string)$xml['type'];

            switch ($type) {
                case 'module':
                    $extensionPath = 'modules/' . $element . '/';
                    break;
                case 'plugin':
                    $group         = (string)$xml['group'];
                    $extensionPath = 'plugins/' . $group . '/' . $element . '/';
                    break;
                case 'template':
                    $extensionPath = 'templates/' . $element . '/';
            }

            if ($extensionPath !== false) {
                foreach ($attributes as $attribute) {
                    foreach ($xml->config->xpath('//*[@' . $attribute . ']') as $node) {
                        $attrPath = (string)$node[$attribute];
                        $folder   = ltrim($attrPath, '/');

                        if (strpos($folder, $extensionPath) === 0) {
                            $folder = $this->manifestDir . $sitedir . substr($folder, \strlen($extensionPath));

                            if (! is_dir($folder)) {
                                $this->errors[] = Text::sprintf('COM_JEDCHECKER_XML_FILES_FOLDER_NOT_FOUND', $attrPath);
                            }
                        }
                    }
                }
            }
        }

        if (isset($xml->namespace['path'])) {
            $folder = (string)$xml->namespace['path'];

            if (! is_dir($this->manifestDir . $admindir . $folder) && ! is_dir($this->manifestDir . $sitedir . $folder)) {
                $this->errors[] = Text::sprintf('COM_JEDCHECKER_XML_FILES_FOLDER_NOT_FOUND', $folder);
            }
        }

        if (\count($this->errors)) {
            $this->report->addError($file, implode('<br />', $this->errors));
        }

        if (\count($this->warnings)) {
            $this->report->addWarning($file, implode('<br />', $this->warnings));
        }

        return true;
    }

    /**
     * checkNotEmpty
     *
     * Checks if a SimpleXMLElement node has any child elements.
     *
     * @param   \SimpleXMLElement  $node
     *
     * @since  3.0.0
     */
    protected function checkNotEmpty(\SimpleXMLElement $node): void
    {
        if (\count($node->children()) === 0) {
            $path = [];

            foreach ($node->xpath("ancestor-or-self::*") as $p) {
                $path[] = $p->getName();
            }

            $this->warnings[] = Text::sprintf('COM_JEDCHECKER_XML_FILES_EMPTY_LIST', implode('/', $path));
        }
    }

    /**
     * getSourceFolder
     *
     * Retrieves the source folder for a SimpleXMLElement node.
     *
     * @param   \SimpleXMLElement  $node
     *
     * @return string
     *
     * @since  3.0.0
     */
    protected function getSourceFolder(\SimpleXMLElement $node): string
    {
        if (! isset($node['folder'])) {
            return '';
        }

        $folder = (string)$node['folder'];

        if (is_dir($this->manifestDir . $folder)) {
            return $folder . '/';
        }

        $this->warnings[] = Text::sprintf('COM_JEDCHECKER_XML_FILES_FOLDER_NOT_FOUND', $folder);

        return '';
    }

    /**
     * checkFiles
     *
     * Checks if files exist in the manifest directory and handles compressed files.
     *
     * @param           $files
     * @param   string  $dir
     *
     * @since  3.0.0
     */
    protected function checkFiles($files, string $dir = ''): void
    {
        foreach ($files as $file) {
            $filename = $this->manifestDir . $dir . $file;

            if (is_file($filename)) {
                continue;
            }

            if (
                preg_match('/^(.*)\.(?:zip|tar|tgz|tbz2|tar\.(?:gz|gzip|bz2|bzip2))$/', $filename, $matches) && is_dir(
                    $matches[1]
                )
            ) {
                continue;
            }

            $this->errors[] = Text::sprintf('COM_JEDCHECKER_XML_FILES_FILE_NOT_FOUND', $dir . $file);
        }
    }

    /**
     * checkFolders
     *
     * Checks if folders exist in the manifest directory.
     *
     * @param           $folders
     * @param   string  $dir
     *
     * @since  3.0.0
     */
    protected function checkFolders($folders, string $dir = ''): void
    {
        foreach ($folders as $folder) {
            if (! is_dir($this->manifestDir . $dir . $folder)) {
                $this->errors[] = Text::sprintf('COM_JEDCHECKER_XML_FILES_FOLDER_NOT_FOUND', $dir . $folder);
            }
        }
    }
}
