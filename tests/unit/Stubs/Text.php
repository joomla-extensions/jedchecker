<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\CMS\Language;

/**
 * Test stub of Joomla's Text: no translations, so the language key comes back unchanged, which
 * lets the tests assert which message a rule reported. sprintf() appends its arguments to the key
 * ("KEY[arg1|arg2]") so that they can be asserted, too.
 */
class Text
{
    public static function _($string, $jsSafe = false, $interpretBackSlashes = true, $script = false): string
    {
        return (string) $string;
    }

    public static function sprintf($string, ...$args): string
    {
        return $args === [] ? (string) $string : $string . '[' . implode('|', array_map('strval', $args)) . ']';
    }
}
