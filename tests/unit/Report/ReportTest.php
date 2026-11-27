<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jedchecker\Tests\Unit\Report;

use Joomla\Component\Jedchecker\Administrator\Report\Report;
use PHPUnit\Framework\TestCase;

class ReportTest extends TestCase
{
    public function testCountsIssuesPerLevel(): void
    {
        $report = new Report();
        $report->addError('a.php', 'e1');
        $report->addError('b.php', 'e2');
        $report->addWarning('a.php', 'w');
        $report->addCompat('a.php', 'c');
        $report->addNotice('a.php', 'n');
        $report->addInfo('a.php', 'i');
        $report->addPassed('a.php', 'p');

        $this->assertSame(
            ['total' => 7, 'error' => 2, 'warning' => 1, 'compatibility' => 1, 'notice' => 1, 'info' => 1, 'passed' => 1],
            $report->getData()['count']
        );
    }

    public function testDataListsIssuesBySeverity(): void
    {
        $report = new Report();
        $report->setDefaultSubtype('X1');
        $report->addNotice('n.php', 'notice', 3, 'code();');
        $report->addError('e.php', 'error');
        $report->addIssue(Report::LEVEL_WARNING, 'TM2', 'w.php', 'warning');

        $issues = $report->getData()['issues'];

        $this->assertSame(['error', 'warning', 'notice'], array_column($issues, 'type'));
        $this->assertSame(['X1', 'TM2', 'X1'], array_column($issues, 'subtype'));
        $this->assertSame(
            ['type' => 'notice', 'subtype' => 'X1', 'location' => 'n.php', 'text' => 'notice', 'line' => 3, 'code' => 'code();'],
            $issues[2]
        );
    }

    public function testMakesLocationsRelativeToTheBaseFolder(): void
    {
        $base   = sys_get_temp_dir() . '/jedchecker_report';
        $report = new Report();
        $report->setBasedir($base . '/');
        $report->addError($base . '/com_foo.zip/foo.php');
        $report->addError($base);
        $report->addError($base . 'suffix/foo.php');
        $report->addError('');

        $locations = array_map(
            static fn (string $location): string => str_replace('\\', '/', $location),
            array_column($report->getData()['issues'], 'location')
        );

        $this->assertSame('com_foo.zip/foo.php', $locations[0]);
        $this->assertSame('/', $locations[1]);
        // Only real sub-paths are shortened, not paths that merely start with the same characters.
        $this->assertStringEndsWith('jedchecker_reportsuffix/foo.php', $locations[2]);
        $this->assertSame('', $locations[3]);
    }

    public function testHtmlForAnEmptyReport(): void
    {
        $this->assertSame(
            '<div class="alert alert-success">COM_JEDCHECKER_EVERYTHING_SEEMS_TO_BE_FINE_WITH_THAT_RULE</div>',
            (new Report())->getHTML()
        );
    }

    public function testHtmlEscapesLocationAndCode(): void
    {
        $report = new Report();
        $report->setDefaultSubtype('PH1');
        $report->addError('<b>x</b>.php', 'Message', 12, "<?php echo '<script>';\n");

        $html = $report->getHTML();

        $this->assertStringContainsString('class="alert alert-danger"', $html);
        $this->assertStringContainsString('data-level="COM_JEDCHECKER_LEVEL_ERROR: PH1"', $html);
        $this->assertStringContainsString('<strong>#001</strong> &lt;b&gt;x&lt;/b&gt;.php', $html);
        $this->assertStringContainsString('COM_JEDCHECKER_IN_LINE: <strong>12</strong>', $html);
        $this->assertStringContainsString('<pre>&lt;?php echo &#039;&lt;script&gt;&#039;;</pre>', $html);
    }

    public function testHtmlUsesOneBootstrapStylePerLevel(): void
    {
        $report = new Report();
        $report->addCompat('a', 'c');
        $report->addWarning('a', 'w');
        $report->addInfo('a', 'i');

        $html = $report->getHTML();

        // Ordered by severity, whatever the order of adding.
        $this->assertMatchesRegularExpression('/alert-warning.*alert-secondary.*alert-info/s', $html);
    }

    public function testResetClearsTheReport(): void
    {
        $report = new Report();
        $report->addError('a', 'e');
        $report->reset();

        $this->assertSame(0, $report->getData()['count']['total']);
        $this->assertSame([], $report->getData()['issues']);
    }
}
