<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2026 Open Source Matters, Inc. All rights reserved.
 *             Copyright (C) 2008 - 2016 compjoom.com All rights reserved.
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 *             eaxs <support@projectfork.net>
 *
 * @license    GNU General Public Licence version 2 or later; see LICENCE.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Rule\Rules;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;
use Joomla\Component\Jedchecker\Administrator\Helper\CheckerHelper;
use Joomla\Component\Jedchecker\Administrator\Rule\AbstractRule;

/**
 * XmlUpdateServerRule validates update server entries in XML manifests.
 *
 * @since  3.0.0
 */
class XmlUpdateServerRule extends AbstractRule
{
    /**
     * Rule ordering.
     *
     * @var integer
     * @since 3.0.0
     */
    public static int $ordering = 400;
    /**
     * Rule ID.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $id = 'US1';
    /**
     * Rule title.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $title = 'COM_JEDCHECKER_RULE_US1';
    /**
     * Description of the rule.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $description = 'COM_JEDCHECKER_RULE_US1_DESC';

    /**
     * check
     *
     * Runs the rule.
     *
     * @since  3.0.0
     */
    public function check(): void
    {
        $this->report->setDefaultSubtype($this->id);

        $files       = CheckerHelper::findManifests($this->basedir);
        $packageFile = $this->checkPackageXML($files);

        if (! $packageFile) {
            $this->findXMLPaths($files);
        }
    }

    /**
     * checkPackageXML
     *
     * Checks if any of the provided files are package manifests.
     *
     * @param   array  $files
     *
     * @return bool
     *
     * @since  3.0.0
     */
    protected function checkPackageXML(array $files): bool
    {
        $packageCount = 0;

        foreach ($files as $file) {
            $xml = simplexml_load_file($file);

            if ($xml && (string)$xml['type'] === 'package') {
                $packageCount++;
                $this->find($file);
            }
        }

        return $packageCount > 0;
    }

    /**
     * findXMLPaths
     *
     * Finds XML paths within the provided files and categorizes them based on their type.
     *
     * @param   array  $files
     *
     * @since  3.0.0
     */
    protected function findXMLPaths(array $files): void
    {
        $XMLFiles       = [];
        $componentPaths = [];

        foreach ($files as $file) {
            $xml = simplexml_load_file($file);

            if ($xml) {
                $directories = explode('/', substr($file, 0, strrpos($file, '/')));
                $XMLFiles[]  = [
                        'type'          => (string)$xml->attributes()->type,
                        'filepath'      => $file,
                        'directoryPath' => substr($file, 0, strrpos($file, '/')),
                        'directory'     => trim(end($directories)),
                ];

                if ($xml->attributes()->type == 'component') {
                    $componentPaths[] = substr($file, 0, strrpos($file, '/'));
                }
            }
        }

        foreach ($XMLFiles as $XMLFile) {
            if ($XMLFile['type'] == 'component') {
                $this->find($XMLFile['filepath']);
            } else {
                $nested = false;

                foreach ($componentPaths as $component) {
                    if (strpos($XMLFile['directoryPath'], $component) !== false) {
                        $nested = true;
                    }
                }

                if (! $nested) {
                    $this->find($XMLFile['filepath']);
                }
            }
        }
    }

    /**
     * find
     *
     * Checks if the provided XML file contains an update server.
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
            return true;
        }

        if (! isset($xml->updateservers)) {
            $this->report->addError($file, Text::_('COM_JEDCHECKER_ERROR_XML_UPDATE_SERVER_NOT_FOUND'));

            return false;
        }

        if (! isset($xml->updateservers->server)) {
            $this->report->addError($file, Text::_('COM_JEDCHECKER_ERROR_XML_UPDATE_SERVER_NOT_FOUND'));

            return false;
        }

        foreach ($xml->updateservers->server as $server) {
            if (stripos($server, 'http') === false) {
                $this->report->addError($file, Text::_('COM_JEDCHECKER_ERROR_XML_UPDATE_SERVER_LINK_NOT_FOUND'));

                return false;
            }

            $this->report->addPassed(
                $file,
                Text::sprintf('COM_JEDCHECKER_INFO_XML_UPDATE_SERVER_LINK', (string)$server)
            );
        }

        return true;
    }
}
