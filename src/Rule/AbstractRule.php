<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 *              eaxs <support@projectfork.net>
 * @copyright  Copyright (C) 2017 - 2026 Open Source Matters, Inc. All rights reserved.
 *             Copyright (C) 2008 - 2016 compjoom.com All rights reserved.
 *
 * @license    GNU General Public Licence version 2 or later; see LICENCE.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Rule;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\Component\Jedchecker\Administrator\Report\Report;
use Joomla\Registry\Registry;
use ReflectionClass;

/**
 * AbstractRule is the base class for all JEDChecker rules.
 *
 * @since  3.0.0
 */
abstract class AbstractRule
{
    /**
     * Rule ordering.
     *
     * @var integer Rule sort order
     * @since 3.0.0
     */
    public static int $ordering = 10000;
    /**
     * Rule ID.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $id = '';
    /**
     * Rule title.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $title = '';
    /**
     * Description of the rule.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $description = '';
    /**
     * Base directory of the JEDChecker installation.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $basedir;

    /**
     * Parameters for the rule.
     *
     * @var Registry
     * @since 3.0.0
     */
    protected Registry $params;

    /**
     * Report object for storing rule results.
     *
     * @var Report
     * @since 3.0.0
     */
    protected Report $report;

    /**
     * check
     *
     * Abstract method to be implemented by each rule.
     * This method should perform the actual rule check and store the results in the report.
     * 1. Check the JED file for errors
     * 2. Check the JED file for warnings
     * 3. Check the JED file for notices
     * 4. Check the JED file for compatibility issues
     *
     * @since  3.0.0
     */
    abstract public function check(): void;

    /**
     * getDescription
     *
     * Returns the rule description.
     *
     * @return string
     *
     * @since  3.0.0
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * getId
     *
     * Returns the rule ID.
     *
     * @return string
     *
     * @since  3.0.0
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * getReport
     *
     * Returns the rule report.
     *
     * @return Report
     *
     * @since  3.0.0
     */
    public function getReport(): Report
    {
        return $this->report;
    }

    /**
     * getTitle
     *
     * Returns the rule title.
     *
     * @return string
     *
     * @since  3.0.0
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * Constructor.
     *
     * @param   string         $basedir
     * @param   Registry|null  $params
     *
     * @since   3.0.0
     */
    public function __construct(string $basedir = '', ?Registry $params = null)
    {
        $this->basedir = $basedir;
        $this->report  = new Report();

        if ($params !== null) {
            $this->params = $params;
        } else {
            $this->params = $this->loadParams();
        }
    }

    /**
     * loadParams
     *
     * Loads the rule parameters from an INI file.
     *
     * @return Registry
     *
     * @since  3.0.0
     */
    protected function loadParams(): Registry
    {
        $reflect   = new ReflectionClass($this);
        $shortName = strtolower(preg_replace('/Rule$/', '', $reflect->getShortName()));

        $paramsFile = __DIR__ . '/Rules/data/' . $shortName . '.ini';

        $params = new Registry('jedchecker.rule . ' . $shortName);

        if (file_exists($paramsFile)) {
            $data = file_get_contents($paramsFile);

            if ($data) {
                $obj = (object)parse_ini_string($data);

                if (is_object($obj)) {
                    $params->loadObject($obj);
                }
            }
        }

        return $params;
    }
}
