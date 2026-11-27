<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jedchecker\Tests\Unit\Rule;

use Joomla\Component\Jedchecker\Administrator\Rule\Rules\XmlLicenseRule;
use Joomla\Component\Jedchecker\Tests\Unit\RuleTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class XmlLicenseRuleTest extends RuleTestCase
{
    private static function manifest(string $licence): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>' . "\n"
            . '<extension type="component" method="upgrade"><name>com_foo</name><version>1.0.0</version>'
            . $licence . '</extension>';
    }

    public static function gplLicences(): array
    {
        return [
            ['<license>GNU General Public License version 2 or later</license>'],
            ['<license>GNU General Public Licence version 2 or later</license>'],
            ['<license>http://www.gnu.org/copyleft/gpl.html GNU/GPL</license>'],
            ['<license>GPL-3.0-or-later</license>'],
        ];
    }

    #[DataProvider('gplLicences')]
    public function testAcceptsGplLicences(string $licence): void
    {
        $this->createFiles(['foo.xml' => self::manifest($licence)]);

        $this->assertSame(0, $this->runRule(XmlLicenseRule::class)['count']['total']);
    }

    public function testReportsMissingLicence(): void
    {
        $this->createFiles(['foo.xml' => self::manifest('')]);

        $errors = self::issues($this->runRule(XmlLicenseRule::class), 'error', 'foo.xml');

        $this->assertCount(1, $errors);
        $this->assertSame('COM_JEDCHECKER_ERROR_XML_LICENSE_NOT_FOUND', $errors[0]['text']);
        $this->assertSame('PH3', $errors[0]['subtype']);
    }

    public function testReportsNonGplLicence(): void
    {
        $this->createFiles(['foo.xml' => self::manifest('<license>MIT</license>')]);

        $compat = self::issues($this->runRule(XmlLicenseRule::class), 'compatibility', 'foo.xml');

        $this->assertCount(1, $compat);
        $this->assertSame('COM_JEDCHECKER_ERROR_XML_LICENSE_NOT_GPL', $compat[0]['text']);
    }

    public function testIgnoresXmlFilesThatAreNoManifests(): void
    {
        $this->createFiles([
            'forms/form.xml' => '<?xml version="1.0"?><form><field name="x"/></form>',
            'broken.xml'     => '<extension type="component"',
        ]);

        $this->assertSame(0, $this->runRule(XmlLicenseRule::class)['count']['total']);
    }
}
