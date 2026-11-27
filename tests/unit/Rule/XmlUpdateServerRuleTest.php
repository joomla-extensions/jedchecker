<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jedchecker\Tests\Unit\Rule;

use Joomla\Component\Jedchecker\Administrator\Rule\Rules\XmlUpdateServerRule;
use Joomla\Component\Jedchecker\Tests\Unit\RuleTestCase;

class XmlUpdateServerRuleTest extends RuleTestCase
{
    private static function manifest(string $type, string $updateServers, string $attributes = ''): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>' . "\n"
            . '<extension type="' . $type . '" method="upgrade"' . $attributes . '><name>foo</name><version>1.0.0</version>'
            . $updateServers . '</extension>';
    }

    public function testAcceptsUpdateServerLinks(): void
    {
        $this->createFiles([
            'foo.xml' => self::manifest(
                'component',
                '<updateservers><server type="extension" name="Foo">https://example.com/updates.xml</server></updateservers>'
            ),
        ]);

        $data = $this->runRule(XmlUpdateServerRule::class);

        $this->assertSame([], self::issues($data, 'error'));
        $passed = self::issues($data, 'passed', 'foo.xml');
        $this->assertCount(1, $passed);
        $this->assertSame('COM_JEDCHECKER_INFO_XML_UPDATE_SERVER_LINK[https://example.com/updates.xml]', $passed[0]['text']);
    }

    public function testEscapesTheLinkInTheReport(): void
    {
        $this->createFiles([
            'foo.xml' => self::manifest(
                'module',
                '<updateservers><server type="extension">https://example.com/?a=1&amp;b="&lt;x&gt;"</server></updateservers>',
                ' client="site"'
            ),
        ]);

        $passed = self::issues($this->runRule(XmlUpdateServerRule::class), 'passed', 'foo.xml');

        $this->assertStringContainsString('&amp;b=&quot;&lt;x&gt;&quot;', $passed[0]['text']);
    }

    public function testReportsMissingUpdateServers(): void
    {
        $this->createFiles([
            'none.xml'  => self::manifest('component', ''),
            'empty.xml' => self::manifest('component', '<updateservers></updateservers>'),
        ]);

        $data = $this->runRule(XmlUpdateServerRule::class);

        $this->assertHasMessage('COM_JEDCHECKER_ERROR_XML_UPDATE_SERVER_NOT_FOUND', self::issues($data, 'error', 'none.xml'));
        $this->assertHasMessage('COM_JEDCHECKER_ERROR_XML_UPDATE_SERVER_NOT_FOUND', self::issues($data, 'error', 'empty.xml'));
    }

    public function testReportsServerWithoutLink(): void
    {
        $this->createFiles(['foo.xml' => self::manifest('component', '<updateservers><server type="extension">none</server></updateservers>')]);

        $this->assertHasMessage(
            'COM_JEDCHECKER_ERROR_XML_UPDATE_SERVER_LINK_NOT_FOUND',
            self::issues($this->runRule(XmlUpdateServerRule::class), 'error', 'foo.xml')
        );
    }

    public function testChecksOnlyThePackageManifestOfAPackage(): void
    {
        $this->createFiles([
            'pkg_foo.xml'         => self::manifest('package', '<updateservers><server type="extension">https://example.com/pkg.xml</server></updateservers>'),
            'com_foo/com_foo.xml' => self::manifest('component', ''),
            'mod_foo/mod_foo.xml' => self::manifest('module', '', ' client="site"'),
        ]);

        $data = $this->runRule(XmlUpdateServerRule::class);

        $this->assertSame([], self::issues($data, 'error'));
        $this->assertCount(1, self::issues($data, 'passed', 'pkg_foo.xml'));
    }

    public function testSkipsManifestsNestedInAComponent(): void
    {
        $this->requireForwardSlashPaths('XmlUpdateServerRule::findXMLPaths()');

        $this->createFiles([
            'com_foo/com_foo.xml'                 => self::manifest('component', '<updateservers><server type="extension">https://example.com/c.xml</server></updateservers>'),
            'com_foo/modules/mod_foo/mod_foo.xml' => self::manifest('module', '', ' client="site"'),
            'plg_bar/plg_bar.xml'                 => self::manifest('plugin', '', ' group="system"'),
        ]);

        $data = $this->runRule(XmlUpdateServerRule::class);

        $this->assertSame([], self::issues($data, 'error', 'com_foo/modules/mod_foo/mod_foo.xml'));
        $this->assertHasMessage('COM_JEDCHECKER_ERROR_XML_UPDATE_SERVER_NOT_FOUND', self::issues($data, 'error', 'plg_bar/plg_bar.xml'));
    }
}
