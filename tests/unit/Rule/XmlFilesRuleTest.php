<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jedchecker\Tests\Unit\Rule;

use Joomla\Component\Jedchecker\Administrator\Rule\Rules\XmlFilesRule;
use Joomla\Component\Jedchecker\Tests\Unit\RuleTestCase;

class XmlFilesRuleTest extends RuleTestCase
{
    private static function manifest(string $body, string $type = 'component'): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>' . "\n"
            . '<extension type="' . $type . '" method="upgrade"><name>com_foo</name><version>1.0.0</version>'
            . $body . '</extension>';
    }

    public function testAcceptsListedFilesAndFoldersThatExist(): void
    {
        $this->createFiles([
            'com_foo.xml' => self::manifest(
                '<scriptfile>script.php</scriptfile>'
                . '<namespace path="src">Foo\\Component\\Foo</namespace>'
                . '<files folder="site"><filename>foo.php</filename><folder>tmpl</folder></files>'
                . '<media destination="com_foo" folder="media"><folder>js</folder></media>'
                . '<languages folder="site/language"><language tag="en-GB">en-GB/en-GB.com_foo.ini</language></languages>'
                . '<administration><files folder="admin"><folder>src</folder><folder>sql</folder></files></administration>'
                . '<install><sql><file driver="mysql">sql/install.sql</file></sql></install>'
                . '<update><schemas><schemapath type="mysql">sql/updates</schemapath></schemas></update>'
            ),
            'script.php'                            => '<?php',
            'site/foo.php'                          => '<?php',
            'site/tmpl/default.php'                 => '<?php',
            'media/js/foo.js'                       => '',
            'site/language/en-GB/en-GB.com_foo.ini' => '',
            'admin/src/Foo.php'                     => '<?php',
            'admin/sql/install.sql'                 => '',
            'admin/sql/updates/1.0.0.sql'           => '',
        ]);

        $this->assertSame(0, $this->runRule(XmlFilesRule::class)['count']['total']);
    }

    public function testReportsMissingFilesAndFolders(): void
    {
        $this->createFiles([
            'com_foo.xml' => self::manifest(
                '<files folder="site"><filename>foo.php</filename><filename>missing.php</filename><folder>gone</folder></files>'
            ),
            'site/foo.php' => '<?php',
        ]);

        $errors = self::issues($this->runRule(XmlFilesRule::class), 'error', 'com_foo.xml');

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('COM_JEDCHECKER_XML_FILES_FILE_NOT_FOUND[site/missing.php]', $errors[0]['text']);
        $this->assertStringContainsString('COM_JEDCHECKER_XML_FILES_FOLDER_NOT_FOUND[site/gone]', $errors[0]['text']);
        $this->assertStringNotContainsString('foo.php', $errors[0]['text']);
    }

    public function testWarnsAboutMissingSourceFolderAndEmptyLists(): void
    {
        $this->createFiles(['com_foo.xml' => self::manifest('<files folder="nowhere"></files>')]);

        $warnings = self::issues($this->runRule(XmlFilesRule::class), 'warning', 'com_foo.xml');

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('COM_JEDCHECKER_XML_FILES_EMPTY_LIST[extension/files]', $warnings[0]['text']);
        $this->assertStringContainsString('COM_JEDCHECKER_XML_FILES_FOLDER_NOT_FOUND[nowhere]', $warnings[0]['text']);
    }

    public function testRefusesPathsOutsideThePackage(): void
    {
        $this->createFiles([
            'com_foo.xml' => self::manifest(
                '<files><filename>../../../../etc/passwd</filename><folder>/etc</folder></files>'
                . '<media folder="C:/Windows"><folder>System32</folder></media>'
            ),
        ]);

        $errors = self::issues($this->runRule(XmlFilesRule::class), 'error', 'com_foo.xml');

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('COM_JEDCHECKER_XML_FILES_OUTSIDE_PACKAGE[../../../../etc/passwd]', $errors[0]['text']);
        $this->assertStringContainsString('COM_JEDCHECKER_XML_FILES_OUTSIDE_PACKAGE[/etc]', $errors[0]['text']);
        $this->assertStringContainsString('COM_JEDCHECKER_XML_FILES_OUTSIDE_PACKAGE[C:/Windows]', $errors[0]['text']);
        // After refusing the source folder, its entries are looked up in the package, not on the server.
        $this->assertStringContainsString('COM_JEDCHECKER_XML_FILES_FOLDER_NOT_FOUND[System32]', $errors[0]['text']);
    }

    public function testAcceptsExtractedArchivesOfAPackage(): void
    {
        $this->createFiles([
            'pkg_foo.xml' => self::manifest(
                '<files><file type="component" id="com_foo">com_foo.zip</file><file type="module" id="mod_foo" client="site">mod_foo.tar.gz</file></files>',
                'package'
            ),
            'com_foo/com_foo.xml' => '<?xml version="1.0"?><extension type="component"><name>x</name></extension>',
            'mod_foo/mod_foo.php' => '<?php',
        ]);

        $this->assertSame([], self::issues($this->runRule(XmlFilesRule::class), 'error', 'pkg_foo.xml'));
    }

    public function testChecksFieldPathsOfModules(): void
    {
        $this->createFiles([
            'mod_foo.xml' => '<?xml version="1.0" encoding="utf-8"?>'
                . '<extension type="module" client="site" method="upgrade"><name>mod_foo</name><version>1</version>'
                . '<files><filename module="mod_foo">mod_foo.php</filename><folder>fields</folder></files>'
                . '<config><fields name="params" addfieldpath="/modules/mod_foo/fields"><fieldset name="basic"/></fields>'
                . '<fields name="other" addfieldpath="/modules/mod_foo/missing"/></config></extension>',
            'mod_foo.php'      => '<?php',
            'fields/field.php' => '<?php',
        ]);

        $errors = self::issues($this->runRule(XmlFilesRule::class), 'error', 'mod_foo.xml');

        $this->assertCount(1, $errors);
        $this->assertSame('COM_JEDCHECKER_XML_FILES_FOLDER_NOT_FOUND[/modules/mod_foo/missing]', $errors[0]['text']);
    }
}
