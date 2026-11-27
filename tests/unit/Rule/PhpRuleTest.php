<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jedchecker\Tests\Unit\Rule;

use Joomla\Component\Jedchecker\Administrator\Rule\Rules\PhpRule;
use Joomla\Component\Jedchecker\Tests\Unit\RuleTestCase;

class PhpRuleTest extends RuleTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $dataFile = \dirname(__DIR__, 3) . '/src/administrator/components/com_jedchecker/src/Rule/Rules/data/php_deprecated.json';

        if (! is_file($dataFile)) {
            $this->markTestSkipped('The data file of the PHP compatibility rule (php_deprecated.json) is missing.');
        }
    }

    public function testReportsRemovedFunctionsAsErrors(): void
    {
        $this->createFiles(['foo.php' => "<?php\n\$f = create_function('\$a', 'return \$a;');\necho 1;\n"]);

        $errors = self::issues($this->runRule(PhpRule::class), 'error', 'foo.php');

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('COM_JEDCHECKER_ERROR_PHP_REMOVED', $errors[0]['text']);
        $this->assertStringContainsString('<b>create_function</b>', $errors[0]['text']);
        $this->assertStringContainsString('anonymous functions', $errors[0]['text']);
    }

    public function testReportsDeprecatedFunctionsAsWarnings(): void
    {
        $this->createFiles(['foo.php' => "<?php\n\$d = strftime('%Y');\necho 1;\n"]);

        $warnings = self::issues($this->runRule(PhpRule::class), 'warning', 'foo.php');

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('COM_JEDCHECKER_ERROR_PHP_DEPRECATED', $warnings[0]['text']);
    }

    public function testIgnoresNamesInCommentsAndStrings(): void
    {
        $this->createFiles(['foo.php' => "<?php\n// create_function\n\$s = 'strftime';\n\$my_strftime_x = 1;\n"]);

        $this->assertSame(0, $this->runRule(PhpRule::class)['count']['total']);
    }
}
