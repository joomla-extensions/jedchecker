<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\CMS\Filter;

/**
 * Test stub of Joomla's InputFilter, implementing only the "cmd" filter CheckerHelper uses
 * (the same rules as Joomla\Filter\InputFilter::cleanCmd()).
 */
class InputFilter
{
    public static function getInstance(): self
    {
        return new self();
    }

    public function clean($source, $type = 'string')
    {
        if (strtolower($type) !== 'cmd') {
            throw new \LogicException('The InputFilter test stub only implements the "cmd" filter');
        }

        return ltrim((string) preg_replace('/[^A-Z0-9_\.-]/i', '', (string) $source), '.');
    }
}
