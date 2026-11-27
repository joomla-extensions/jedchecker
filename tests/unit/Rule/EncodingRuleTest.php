<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jedchecker\Tests\Unit\Rule;

use Joomla\Component\Jedchecker\Administrator\Rule\Rules\EncodingRule;
use Joomla\Component\Jedchecker\Tests\Unit\RuleTestCase;

class EncodingRuleTest extends RuleTestCase
{
    public function testReportsEncodingFunctionsWithLineAndCode(): void
    {
        $this->createFiles([
            'foo.php' => "<?php\ndefined('_JEXEC') or die;\n\$a = 1;\n\$x = base64_decode(\$y);\n\$z = ZLIB_ENCODE(\$y);\n",
        ]);

        $warnings = self::issues($this->runRule(EncodingRule::class), 'warning', 'foo.php');

        $this->assertCount(2, $warnings);
        $this->assertSame('COM_JEDCHECKER_ERROR_ENCODING', $warnings[0]['text']);
        $this->assertSame(4, $warnings[0]['line']);
        $this->assertSame('$x = base64_decode($y);', $warnings[0]['code']);
        $this->assertSame(5, $warnings[1]['line']);
    }

    public function testIgnoresCommentsAndHtml(): void
    {
        $this->createFiles([
            'foo.php' => "<p>base64_encode in HTML</p>\n<?php\n// base64_decode in a comment\n/* zlib_decode */\n# base64_encode\necho 1;\n",
        ]);

        $this->assertSame([], self::issues($this->runRule(EncodingRule::class), 'warning'));
    }

    public function testChecksOnlyPhpFiles(): void
    {
        $this->createFiles(['foo.js' => 'var x = base64_decode(y);']);

        $this->assertSame(0, $this->runRule(EncodingRule::class)['count']['total']);
    }
}
