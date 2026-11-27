<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jedchecker\Tests\Unit\Rule;

use Joomla\Component\Jedchecker\Administrator\Rule\Rules\GplRule;
use Joomla\Component\Jedchecker\Tests\Unit\RuleTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class GplRuleTest extends RuleTestCase
{
    public static function gplHeaders(): array
    {
        return [
            'GNU GPL'            => [' * @license    GNU General Public License version 2 or later; see LICENSE.txt'],
            'British spelling'   => [' * @license    GNU General Public Licence version 2 or later; see LICENCE.txt'],
            'GNU/GPL short form' => [' * @license    GNU/GPL'],
            'SPDX identifier'    => [' * @license    GPL-2.0-or-later'],
        ];
    }

    #[DataProvider('gplHeaders')]
    public function testAcceptsGplLicenceHeaders(string $licenceLine): void
    {
        $this->createFiles(['foo.php' => "<?php\n/**\n * @package Foo\n *\n" . $licenceLine . "\n */\n\necho 1;\n"]);

        $data = $this->runRule(GplRule::class);

        $this->assertSame([], self::issues($data, 'error'));
        $passed = self::issues($data, 'passed', 'foo.php');
        $this->assertCount(1, $passed);
        $this->assertSame('COM_JEDCHECKER_PH1_License_FOUND', $passed[0]['text']);
        $this->assertSame(5, $passed[0]['line']);
    }

    public function testWarnsAboutGplCompatibleLicences(): void
    {
        $this->createFiles(['foo.php' => "<?php\n/**\n * @license MIT License\n */\necho 1;\n"]);

        $warnings = self::issues($this->runRule(GplRule::class), 'warning', 'foo.php');

        $this->assertCount(1, $warnings);
        $this->assertSame('COM_JEDCHECKER_GPL_COMPATIBLE_License_WAS_FOUND', $warnings[0]['text']);
    }

    public function testReportsFilesWithoutLicence(): void
    {
        $this->createFiles(['foo.php' => "<?php\n/**\n * @copyright Me\n */\necho 1;\n"]);

        $errors = self::issues($this->runRule(GplRule::class), 'error', 'foo.php');

        $this->assertCount(1, $errors);
        $this->assertSame('COM_JEDCHECKER_ERROR_GPL_NOT_FOUND', $errors[0]['text']);
    }

    public function testSkipsFilesWithoutCode(): void
    {
        $this->createFiles([
            'empty.php' => '',
            'die.php'   => "<?php die;\n",
            'exit.php'  => "<?php\n// comment\nexit();\n",
        ]);

        $this->assertSame([], self::issues($this->runRule(GplRule::class), 'error'));
    }
}
