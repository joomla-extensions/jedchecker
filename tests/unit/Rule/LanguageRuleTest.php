<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jedchecker\Tests\Unit\Rule;

use Joomla\Component\Jedchecker\Administrator\Rule\Rules\LanguageRule;
use Joomla\Component\Jedchecker\Tests\Unit\RuleTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class LanguageRuleTest extends RuleTestCase
{
    private const FILE = 'language/en-GB/en-GB.com_foo.ini';

    protected function setUp(): void
    {
        parent::setUp();

        $this->requireForwardSlashPaths('LanguageRule (language file name detection)');
    }

    public function testValidFileHasNoFindings(): void
    {
        $this->createFiles([self::FILE => "; Comment\n[section]\nCOM_FOO=\"Foo\"\nCOM_FOO_ITEMS=\"%d items\" ; trailing comment\n\nCOM_FOO_TITLE=\"Title: \"\n"]);

        $this->assertSame(0, $this->runRule(LanguageRule::class)['count']['total']);
    }

    public static function brokenLines(): array
    {
        return [
            'hash comment'       => ['# comment', 'error', 'COM_JEDCHECKER_LANG_INCORRECT_COMMENT'],
            'no equals sign'     => ['COM_FOO', 'error', 'COM_JEDCHECKER_LANG_WRONG_LINE'],
            'empty key'          => ['="Foo"', 'error', 'COM_JEDCHECKER_LANG_KEY_EMPTY'],
            'whitespace in key'  => ['COM FOO="Foo"', 'error', 'COM_JEDCHECKER_LANG_KEY_WHITESPACE'],
            'invalid character'  => ['COM_FOO!="Foo"', 'error', 'COM_JEDCHECKER_LANG_KEY_INVALID_CHARACTER'],
            'reserved word'      => ['yes="Yes"', 'error', 'COM_JEDCHECKER_LANG_KEY_RESERVED'],
            'lowercase key'      => ['com_foo="Foo"', 'warning', 'COM_JEDCHECKER_LANG_KEY_NOT_UPPERCASE'],
            'unquoted value'     => ['COM_FOO=Foo', 'error', 'COM_JEDCHECKER_LANG_TRANSLATION_ERROR'],
            'unescaped quote'    => ['COM_FOO="Say "hi""', 'error', 'COM_JEDCHECKER_LANG_TRANSLATION_ERROR'],
            'empty value'        => ['COM_FOO=""', 'notice', 'COM_JEDCHECKER_LANG_TRANSLATION_EMPTY'],
            'escaped dollar'     => ['COM_FOO="Costs \\$5"', 'warning', 'COM_JEDCHECKER_LANG_JOOMLA501_BC'],
            'printf argnum'      => ['COM_FOO="%1s of %2s"', 'warning', 'COM_JEDCHECKER_LANG_INCORRECT_ARGNUM'],
            'surrounding spaces' => ['COM_FOO=" Foo"', 'notice', 'COM_JEDCHECKER_LANG_SPACES_AROUND'],
        ];
    }

    #[DataProvider('brokenLines')]
    public function testReportsBrokenLines(string $line, string $level, string $message): void
    {
        $this->createFiles([self::FILE => "COM_FOO_OK=\"OK\"\n" . $line . "\n"]);

        $issues = self::issues($this->runRule(LanguageRule::class), $level, self::FILE);

        $this->assertHasMessage($message, $issues);
        $this->assertSame(2, $issues[0]['line']);
        $this->assertSame($line, $issues[0]['code']);
    }

    public function testReportsDuplicateKeysWithTheFirstLine(): void
    {
        $this->createFiles([self::FILE => "COM_FOO=\"Foo\"\nCOM_BAR=\"Bar\"\nCOM_FOO=\"Foo again\"\n"]);

        $warnings = self::issues($this->runRule(LanguageRule::class), 'warning', self::FILE);

        $this->assertCount(1, $warnings);
        $this->assertSame('COM_JEDCHECKER_LANG_KEY_DUPLICATED[1]', $warnings[0]['text']);
        $this->assertSame(3, $warnings[0]['line']);
    }

    public function testReportsByteOrderMarkAndWindowsLineEndings(): void
    {
        $this->createFiles([self::FILE => "\xEF\xBB\xBFCOM_FOO=\"Foo\"\r\nCOM_BAR=\"Bar\"\r\n"]);

        $data = $this->runRule(LanguageRule::class);

        $this->assertHasMessage('COM_JEDCHECKER_LANG_BOM_FOUND', self::issues($data, 'warning', self::FILE));
        $this->assertHasMessage('COM_JEDCHECKER_LANG_INCORRECT_EOL', self::issues($data, 'notice', self::FILE));
        $this->assertSame([], self::issues($data, 'error'));
    }

    public function testOnlyChecksLanguageFiles(): void
    {
        $this->createFiles(['config/settings.ini' => "lowercase = value\n"]);

        $this->assertSame(0, $this->runRule(LanguageRule::class)['count']['total']);
    }

    public function testReportsUnknownKeysUsedInCode(): void
    {
        $this->createFiles([
            self::FILE             => "COM_FOO_KNOWN=\"Known\"\n",
            'admin/views/view.php' => "<?php\necho Text::_('COM_FOO_KNOWN');\necho Text::_('JSUBMIT');\necho Text::sprintf('COM_FOO_MISSING', 1);\n",
        ]);

        $notices = self::issues($this->runRule(LanguageRule::class), 'notice', 'admin/views/view.php');

        $this->assertCount(1, $notices);
        $this->assertSame('COM_JEDCHECKER_LANG_UNKNOWN_KEY_IN_CODE[COM_FOO_MISSING]', $notices[0]['text']);
        $this->assertSame(4, $notices[0]['line']);
    }
}
