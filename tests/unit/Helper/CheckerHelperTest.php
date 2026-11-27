<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jedchecker\Tests\Unit\Helper;

use Joomla\Component\Jedchecker\Administrator\Helper\CheckerHelper;
use Joomla\Component\Jedchecker\Tests\Unit\RuleTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

class CheckerHelperTest extends RuleTestCase
{
    private const ALL = CheckerHelper::CLEAN_HTML | CheckerHelper::CLEAN_COMMENTS | CheckerHelper::CLEAN_STRINGS;

    public static function cleanPhpCodeCases(): array
    {
        return [
            'line comment' => [
                "<?php\n\$a = 1; // base64_decode\n# eval\n",
                CheckerHelper::CLEAN_COMMENTS,
                "<?php\n\$a = 1; \n\n",
            ],
            'block comment keeps line breaks' => [
                "<?php\n/* a\n b */\$x = 1;\n",
                CheckerHelper::CLEAN_COMMENTS,
                "<?php\n/*\n*/\$x = 1;\n",
            ],
            'html outside php' => [
                "<p>eval</p>\n<?php echo 1; ?>\n<b>x</b>\n",
                CheckerHelper::CLEAN_HTML,
                // HTML after the last closing tag is dropped as a whole.
                "\n<?php echo 1; ?>",
            ],
            'strings' => [
                "<?php\n\$a = 'eval'; \$b = \"x {\$c} y\";\n",
                CheckerHelper::CLEAN_STRINGS,
                "<?php\n\$a = ''; \$b = \"{\$c}\";\n",
            ],
            'escaped quotes' => [
                "<?php\n\$a = 'it\\'s'; \$b = 1;\n",
                CheckerHelper::CLEAN_STRINGS,
                "<?php\n\$a = ''; \$b = 1;\n",
            ],
            'comment markers inside strings are kept' => [
                "<?php\n\$url = 'http://example.com'; \$x = 1;\n",
                CheckerHelper::CLEAN_COMMENTS,
                "<?php\n\$url = 'http://example.com'; \$x = 1;\n",
            ],
            'heredoc' => [
                "<?php\n\$a = <<<EOT\neval {\$x}\nEOT;\n\$b = 1;\n",
                CheckerHelper::CLEAN_STRINGS,
                "<?php\n\$a = <<<EOT\n{\$x}\nEOT;\n\$b = 1;\n",
            ],
            'no php at all' => [
                "<p>Just HTML</p>\n",
                CheckerHelper::CLEAN_HTML,
                '',
            ],
            'line comment ends at closing tag' => [
                "<?php // comment ?>\n<p>x</p>\n",
                self::ALL,
                '<?php ?>',
            ],
        ];
    }

    #[DataProvider('cleanPhpCodeCases')]
    public function testCleanPhpCode(string $content, int $options, string $expected): void
    {
        $this->assertSame($expected, CheckerHelper::cleanPhpCode($content, $options));
    }

    public function testCleanPhpCodeKeepsTheNumberOfLines(): void
    {
        $content = "<p>\nHTML\n</p>\n<?php\n/**\n * Doc\n */\n\$a = 'multi\nline';\n// x\n\$b = <<<EOT\none\ntwo\nEOT;\n?>\n<i>\n</i>\n<?php\n\$c = 1;\n";

        $this->assertSame(substr_count($content, "\n"), substr_count(CheckerHelper::cleanPhpCode($content, self::ALL), "\n"));
    }

    public function testResolveAliasesExpandsImportedNames(): void
    {
        $content = "<?php\nuse Joomla\\CMS\\Factory;\nuse Joomla\\Registry\\Registry as JRegistry;\n\$app = Factory::getApplication();\n\$r = new JRegistry();\n";

        $this->assertSame(
            "<?php\n\n\n\$app = Joomla\\CMS\\Factory::getApplication();\n\$r = new Joomla\\Registry\\Registry();\n",
            CheckerHelper::resolveAliases($content)
        );
    }

    public function testSplitLines(): void
    {
        $this->assertSame(['a', 'b', 'c', 'd'], CheckerHelper::splitLines("a\nb\r\nc\rd"));
        $this->assertSame(['a', '', 'b'], CheckerHelper::splitLines("a\n\nb"));
    }

    public static function packagePaths(): array
    {
        return [
            ['admin/foo.php', true],
            ['foo..bar/x.php', true],
            ['', true],
            ['../foo.php', false],
            ['admin/../../foo.php', false],
            ['admin\\..\\..\\foo.php', false],
            ['/etc/passwd', false],
            ['\\\\server\\share', false],
            ['C:/Windows', false],
            ['c:\\Windows', false],
            ['php://filter/resource=x', false],
        ];
    }

    #[DataProvider('packagePaths')]
    public function testIsInsidePackage(string $path, bool $inside): void
    {
        $this->assertSame($inside, CheckerHelper::isInsidePackage($path));
    }

    public function testSortPathsCmpSortsByDepthThenName(): void
    {
        $paths = ['a/b/c.xml', 'b.xml', 'a/z.xml', 'a.xml', 'a/b.xml'];

        usort($paths, [CheckerHelper::class, 'sortPathsCmp']);

        $this->assertSame(['a.xml', 'b.xml', 'a/b.xml', 'a/z.xml', 'a/b/c.xml'], $paths);
    }

