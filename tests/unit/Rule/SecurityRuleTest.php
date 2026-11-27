<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jedchecker\Tests\Unit\Rule;

use Joomla\Component\Jedchecker\Administrator\Rule\Rules\SecurityRule;
use Joomla\Component\Jedchecker\Tests\Unit\RuleTestCase;

class SecurityRuleTest extends RuleTestCase
{
    public function testReportsExecutablesAndShellScripts(): void
    {
        $this->createFiles([
            'bin/tool.exe'    => 'MZ',
            'lib/native.DLL'  => 'MZ',
            'scripts/run.sh'  => "echo 1\n",
            'scripts/run'     => "#!/bin/sh\necho 1\n",
            'media/style.css' => 'body {}',
        ]);

        $data = $this->runRule(SecurityRule::class);

        $this->assertHasMessage('COM_JEDCHECKER_ERROR_SECURITY_EXECUTABLE_FILE', self::issues($data, 'warning', 'bin/tool.exe'));
        $this->assertHasMessage('COM_JEDCHECKER_ERROR_SECURITY_EXECUTABLE_FILE', self::issues($data, 'warning', 'lib/native.DLL'));
        $this->assertHasMessage('COM_JEDCHECKER_ERROR_SECURITY_SHELL_SCRIPT', self::issues($data, 'warning', 'scripts/run.sh'));
        $this->assertHasMessage('COM_JEDCHECKER_ERROR_SECURITY_SHEBANG_FILE', self::issues($data, 'warning', 'scripts/run'));
        $this->assertSame([], self::issues($data, 'warning', 'media/style.css'));
    }

    public function testReportsEncodedCode(): void
    {
        $this->createFiles([
            'encoded.php' => "<?php //0046a\nif(!extension_loaded('ionCube Loader')){die('The file was encoded by ionCube');}\n",
            'sg.php'      => "<?php\nsg_load('abc');\n",
            'clean.php'   => "<?php\n\$sgx_loader = 1;\n",
        ]);

        $data = $this->runRule(SecurityRule::class);

        $this->assertHasMessage('COM_JEDCHECKER_ERROR_SECURITY_OBFUSCATED_CODE[ionCube]', self::issues($data, 'error', 'encoded.php'));
        $this->assertHasMessage('COM_JEDCHECKER_ERROR_SECURITY_OBFUSCATED_CODE[sg_load]', self::issues($data, 'error', 'sg.php'));
        $this->assertSame([], self::issues($data, 'error', 'clean.php'));
    }

    public function testReportsBadCharactersInFileNames(): void
    {
        $this->createFiles(['images/my image.png' => 'x', 'images/a&b.png' => 'x', 'images/ok-name_1.png' => 'x']);

        $data = $this->runRule(SecurityRule::class);

        $this->assertHasMessage('COM_JEDCHECKER_ERROR_SECURITY_BAD_FILENAME_CHAR[space]', self::issues($data, 'notice', 'images/my image.png'));
        $this->assertHasMessage('COM_JEDCHECKER_ERROR_SECURITY_BAD_FILENAME_CHAR[&amp;]', self::issues($data, 'notice', 'images/a&b.png'));
        $this->assertSame([], self::issues($data, 'notice', 'images/ok-name_1.png'));
    }
}
