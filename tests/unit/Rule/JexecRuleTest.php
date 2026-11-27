<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jedchecker\Tests\Unit\Rule;

use Joomla\Component\Jedchecker\Administrator\Rule\Rules\JexecRule;
use Joomla\Component\Jedchecker\Tests\Unit\RuleTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class JexecRuleTest extends RuleTestCase
{
    public static function guardedFiles(): array
    {
        return [
            'plain guard'       => ["<?php\ndefined('_JEXEC') or die;\necho 1;\n"],
            'leading backslash' => ["<?php\n\\defined('_JEXEC') or die;\n"],
            'die with message'  => ["<?php\ndefined('_JEXEC') or die('Restricted access');\n"],
            'exit and ||'       => ["<?php\ndefined(\"_JEXEC\") || exit;\n"],
            'namespace and use' => ["<?php\n\nnamespace Foo\\Bar;\n\nuse Joomla\\CMS\\Factory;\nuse Joomla\\CMS\\Language\\Text as T;\n\n\\defined('_JEXEC') or die;\n"],
            'group use'         => ["<?php\nnamespace Foo;\nuse Joomla\\CMS\\{Factory, Language\\Text};\ndefined('_JEXEC') or die;\n"],
            'strict types'      => ["<?php\ndeclare(strict_types=1);\nnamespace Foo;\ndefined('_JEXEC') or die;\n"],
            'comments first'    => ["<?php\n/**\n * @package Foo\n */\n// comment\ndefined('_JEXEC') or die;\n"],
            'chained defined'   => ["<?php\ndefined('_JEXEC') or defined('JPATH_BASE') or die;\n"],
            'other constant'    => ["<?php\ndefined('JPATH_PLATFORM') or die;\n"],
            'empty php file'    => ["<?php\n"],
            'empty file'        => [''],
            'byte order mark'   => ["\xEF\xBB\xBF<?php\ndefined('_JEXEC') or die;\n"],
        ];
    }

    #[DataProvider('guardedFiles')]
    public function testAcceptsGuardedFiles(string $content): void
    {
        $this->createFiles(['foo.php' => $content]);

        $data = $this->runRule(JexecRule::class);

        $this->assertSame([], self::issues($data, 'error'));
    }

    public static function unguardedFiles(): array
    {
        return [
            'no guard'          => ["<?php\necho 1;\n"],
            'guard after code'  => ["<?php\necho 1;\ndefined('_JEXEC') or die;\n"],
            'unknown constant'  => ["<?php\ndefined('MY_CONST') or die;\n"],
            'guard without die' => ["<?php\ndefined('_JEXEC');\n"],
        ];
    }

    #[DataProvider('unguardedFiles')]
    public function testReportsFilesWithoutGuard(string $content): void
    {
        $this->createFiles(['foo.php' => $content]);

        $errors = self::issues($this->runRule(JexecRule::class), 'error', 'foo.php');

        $this->assertCount(1, $errors);
        $this->assertHasMessage('COM_JEDCHECKER_ERROR_JEXEC_NOT_FOUND', $errors);
    }

    public function testSkipsLibraryFolders(): void
    {
        $this->createFiles([
            'vendor/lib/Foo.php'             => "<?php\nclass Foo {}\n",
            'admin/libs/thing/composer.json' => '{}',
            'admin/libs/thing/Bar.php'       => "<?php\nclass Bar {}\n",
            'admin/helper.php'               => "<?php\nclass Helper {}\n",
        ]);

        $errors = self::issues($this->runRule(JexecRule::class), 'error');

        $this->assertSame(['admin/helper.php'], array_map(
            static fn (array $issue): string => str_replace('\\', '/', $issue['location']),
            $errors
        ));
    }

    public function testChecksOnlyPhpFiles(): void
    {
        $this->createFiles(['foo.js' => 'alert(1);', 'foo.html' => '<p>x</p>']);

        $this->assertSame(0, $this->runRule(JexecRule::class)['count']['total']);
    }
}
