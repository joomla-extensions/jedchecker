<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2021 Open Source Matters, Inc. All rights reserved.
 *             Copyright (C) 2008 - 2016 compojoom.com . All rights reserved.
 * @author     Denis Ryabov <denis@mobilejoomla.com>
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');


// Include the rule base class
require_once JPATH_COMPONENT_ADMINISTRATOR . '/models/rule.php';


/**
 * class JedcheckerRulesXMLManifest
 *
 * This class validates all xml manifestes
 *
 * @since  2.3
 */
class JedcheckerRulesXMLManifest extends JEDcheckerRule
{
	/**
	 * The formal ID of this rule. For example: SE1.
	 *
	 * @var    string
	 */
	protected $id = 'MANIFEST';

	/**
	 * The title or caption of this rule.
	 *
	 * @var    string
	 */
	protected $title = 'COM_JEDCHECKER_MANIFEST';

	/**
	 * The description of this rule.
	 *
	 * @var    string
	 */
	protected $description = 'COM_JEDCHECKER_MANIFEST_DESC';

	/**
	 * List of errors.
	 *
	 * @var    string[]
	 */
	protected $errors;

	/**
	 * List of warnings.
	 *
	 * @var    string[]
	 */
	protected $warnings;

	/**
	 * Rules for XML nodes
	 *   ? - single, optional
	 *   = - single, required, warning if missed
	 *   ! - single, required, error if missed
	 *   * - multiple, optional
	 * @var array
	 */
	protected $DTDNodeRules;

	/**
	 * Rules for attributes
	 *   (list of allowed attributes)
	 * @var array
	 */
	protected $DTDAttrRules;

	protected $types = array(
	    'component', 'file', 'language', 'library',
	    'module', 'package', 'plugin', 'template'
	);

	/**
	 * Initiates the search and check
	 *
	 * @return    void
	 */
	public function check()
	{
		// Find all XML files of the extension
		$files = JFolder::files($this->basedir, '\.xml$', true, true);

		// Iterate through all the xml files
		foreach ($files as $file)
		{
			// Try to check the file
			$this->find($file);
		}
	}

	/**
	 * Reads a file and validate XML manifest
	 *
	 * @param   string  $file  - The path to the file
	 *
	 * @return boolean True if the manifest file was found, otherwise False.
	 */
	protected function find($file)
	{
		$xml = simplexml_load_file($file);

		// Failed to parse the xml file.
		// Assume that this is not a extension manifest
		if (!$xml)
		{
			return false;
		}

		// Check if this is an extension manifest
		if ($xml->getName() !== 'extension')
		{
			return false;
		}

		// check extension type
		$type = (string) $xml['type'];
		if (!in_array($type, $this->types, true))
		{
			$this->report->addError($file, JText::sprintf('COM_JEDCHECKER_MANIFEST_UNKNOWN_TYPE', $type));
			return true;
		}

		// load DTD-like data for this extension type
		$json_filename = __DIR__ . '/xmlmanifest/dtd_' . $type . '.json';
		if (!is_file($json_filename))
		{
			$this->report->addError($file, JText::sprintf('COM_JEDCHECKER_MANIFEST_TYPE_NOT_ACCEPTED', $type));
			return true;
		}
		$data = json_decode(file_get_contents($json_filename), true);
		$this->DTDNodeRules = $data['nodes'];
		$this->DTDAttrRules = $data['attributes'];

		$this->errors = array();
		$this->warnings = array();

		// validate manifest
		$this->validateXml($xml, 'extension');

		if (count($this->errors))
		{
			$this->report->addError($file, implode('<br />', $this->errors));
		}

		if (count($this->warnings))
		{
			$this->report->addWarning($file, implode('<br />', $this->warnings));
		}

		// All checks passed. Return true
		return true;
	}

	/**
	 * @param JXMLElement $node
	 * @param string      $name
	 */
	protected function validateXml($node, $name)
	{
		// Check attributes
		$DTDattributes = isset($this->DTDAttrRules[$name]) ? $this->DTDAttrRules[$name] : array();
		foreach ($node->attributes() as $attr)
		{
			$attr_name = (string)$attr->getName();
			if (!in_array($attr_name, $DTDattributes, true))
			{
				$this->warnings[] = JText::sprintf('COM_JEDCHECKER_MANIFEST_UNKNOWN_ATTRIBUTE', $name, $attr_name);
			}
		}

		// Check children nodes
		if (!isset($this->DTDNodeRules[$name]))
		{
			// No children
			if ($node->count() > 0)
			{
				$this->warnings[] = JText::sprintf('COM_JEDCHECKER_MANIFEST_UNKNOWN_CHILDREN', $name);
			}
		} else {
			$DTDchildren = $this->DTDNodeRules[$name];

			// 1) check required single elements

			foreach ($DTDchildren as $child => $mode)
			{
				$count = $node->$child->count();
				switch ($mode)
				{
					case '!':
						$errors =& $this->errors;
						break;
					case '=':
						$errors =& $this->warnings;
						break;
					default:
						continue 2;
				}
				if ($count === 0)
				{
					$errors[] = JText::sprintf('COM_JEDCHECKER_MANIFEST_MISSED_REQUIRED', $name, $child);
				}
				elseif ($count > 1)
				{
					$errors[] = JText::sprintf('COM_JEDCHECKER_MANIFEST_MULTIPLE_FOUND', $name, $child);
				}
				unset($errors);
			}

			// 2) check unknown/multiple elements

			// collect unique child node names
			$child_names = array();
			foreach ($node as $child)
			{
				$child_names[$child->getName()] = 1;
			}
			$child_names = array_keys($child_names);

			foreach ($child_names as $child)
			{
				if (!isset($DTDchildren[$child]))
				{
					$this->warnings[] = JText::sprintf('COM_JEDCHECKER_MANIFEST_UNKNOWN_CHILD', $name, $child);
				}
				else
				{
					if ($DTDchildren[$child] === '?' && $node->$child->count() > 1)
					{
						$this->errors[] = JText::sprintf('COM_JEDCHECKER_MANIFEST_MULTIPLE_FOUND', $name, $child);
					}
				}
			}
		}

		// Extra checks (if exist)
		$method = 'validateXml' . $name;
		if (method_exists($this, $method))
		{
			$this->$method($node);
		}

		// Recursion
		foreach ($node as $child)
		{
			$child_name = $child->getName();
			if (isset($this->DTDNodeRules[$child_name])) {
				$this->validateXml($child, $child_name);
			}
		}
	}

	/**
	 * Extra check for menu nodes
	 * @param JXMLElement $node
	 */
	protected function validateXmlMenu($node)
	{
		if (isset($node['link']))
		{
			$skip_attrs = array('act', 'controller', 'layout', 'sub', 'task', 'view');
			foreach ($node->attributes() as $attr)
			{
				$attr_name = $attr->getName();
				if (in_array($attr_name, $skip_attrs, true))
				{
					$this->warnings[] = JText::sprintf('COM_JEDCHECKER_MANIFEST_MENU_UNUSED_ATTRIBUTE', $attr_name);
				}
			}
		}
	}
}