    public static function elementNames(): array
    {
        return [
            'element tag'            => ['<extension type="module"><name>Foo</name><element>mod_foo</element></extension>', 'mod_foo'],
            'component gets prefix'  => ['<extension type="component"><name>Foo</name></extension>', 'com_foo'],
            'component keeps prefix' => ['<extension type="component"><name>com_Foo</name></extension>', 'com_foo'],
            'plugin attribute'       => ['<extension type="plugin"><name>PLG_X</name><files><filename plugin="myplugin">x.php</filename></files></extension>', 'myplugin'],
            'unsafe characters'      => ['<extension type="module"><name>Foo</name><element>../mod foo!</element></extension>', 'modfoo'],
        ];
    }

    #[DataProvider('elementNames')]
    public function testGetElementName(string $xml, string $expected): void
    {
        $this->assertSame($expected, CheckerHelper::getElementName(new \SimpleXMLElement($xml)));
    }

    public function testFindManifestsSkipsNonManifestsAndSortsByDepth(): void
    {
        $this->createFiles([
            'sub/mod_foo.xml' => '<?xml version="1.0"?><extension type="module"><name>x</name></extension>',
            'com_foo.xml'     => '<?xml version="1.0"?><extension type="component"><name>x</name></extension>',
            'legacy.xml'      => '<?xml version="1.0"?><install type="component"><name>x</name></install>',
            'forms/form.xml'  => '<?xml version="1.0"?><form/>',
            'broken.xml'      => '<extension',
        ]);

        $files = array_map(
            fn (string $file): string => str_replace('\\', '/', substr($file, \strlen($this->packageDir) + 1)),
            CheckerHelper::findManifests($this->packageDir)
        );

        $this->assertSame(['com_foo.xml', 'legacy.xml', 'sub/mod_foo.xml'], $files);
    }

    public function testLoadXmlReturnsFalseForBrokenXmlWithoutOutput(): void
    {
        $this->createFiles(['broken.xml' => '<extension><name>x</extension>', 'ok.xml' => '<extension/>']);

        $this->assertFalse(CheckerHelper::loadXml($this->packageDir . '/broken.xml'));
        $this->assertSame([], libxml_get_errors());
        $this->assertSame('extension', CheckerHelper::loadXml($this->packageDir . '/ok.xml')->getName());
    }

    public function testLoadXmlDoesNotExpandExternalEntities(): void
    {
        $this->createFiles([
            'secret.txt' => 'SECRET',
            'xxe.xml'    => '<?xml version="1.0"?><!DOCTYPE x [<!ENTITY e SYSTEM "secret.txt">]><extension><name>&e;</name></extension>',
        ]);

        $xml = CheckerHelper::loadXml($this->packageDir . '/xxe.xml');

        $this->assertStringNotContainsString('SECRET', $xml === false ? '' : (string) $xml->name);
    }

    public function testDirectorySize(): void
    {
        $this->createFiles(['a.txt' => str_repeat('x', 10), 'sub/b.txt' => str_repeat('y', 5)]);

        $this->assertSame(15, CheckerHelper::directorySize($this->packageDir));
        $this->assertSame(10, CheckerHelper::directorySize($this->packageDir . '/a.txt'));
        $this->assertSame(0, CheckerHelper::directorySize($this->packageDir . '/missing'));
    }

    public function testExceedsSizeLimitForZipArchives(): void
    {
        if (! class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('The zip extension is not available.');
        }

        $zipFile = $this->packageDir . '/a.zip';
        $zip     = new \ZipArchive();
        $zip->open($zipFile, \ZipArchive::CREATE);
        $zip->addFromString('big.txt', str_repeat("\0", 100000));
        $zip->close();

        $this->assertTrue(CheckerHelper::exceedsSizeLimit($zipFile, 50000));
        $this->assertFalse(CheckerHelper::exceedsSizeLimit($zipFile, 200000));
    }

    public function testExceedsSizeLimitForGzipFiles(): void
    {
        $gzFile = $this->packageDir . '/a.tar.gz';
        file_put_contents($gzFile, gzencode(str_repeat("\0", 100000)));

        $this->assertTrue(CheckerHelper::exceedsSizeLimit($gzFile, 50000));
        $this->assertFalse(CheckerHelper::exceedsSizeLimit($gzFile, 200000));
    }

    public function testProtectDirectoryWritesItsOwnServerConfiguration(): void
    {
        $this->createFiles([
            '.htaccess'                  => "Options +ExecCGI\n",
            'unzipped/pkg.zip/.htaccess' => "AddHandler application/x-httpd-php .txt\n",
            'unzipped/pkg.zip/.user.ini' => "auto_prepend_file=evil.php\n",
            'unzipped/pkg.zip/foo.php'   => '<?php',
        ]);

        CheckerHelper::protectDirectory($this->packageDir);

        $this->assertStringContainsString('Require all denied', file_get_contents($this->packageDir . '/.htaccess'));
        $this->assertStringContainsString('<clear />', file_get_contents($this->packageDir . '/web.config'));
        // The package's own server configuration is no longer active.
        $this->assertFileDoesNotExist($this->packageDir . '/unzipped/pkg.zip/.htaccess');
        $this->assertFileDoesNotExist($this->packageDir . '/unzipped/pkg.zip/.user.ini');
        $this->assertFileExists($this->packageDir . '/unzipped/pkg.zip/foo.php');
    }

    public function testBootstrapPointsJpathAdministratorAtTheComponent(): void
    {
        // Sanity check of a constant the rules rely on in the tests.
        $this->assertTrue(\defined('JPATH_ADMINISTRATOR'));
        $this->assertDirectoryExists(JPATH_ADMINISTRATOR . '/components/com_jedchecker');
    }
}
