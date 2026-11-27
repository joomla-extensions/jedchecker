<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jedchecker\Tests\Unit\Rule;

use Joomla\Component\Jedchecker\Administrator\Rule\Rules\XmlManifestRule;
use Joomla\Component\Jedchecker\Tests\Unit\RuleTestCase;

class XmlManifestRuleTest extends RuleTestCase
{
    private const HEADER = '<?xml version="1.0" encoding="utf-8"?>' . "\n";

    private const DETAILS = '<name>Foo</name><author>Me</author><authorEmail>me@example.com</authorEmail>'
        . '<authorUrl>https://example.com</authorUrl><creationDate>2026-01</creationDate>'
        . '<copyright>(C) Me</copyright><version>1.0.0</version><description>Foo</description>';

    public function testValidComponentManifestHasNoErrorsOrWarnings(): void
    {
        $this->createFiles([
            'com_foo.xml' => self::HEADER . '<extension type="component" method="upgrade">' . self::DETAILS
                . '<namespace path="src">Foo\\Component\\Foo</namespace>'
                . '<files folder="site"><folder>src</folder></files>'
                . '<administration><menu>COM_FOO</menu><files folder="admin"><folder>src</folder></files></administration>'
                . '</extension>',
        ]);

        $data = $this->runRule(XmlManifestRule::class);

        $this->assertSame([], self::issues($data, 'error'));
        $this->assertSame([], self::issues($data, 'warning'));
    }

    public function testReportsUnknownExtensionType(): void
    {
        $this->createFiles(['foo.xml' => self::HEADER . '<extension type="widget" method="upgrade">' . self::DETAILS . '</extension>']);

        $errors = self::issues($this->runRule(XmlManifestRule::class), 'error', 'foo.xml');

        $this->assertSame(['COM_JEDCHECKER_MANIFEST_UNKNOWN_TYPE[widget]'], array_column($errors, 'text'));
    }

    public function testWarnsAboutMissingUpgradeMethod(): void
    {
        $this->createFiles(['foo.xml' => self::HEADER . '<extension type="component">' . self::DETAILS . '</extension>']);

        $this->assertHasMessage(
            'COM_JEDCHECKER_MANIFEST_MISSED_METHOD_UPGRADE',
            self::issues($this->runRule(XmlManifestRule::class), 'warning', 'foo.xml')
        );
    }

    public function testReportsMissingRequiredChildren(): void
    {
        $this->createFiles(['foo.xml' => self::HEADER . '<extension type="component" method="upgrade"><author>Me</author></extension>']);

        $errors = self::issues($this->runRule(XmlManifestRule::class), 'error', 'foo.xml');

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('COM_JEDCHECKER_MANIFEST_MISSED_REQUIRED[extension|name]', $errors[0]['text']);
        $this->assertStringContainsString('COM_JEDCHECKER_MANIFEST_MISSED_REQUIRED[extension|version]', $errors[0]['text']);
    }

    public function testReportsModuleClientProblems(): void
    {
        $this->createFiles([
            'missing/mod_a.xml' => self::HEADER . '<extension type="module" method="upgrade">' . self::DETAILS
                . '<files><filename module="mod_a">mod_a.php</filename></files></extension>',
            'wrong/mod_b.xml' => self::HEADER . '<extension type="module" client="frontend" method="upgrade">' . self::DETAILS
                . '<files><filename module="mod_b">mod_b.php</filename></files></extension>',
        ]);

        $data = $this->runRule(XmlManifestRule::class);

        $this->assertHasMessage('COM_JEDCHECKER_MANIFEST_MISSED_ATTRIBUTE[extension|client]', self::issues($data, 'error', 'missing/mod_a.xml'));
        $this->assertHasMessage(
            'COM_JEDCHECKER_MANIFEST_UNKNOWN_ATTRIBUTE_VALUE[extension|client|frontend]',
            self::issues($data, 'error', 'wrong/mod_b.xml')
        );
    }

    public function testReportsPluginWithoutElement(): void
    {
        $this->createFiles([
            'plg_foo.xml' => self::HEADER . '<extension type="plugin" group="system" method="upgrade">' . self::DETAILS
                . '<files><filename>foo.php</filename></files></extension>',
        ]);

        $this->assertHasMessage(
            'COM_JEDCHECKER_MANIFEST_MISSED_ELEMENT_ATTRIBUTE[plugin]',
            self::issues($this->runRule(XmlManifestRule::class), 'error', 'plg_foo.xml')
        );
    }

    public function testReportsIncompletePackageItems(): void
    {
        $this->createFiles([
            'pkg_foo.xml' => self::HEADER . '<extension type="package" method="upgrade">' . self::DETAILS
                . '<packagename>foo</packagename><files>'
                . '<file type="component" id="com_foo">com_foo.zip</file>'
                . '<file type="plugin" id="foo">plg_foo.zip</file>'
                . '<file id="lib_foo">lib_foo.zip</file>'
                . '<file type="gadget" id="gad_foo">gad_foo.zip</file>'
                . '</files></extension>',
        ]);

        $errors = array_column(self::issues($this->runRule(XmlManifestRule::class), 'error', 'pkg_foo.xml'), 'text');

        $this->assertContains('COM_JEDCHECKER_MANIFEST_MISSED_ATTRIBUTE[file|group]', $errors);
        $this->assertContains('COM_JEDCHECKER_MANIFEST_MISSED_ATTRIBUTE[file|type]', $errors);
        $this->assertContains('COM_JEDCHECKER_MANIFEST_UNKNOWN_TYPE[gadget]', $errors);
    }

    public function testNoticesUnknownChildren(): void
    {
        $this->createFiles(['foo.xml' => self::HEADER . '<extension type="component" method="upgrade">' . self::DETAILS . '<colour>red</colour></extension>']);

        $notices = self::issues($this->runRule(XmlManifestRule::class), 'notice', 'foo.xml');

        $this->assertCount(1, $notices);
        $this->assertStringContainsString('COM_JEDCHECKER_MANIFEST_UNKNOWN_CHILD[extension|colour]', $notices[0]['text']);
    }
}
