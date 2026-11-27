<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jedchecker\Tests\Unit\Rule;

use Joomla\Component\Jedchecker\Administrator\Rule\Rules\JamssRule;
use Joomla\Component\Jedchecker\Tests\Unit\RuleTestCase;

class JamssRuleTest extends RuleTestCase
{
    public function testCleanFileHasNoFindings(): void
    {
        $this->createFiles(['foo.php' => "<?php\ndefined('_JEXEC') or die;\n\necho htmlspecialchars(\$value);\n"]);

        $this->assertSame(0, $this->runRule(JamssRule::class)['count']['total']);
    }

    public function testReportsObfuscationChainsWithLine(): void
    {
        $this->createFiles(['foo.php' => "<?php\n\$a = 1;\neval(gzinflate(base64_decode(\$payload)));\necho 1;\n"]);

        $warnings = self::issues($this->runRule(JamssRule::class), 'warning', 'foo.php');

        $this->assertHasMessage('COM_JEDCHECKER_ERROR_JAMSS_PATTERN#17', $warnings);

        foreach ($warnings as $warning) {
            if (str_contains($warning['text'], '#17')) {
                $this->assertSame(3, $warning['line']);
                $this->assertSame('eval(gzinflate(base64_decode($payload)));', $warning['code']);
            }
        }
    }

    public function testReportsBackticksInCodeButNotInStrings(): void
    {
        $this->createFiles([
            'exec.php'   => "<?php\n\$out = `ls -la`;\n",
            'string.php' => "<?php\n\$sql = 'SELECT `id` FROM `#__users`';\n",
        ]);

        $data = $this->runRule(JamssRule::class);

        $this->assertHasMessage('COM_JEDCHECKER_ERROR_JAMSS_PATTERN#24', self::issues($data, 'warning', 'exec.php'));
        $this->assertSame([], self::issues($data, 'warning', 'string.php'));
    }

    public function testReportsShellExecutionFromRequestVariables(): void
    {
        $this->createFiles(['foo.php' => "<?php\nsystem(\$_GET['cmd']);\n"]);

        $this->assertHasMessage(
            'COM_JEDCHECKER_ERROR_JAMSS_PATTERN#23',
            self::issues($this->runRule(JamssRule::class), 'warning', 'foo.php')
        );
    }

    public function testReportsKnownMalwareStrings(): void
    {
        $this->createFiles(['foo.php' => "<?php\n// c99shell\n\$x = 'FilesMan';\n"]);

        $warnings = self::issues($this->runRule(JamssRule::class), 'warning', 'foo.php');

        $this->assertHasMessage('COM_JEDCHECKER_ERROR_JAMSS_STRINGFilesMan', $warnings);
    }

    public function testReportsSuspiciousFileNames(): void
    {
        $this->createFiles(['phpinfo.php' => "<?php\nphpinfo();\n"]);

        $this->assertHasMessage(
            'COM_JEDCHECKER_ERROR_JAMSS_SUSPICIOUS_FILENAME',
            self::issues($this->runRule(JamssRule::class), 'warning', 'phpinfo.php')
        );
    }

    public function testScansOnlyConfiguredExtensions(): void
    {
        $this->createFiles(['foo.txt' => "eval(base64_decode(\$x));\n`ls`\n"]);

        $this->assertSame(0, $this->runRule(JamssRule::class)['count']['total']);
    }
}
