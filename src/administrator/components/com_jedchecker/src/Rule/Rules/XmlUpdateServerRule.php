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

use Joomla\CMS\Language\Text;
use Joomla\Component\Jedchecker\Administrator\Helper\CheckerHelper;
use Joomla\Component\Jedchecker\Administrator\Rule\AbstractRule;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

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
            $xml = CheckerHelper::loadXml($file);

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
            $xml = CheckerHelper::loadXml($file);

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

                // Inside the component's folder, not merely a folder whose name starts the same
                // (".../com_foo2" is not inside ".../com_foo").
                foreach ($componentPaths as $component) {
                    if (
                        $XMLFile['directoryPath'] === $component
                        || str_starts_with($XMLFile['directoryPath'], $component . '/')
                    ) {
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
        $xml = CheckerHelper::loadXml($file);

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

        $valid = true;

        // Report every server, not only up to the first broken one.
        foreach ($xml->updateservers->server as $server) {
            $url = trim((string)$server);

            if (! $this->isHttpUrl($url)) {
                $this->report->addError($file, Text::_('COM_JEDCHECKER_ERROR_XML_UPDATE_SERVER_LINK_NOT_FOUND'), null, $url);
                $valid = false;

                continue;
            }

            $this->report->addPassed(
                $file,
                Text::sprintf(
                    'COM_JEDCHECKER_INFO_XML_UPDATE_SERVER_LINK',
                    htmlspecialchars($url, ENT_QUOTES)
                )
            );
        }

        return $valid;
    }

    /**
     * Whether the update server is an absolute http(s) URL with a host. Before, any text that
     * contained "http" somewhere was accepted.
     *
     * @param   string  $url
     *
     * @return  bool
     *
     * @since   __DEPLOY_VERSION__
     */
    protected function isHttpUrl(string $url): bool
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return \in_array($scheme, ['http', 'https'], true) && (string) parse_url($url, PHP_URL_HOST) !== '';
    }
}
