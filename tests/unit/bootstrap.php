<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

// The unit tests run the rules without a Joomla installation. The framework packages the
// component uses (joomla/filesystem, joomla/archive, joomla/registry) come from Composer; the
// few CMS classes it needs are replaced by the minimal stubs in tests/unit/Stubs.

\define('_JEXEC', 1);

$root = \dirname(__DIR__, 2);

\define('JPATH_ROOT', __DIR__ . '/Fixtures/site');
\define('JPATH_BASE', JPATH_ROOT . '/administrator');
\define('JPATH_SITE', JPATH_ROOT);
// The component's own folder: rules that resolve their data files through JPATH_ADMINISTRATOR
// find them in the repository.
\define('JPATH_ADMINISTRATOR', $root . '/src/administrator');
\define('JVERSION', '6.0.0');

require_once $root . '/vendor/autoload.php';

require_once __DIR__ . '/Stubs/Text.php';
require_once __DIR__ . '/Stubs/InputFilter.php';
require_once __DIR__ . '/Stubs/LanguageHelper.php';
