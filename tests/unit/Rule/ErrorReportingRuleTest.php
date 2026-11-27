<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jedchecker\Tests\Unit\Rule;

use Joomla\Component\Jedchecker\Administrator\Rule\Rules\ErrorReportingRule;
use Joomla\Component\Jedchecker\Tests\Unit\RuleTestCase;

class ErrorReportingRuleTest extends RuleTestCase
{
    public function testReportsDisabledErrorReporting(): void
    {
        $this->createFiles(['foo.php' => "<?php\n\nerror_reporting(0);\necho 1;\n"]);

        $warnings = self::issues($this->runRule(ErrorReportingRule::class), 'warning', 'foo.php');

        $this->assertCount(1, $warnings);
        $this->assertSame('COM_JEDCHECKER_ERROR_ERRORREPORTING', $warnings[0]['text']);
        $this->assertSame(3, $warnings[0]['line']);
        $this->assertSame('error_reporting(0);', $warnings[0]['code']);
    }

    public function testIgnoresOtherLevelsStringsAndComments(): void
    {
        $this->createFiles([
            'foo.php' => "<?php\nerror_reporting(E_ALL);\n\$s = 'error_reporting(0)';\n// error_reporting(0);\n",
        ]);

        $this->assertSame([], self::issues($this->runRule(ErrorReportingRule::class), 'warning'));
    }
}
