<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2021 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');


// Include the rule base class
require_once JPATH_COMPONENT_ADMINISTRATOR . '/models/rule.php';


/**
 * class JedcheckerRulesXMLManifest
 *
 * This class validates all XML manifests
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
	 * List of infos.
	 *
	 * @var    string[]
	 */
	protected $infos;

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

	/**
	 * List of extension types
	 *
	 * @var string[]
	 */
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

		// Check extension type
		$type = (string) $xml['type'];

		if (!in_array($type, $this->types, true))
		{
			$this->report->addError($file, JText::sprintf('COM_JEDCHECKER_MANIFEST_UNKNOWN_TYPE', $type));

			return true;
		}

		// Load DTD-like data for this extension type
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
		$this->infos = array();

		// Validate manifest
		$this->validateXml($xml, 'extension');

		if (count($this->errors))
		{
			$this->report->addError($file, implode('<br />', $this->errors));
		}

		if (count($this->warnings))
		{
			$this->report->addWarning($file, implode('<br />', $this->warnings));
		}

		if (count($this->infos))
		{
			$this->report->addInfo($file, implode('<br />', $this->infos));
		}

		// All checks passed. Return true
		return true;
	}

	/**
	 * @param   SimpleXMLElement  $node  XML node object
	 * @param   string            $name  XML node name
	 *
	 * @return  void
	 */
	protected function validateXml($node, $name)
	{
		// Check attributes
		$DTDattributes = isset($this->DTDAttrRules[$name]) ? $this->DTDAttrRules[$name] : array();

		if (isset($DTDattributes[0]) && $DTDattributes[0] !== '*')
		{
			foreach ($node->attributes() as $attr)
			{
				$attrName = (string) $attr->getName();

				if (!in_array($attrName, $DTDattributes, true))
				{
					// The node has unknown attribute
					$this->infos[] = JText::sprintf('COM_JEDCHECKER_MANIFEST_UNKNOWN_ATTRIBUTE', $name, $attrName);
				}
			}
		}

		// Check children nodes
		if (!isset($this->DTDNodeRules[$name]))
		{
			// No children
			if ($node->count() > 0)
			{
				$this->infos[] = JText::sprintf('COM_JEDCHECKER_MANIFEST_UNKNOWN_CHILDREN', $name);
			}
		}
		elseif (!isset($this->DTDNodeRules[$name]['*']))
		{
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

			// Collect unique child node names
			$childNames = array();

			foreach ($node as $child)
			{
				$childNames[$child->getName()] = 1;
			}

			$childNames = array_keys($childNames);

			foreach ($childNames as $child)
			{
				if (!isset($DTDchildren[$child]))
				{
					$this->infos[] = JText::sprintf('COM_JEDCHECKER_MANIFEST_UNKNOWN_CHILD', $name, $child);
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
			$childName = $child->getName();

			if (isset($this->DTDNodeRules[$childName]))
			{
				$this->validateXml($child, $childName);
			}
		}
	}

	/**
	 * Extra check for menu nodes
	 * @param   SimpleXMLElement  $node  XML node
	 *
	 * @return void
	 */
	protected function validateXmlMenu($node)
	{
		if (isset($node['link']))
		{
			// The "link" attribute overrides any other link-related attributes (warn if they present)
			$skipAttrs = array('act', 'controller', 'layout', 'sub', 'task', 'view');

			foreach ($node->attributes() as $attr)
			{
				$attrName = $attr->getName();

				if (in_array($attrName, $skipAttrs, true))
				{
					$this->warnings[] = JText::sprintf('COM_JEDCHECKER_MANIFEST_MENU_UNUSED_ATTRIBUTE', $attrName);
				}
			}
		}
	}
}
