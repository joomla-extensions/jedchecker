<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Component\Jedchecker\Tests\Unit\Rule;

use Joomla\Component\Jedchecker\Administrator\Rule\AbstractRule;
use Joomla\Component\Jedchecker\Administrator\Rule\RuleDiscovery;
use PHPUnit\Framework\TestCase;

class RuleDiscoveryTest extends TestCase
{
    public function testFindsEveryRuleClass(): void
    {
        $files    = glob(\dirname(__DIR__, 3) . '/src/administrator/components/com_jedchecker/src/Rule/Rules/*Rule.php');
        $expected = array_map(
            static fn (string $file): string => 'Joomla\\Component\\Jedchecker\\Administrator\\Rule\\Rules\\' . basename($file, '.php'),
            $files
        );

        $rules = RuleDiscovery::getRules();

        sort($expected);
        $sorted = $rules;
        sort($sorted);

        $this->assertNotEmpty($rules);
        $this->assertSame($expected, $sorted);
    }

    public function testRulesAreOrderedAndDescribed(): void
    {
        $previous = PHP_INT_MIN;
        $ids      = [];

        foreach (RuleDiscovery::getRules() as $ruleClass) {
            $this->assertTrue(is_subclass_of($ruleClass, AbstractRule::class));
            $this->assertGreaterThanOrEqual($previous, $ruleClass::$ordering, $ruleClass . ' is out of order');
            $previous = $ruleClass::$ordering;

            /** @var AbstractRule $rule */
            $rule = new $ruleClass();

            $this->assertNotSame('', $rule->getId(), $ruleClass);
            $this->assertStringStartsWith('COM_JEDCHECKER_', $rule->getTitle(), $ruleClass);
            $this->assertStringStartsWith('COM_JEDCHECKER_', $rule->getDescription(), $ruleClass);
            $ids[] = $rule->getId();
        }

        $this->assertSame($ids, array_unique($ids), 'Rule ids are unique');
    }

    public function testRuleTitlesAndDescriptionsAreTranslated(): void
    {
        $ini = parse_ini_file(
            \dirname(__DIR__, 3) . '/src/administrator/language/en-GB/en-GB.com_jedchecker.ini',
            false,
            INI_SCANNER_RAW
        );

        foreach (RuleDiscovery::getRules() as $ruleClass) {
            $rule = new $ruleClass();

            $this->assertArrayHasKey($rule->getTitle(), $ini, $ruleClass . ' title');
            $this->assertArrayHasKey($rule->getDescription(), $ini, $ruleClass . ' description');
        }
    }
}
