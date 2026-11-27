<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jedchecker\Tests\Unit\Rule;

use Joomla\Component\Jedchecker\Administrator\Rule\Rules\FrameworkRule;
use Joomla\Component\Jedchecker\Tests\Unit\RuleTestCase;

class FrameworkRuleTest extends RuleTestCase
{
    public function testCleanFileHasNoFindings(): void
    {
        $this->createFiles([
            'foo.php' => "<?php\n\nnamespace Foo;\n\nuse Joomla\\CMS\\Factory;\n\n\\defined('_JEXEC') or die;\n\n\$app = Factory::getApplication();\n",
        ]);

        $this->assertSame(0, $this->runRule(FrameworkRule::class)['count']['total']);
    }

    public function testReportsDirectDatabaseAccessAsError(): void
    {
        $this->createFiles(['foo.php' => "<?php\n\$r = mysqli_query(\$db, \$sql);\n\$m = new mysqli('h');\n"]);

        $errors = self::issues($this->runRule(FrameworkRule::class), 'error', 'foo.php');

        $this->assertCount(2, $errors);
        $this->assertHasMessage('COM_JEDCHECKER_ERROR_FRAMEWORK_DIRECTDB', $errors);
        // The offending code is shown, with the match highlighted.
        $this->assertStringContainsString('<b>mysqli_query</b>', $errors[0]['text']);
    }

    public function testReportsSuperglobalsAsWarning(): void
    {
        $this->createFiles(['foo.php' => "<?php\n\$id = \$_GET['id'];\n\$x = \$_REQUEST;\n\$s = '\$_POST in a string';\n"]);

        $warnings = self::issues($this->runRule(FrameworkRule::class), 'warning', 'foo.php');

        $this->assertCount(2, $warnings);
        $this->assertHasMessage('COM_JEDCHECKER_ERROR_FRAMEWORK_SUPERGLOBALS', $warnings);
    }

    public function testReportsDebugOutputAsNotice(): void
    {
        $this->createFiles(['foo.php' => "<?php\nvar_dump(\$x);\nprint_r(\$y);\n"]);

        $notices = self::issues($this->runRule(FrameworkRule::class), 'notice', 'foo.php');

        $this->assertCount(2, $notices);
        $this->assertHasMessage('COM_JEDCHECKER_ERROR_FRAMEWORK_ERRORLOG', $notices);
    }

    public function testReportsRemovedLegacyClassAliases(): void
    {
        $this->createFiles(['foo.php' => "<?php\n\$app = JFactory::getApplication();\n\$jfactory = 1;\n"]);

        $compat = self::issues($this->runRule(FrameworkRule::class), 'compatibility', 'foo.php');

        $this->assertCount(1, $compat);
        $this->assertHasMessage('COM_JEDCHECKER_ERROR_FRAMEWORK_LEGACY_ALIASES', $compat);
        $this->assertStringContainsString('\\Joomla\\CMS\\Factory', $compat[0]['text']);
    }

    public function testLegacyAliasImportedUnderItsOldNameIsResolved(): void
    {
        // "use Joomla\CMS\Factory as JFactory;" is fine: JFactory is the namespaced class here.
        $this->createFiles(['foo.php' => "<?php\nuse Joomla\\CMS\\Factory as JFactory;\n\$app = JFactory::getApplication();\n"]);

        $this->assertSame([], self::issues($this->runRule(FrameworkRule::class), 'compatibility'));
    }

    public function testReportsByteOrderMarkLeadingWhitespaceAndShortTags(): void
    {
        $this->createFiles([
            'bom.php'     => "\xEF\xBB\xBF<?php\necho 1;\n",
            'leading.php' => "\n<?php\necho 1;\n",
            'short.php'   => "<?php\necho 1;\n?>\n<p>x</p>\n<? echo 2; ?>\n",
        ]);

        $data = $this->runRule(FrameworkRule::class);

        $this->assertHasMessage('COM_JEDCHECKER_ERROR_FRAMEWORK_BOM_FOUND', self::issues($data, 'error', 'bom.php'));
        $this->assertHasMessage('COM_JEDCHECKER_ERROR_FRAMEWORK_LEADING_SPACES', self::issues($data, 'notice', 'leading.php'));

        $shortTag = self::issues($data, 'error', 'short.php');
        $this->assertHasMessage('COM_JEDCHECKER_ERROR_FRAMEWORK_SHORT_PHP_TAG', $shortTag);
        $this->assertSame(5, $shortTag[0]['line']);
    }

    public function testReportsLeftoverFilesAndFolders(): void
    {
        $this->createFiles([
            'node_modules/pkg/index.js' => 'x',
            'admin/helper.php.bak'      => 'x',
            'admin/error_log'           => 'x',
            '.gitignore'                => 'x',
            '.htaccess'                 => 'x',
            'admin/helper.php'          => "<?php\necho 1;\n",
        ]);

        $warnings   = self::issues($this->runRule(FrameworkRule::class), 'warning');
        $byLocation = [];

        foreach ($warnings as $warning) {
            $byLocation[str_replace('\\', '/', $warning['location'])] = $warning['text'];
        }

        $this->assertSame('COM_JEDCHECKER_ERROR_FRAMEWORK_LEFTOVER_FOLDER', $byLocation['node_modules'] ?? null);
        $this->assertSame('COM_JEDCHECKER_ERROR_FRAMEWORK_LEFTOVER_FILE', $byLocation['admin/helper.php.bak'] ?? null);
        $this->assertSame('COM_JEDCHECKER_ERROR_FRAMEWORK_LEFTOVER_FILE', $byLocation['admin/error_log'] ?? null);
        $this->assertSame('COM_JEDCHECKER_ERROR_FRAMEWORK_LEFTOVER_FILE', $byLocation['.gitignore'] ?? null);
        // Whitelisted.
        $this->assertArrayNotHasKey('.htaccess', $byLocation);
        $this->assertArrayNotHasKey('admin/helper.php', $byLocation);
    }

    public function testSkipsPhpFilesInLeftoverFolders(): void
    {
        $this->requireForwardSlashPaths('FrameworkRule::excludeResource()');

        $this->createFiles(['node_modules/pkg/tool.php' => "<?php\nvar_dump(\$_GET);\n"]);

        $data = $this->runRule(FrameworkRule::class);

        $this->assertSame([], self::issues($data, 'notice'));
        $this->assertSame(
            ['node_modules'],
            array_map(static fn (array $issue): string => $issue['location'], self::issues($data, 'warning'))
        );
    }
}
