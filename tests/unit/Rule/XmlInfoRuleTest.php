<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jedchecker\Tests\Unit\Rule;

use Joomla\Component\Jedchecker\Administrator\Rule\Rules\XmlInfoRule;
use Joomla\Component\Jedchecker\Tests\Unit\RuleTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class XmlInfoRuleTest extends RuleTestCase
{
    private static function manifest(string $name, string $type = 'component', string $extra = '', string $attributes = ''): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>' . "\n"
            . '<extension type="' . $type . '" method="upgrade"' . $attributes . '>'
            . '<name>' . htmlspecialchars($name, ENT_XML1) . '</name><version>1.2.3</version><creationDate>2026-01-01</creationDate>'
            . '<authorUrl>https://example.com</authorUrl>' . $extra . '</extension>';
    }

    public function testReportsMissingManifest(): void
    {
        $this->createFiles(['foo.php' => '<?php']);

        $errors = self::issues($this->runRule(XmlInfoRule::class), 'error');

        $this->assertSame(['COM_JEDCHECKER_INFO_XML_NO_MANIFEST'], array_column($errors, 'text'));
    }

    public function testShowsNameVersionAndDate(): void
    {
        $this->createFiles(['com_foo.xml' => self::manifest('Foo Bar')]);

        $info = self::issues($this->runRule(XmlInfoRule::class), 'info', 'com_foo.xml');

        $this->assertCount(1, $info);
        $this->assertSame(
            'COM_JEDCHECKER_INFO_XML_NAME_XML[Foo Bar]<br />COM_JEDCHECKER_INFO_XML_VERSION_XML[1.2.3]<br />COM_JEDCHECKER_INFO_XML_CREATIONDATE_XML[2026-01-01]',
            $info[0]['text']
        );
    }

    public function testEscapesPackageValues(): void
    {
        $this->createFiles(['com_foo.xml' => self::manifest('<script>alert(1)</script>')]);

        $info = self::issues($this->runRule(XmlInfoRule::class), 'info', 'com_foo.xml');

        $this->assertStringContainsString('&lt;script&gt;', $info[0]['text']);
        $this->assertStringNotContainsString('<script>', $info[0]['text']);
    }

    public function testTranslatesTheNameWithTheExtensionsLanguageFile(): void
    {
        $this->createFiles([
            'com_foo.xml'                          => self::manifest('COM_FOO', 'component', '<administration><files folder="admin"><folder>src</folder></files></administration>'),
            'admin/language/en-GB/com_foo.sys.ini' => "COM_FOO=\"Foo Manager\"\n",
        ]);

        $data = $this->runRule(XmlInfoRule::class);

        $this->assertStringContainsString('NAME_XML[Foo Manager]', self::issues($data, 'info', 'com_foo.xml')[0]['text']);
        $this->assertSame([], self::issues($data, 'notice'));
    }

    public function testNoticesMissingLanguageFile(): void
    {
        $this->createFiles(['plg_foo.xml' => self::manifest('System - Foo', 'plugin', '<files><filename plugin="foo">foo.php</filename></files>', ' group="system"')]);

        $this->assertSame(
            ['COM_JEDCHECKER_INFO_XML_NO_LANGUAGE_FILE_FOUND[plg_system_foo.sys.ini|en-GB]'],
            array_column(self::issues($this->runRule(XmlInfoRule::class), 'notice', 'plg_foo.xml'), 'text')
        );
    }

    public static function badNames(): array
    {
        return [
            'reserved keyword' => ['Foo Module', 'NM3', 'COM_JEDCHECKER_INFO_XML_NAME_RESERVED_KEYWORDS'],
            'free'             => ['Foo Free', 'NM3', 'COM_JEDCHECKER_INFO_XML_NAME_RESERVED_KEYWORDS'],
            'version in name'  => ['Foo 2.0', 'NM5', 'COM_JEDCHECKER_INFO_XML_NAME_VERSION'],
            'starts Joomla'    => ['Joomla Foo', 'TM2', 'COM_JEDCHECKER_INFO_XML_NAME_JOOMLA'],
            'prefixed'         => ['com_foo', 'INFO_XML', 'COM_JEDCHECKER_INFO_XML_NAME_PREFIXED'],
            'non-ASCII'        => ['Föö', 'INFO_XML', 'COM_JEDCHECKER_INFO_XML_NAME_NON_ASCII'],
            'very long'        => [str_repeat('Foo ', 21), 'INFO_XML', 'COM_JEDCHECKER_INFO_XML_NAME_TOO_LONG'],
        ];
    }

    #[DataProvider('badNames')]
    public function testReportsBadNamesAsErrors(string $name, string $subtype, string $message): void
    {
        $this->createFiles(['com_foo.xml' => self::manifest($name)]);

        $errors = array_values(array_filter(
            self::issues($this->runRule(XmlInfoRule::class), 'error', 'com_foo.xml'),
            static fn (array $issue): bool => $issue['subtype'] === $subtype
        ));

        $this->assertHasMessage($message, $errors);
    }

    public function testWarnsAboutDerivedNamesAndLongNames(): void
    {
        $this->createFiles([
            'a/com_a.xml' => self::manifest('Foo Joomla Bar'),
            'b/com_b.xml' => self::manifest('Foo Extension Manager Suite For Your Website'),
        ]);

        $data = $this->runRule(XmlInfoRule::class);

        $this->assertHasMessage('COM_JEDCHECKER_INFO_XML_NAME_JOOMLA_DERIVATIVE', self::issues($data, 'warning', 'a/com_a.xml'));
        $this->assertHasMessage('COM_JEDCHECKER_INFO_XML_NAME_TOO_LONG', self::issues($data, 'warning', 'b/com_b.xml'));
    }

    public function testAcceptsForJoomlaSuffix(): void
    {
        $this->createFiles(['com_foo.xml' => self::manifest('Foo for Joomla')]);

        $data = $this->runRule(XmlInfoRule::class);

        $this->assertSame([], self::issues($data, 'error'));
        $this->assertSame([], self::issues($data, 'warning'));
    }

    public function testReportsJoomlaInTheAuthorDomain(): void
    {
        $this->createFiles([
            'com_foo.xml' => str_replace('https://example.com', 'https://www.myjoomlastuff.com/', self::manifest('Foo')),
        ]);

        $errors = self::issues($this->runRule(XmlInfoRule::class), 'error', 'com_foo.xml');

        $this->assertSame('TM1', $errors[0]['subtype'] ?? null);
    }

    public function testReportsTypesNotAcceptedInTheJed(): void
    {
        $this->createFiles(['tpl_foo.xml' => self::manifest('Foo', 'template', '', ' client="site"')]);

        $this->assertHasMessage(
            'COM_JEDCHECKER_MANIFEST_TYPE_NOT_ACCEPTED[template]',
            self::issues($this->runRule(XmlInfoRule::class), 'error', 'tpl_foo.xml')
        );
    }

    public function testWarnsAboutPluginNamesWithoutGroup(): void
    {
        $this->createFiles([
            'good/plg_a.xml' => self::manifest('System - Foo', 'plugin', '', ' group="system"'),
            'bad/plg_b.xml'  => self::manifest('Foo', 'plugin', '', ' group="content"'),
        ]);

        $data = $this->runRule(XmlInfoRule::class);

        $this->assertSame([], self::issues($data, 'warning', 'good/plg_a.xml'));
        $this->assertHasMessage('COM_JEDCHECKER_INFO_XML_NAME_PLUGIN_FORMAT', self::issues($data, 'warning', 'bad/plg_b.xml'));
    }
}
