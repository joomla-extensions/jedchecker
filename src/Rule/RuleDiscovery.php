<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2025 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public Licence version 2 or later; see LICENCE.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Rule;

defined('_JEXEC') or die('Restricted access');

/**
 * RuleDiscovery locates all AbstractRule implementations in the Rules/ directory via PSR-4.
 *
 * @since  3.0
 */
class RuleDiscovery
{
	private const RULES_NAMESPACE = 'Joomla\\Component\\Jedchecker\\Administrator\\Rule\\Rules\\';

	private const RULES_DIR = __DIR__ . '/Rules';

	/**
	 * Returns all concrete AbstractRule subclasses found in the Rules/ directory,
	 * sorted ascending by their static $ordering property.
	 *
	 * @return  string[]  Array of fully-qualified class names.
	 */
	public static function getRules(): array
	{
		$classes = [];

		$files = glob(self::RULES_DIR . '/*.php');

		if ($files === false)
		{
			return [];
		}

		foreach ($files as $file)
		{
			$basename  = basename($file, '.php');
			$fqcn      = self::RULES_NAMESPACE . $basename;

			if (!class_exists($fqcn))
			{
				continue;
			}

			if (!is_subclass_of($fqcn, AbstractRule::class))
			{
				continue;
			}

			$classes[] = $fqcn;
		}

		usort($classes, static function (string $a, string $b): int {
			return $a::$ordering <=> $b::$ordering;
		});

		return $classes;
	}
}
