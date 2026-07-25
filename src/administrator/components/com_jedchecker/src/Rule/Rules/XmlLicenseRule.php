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
 * XmlLicenseRule searches all XML manifests for a valid licence tag.
 *
 * @since  3.0.0
 */
class XmlLicenseRule extends AbstractRule
{
    /**
     * Rule ordering.
     *
     * @var integer
     * @since 3.0.0
     */
    public static int $ordering = 100;
    /**
     * Rule ID.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $id = 'PH3';
    /**
     * Rule title.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $title = 'COM_JEDCHECKER_RULE_PH3';
    /**
     * Description of the rule.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $description = 'COM_JEDCHECKER_RULE_PH3_DESC';

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

        $files = CheckerHelper::findManifests($this->basedir);

        foreach ($files as $file) {
            $this->find($file);
        }
    }

    /**
     * find
     *
     * Checks if the XML file contains a valid license.
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

        if (! isset($xml->licence)) {
            $this->report->addError($file, Text::_('COM_JEDCHECKER_ERROR_XML_LICENCE_NOT_FOUND'));

            return false;
        }

        if (
            stripos($xml->licence, 'gpl') === false
            && stripos($xml->licence, 'general public licence') === false
        ) {
            $this->report->addCompat($file, Text::_('COM_JEDCHECKER_ERROR_XML_LICENCE_NOT_GPL'));

            return false;
        }

        return true;
    }
}
