<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2022 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');

use PhpParser\ParserFactory;
use PhpParser\Error;


// Include the rule base class
require_once JPATH_COMPONENT_ADMINISTRATOR . '/models/rule.php';


/**
 * class JedcheckerRulesPHPSyntaxCheck
 *
 * This class TODO
 *
 * @since  2.3
 */
class JedcheckerRulesPHPSyntaxCheck extends JEDcheckerRule
{
	/**
	 * The formal ID of this rule. For example: SE1.
	 *
	 * @var    string
	 */
	protected $id = 'PHPSYNTAXCHECK';

	/**
	 * The title or caption of this rule.
	 *
	 * @var    string
	 */
	protected $title = 'COM_JEDCHECKER_PHP_SYNTAX_CHECK';

	/**
	 * The description of this rule.
	 *
	 * @var    string
	 */
	protected $description = 'COM_JEDCHECKER_PHP_SYNTAX_CHECK_DESC';

	/**
	 * The ordering value to sort rules in the menu.
	 *
	 * @var    integer
	 */
	public static $ordering = 450;

	/**
	 * Manifest's directory
	 *
	 * @var    string
	 */
	protected $basedir;
	
	/**
	 * PHP Parser
	 *
	 * @var    \PhpParser\Parser
	 */
	protected $parser;

	/**
	 * Initiates the search and check
	 *
	 * @return    void
	 */
	public function check()
	{
		include_once JPATH_COMPONENT_ADMINISTRATOR . '/libraries/vendor/autoload.php';
		$this->parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);

		$files = JFolder::files($this->basedir, '\.php$', true, true);
		foreach ($files as $file)
		{
			$this->find($file);
		}
	}

	/**
	 * Reads a file and check syntax
	 *
	 * @param   string  $file  - The path to the file
	 *
	 * @return boolean True if the check has been passed.
	 */
	protected function find($file)
	{
		$code = file_get_contents($file);

		try {
			$ast = $this->parser->parse($code);
		} catch (Error $error) {
			$this->report->addError($file, $error->getMessage());
		}

		return true;
	}
}
