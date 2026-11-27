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
 * Test stub of Joomla's LanguageHelper, implementing only parseIniFile().
 */
class LanguageHelper
{
    public static function parseIniFile($fileName, $debug = false): array
    {
        $strings = @parse_ini_file($fileName, false, INI_SCANNER_RAW);

        return \is_array($strings) ? $strings : [];
    }
}
