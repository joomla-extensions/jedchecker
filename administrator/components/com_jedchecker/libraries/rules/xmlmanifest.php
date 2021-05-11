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

// Include the helper class
require_once JPATH_COMPONENT_ADMINISTRATOR . '/libraries/helper.php';


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
	protected $joomlaTypes = array(
		'component', 'file', 'language', 'library',
		'module', 'package', 'plugin', 'template'
	);

	/**
	 * List of JED extension types
	 *
	 * @var string[]
	 */
	protected $jedTypes = array(
		'component', 'module', 'package', 'plugin'
	);

	/**
	 * Initiates the search and check
	 *
	 * @return    void
	 */
	public function check()
	{
		// Find all XML files of the extension
		$files = JEDCheckerHelper::findManifests($this->basedir);

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

		// Check extension type
		$type = (string) $xml['type'];

		if (!in_array($type, $this->joomlaTypes, true))
		{
			$this->report->addError($file, JText::sprintf('COM_JEDCHECKER_MANIFEST_UNKNOWN_TYPE', $type));

			return true;
		}

		// JED allows components, modules, plugins, and packages (as a container) only
		if (!in_array($type, $this->jedTypes, true))
		{
			$this->report->addError($file, JText::sprintf('COM_JEDCHECKER_MANIFEST_TYPE_NOT_ACCEPTED', $type));
		}

		// Load DTD-like data for this extension type
		$jsonFilename = __DIR__ . '/xmlmanifest/dtd_' . $type . '.json';

		if (!is_file($jsonFilename))
		{
			return true;
		}

		// Warn if method="upgrade" attribute is not found
		if ((string) $xml['method'] !== 'upgrade')
		{
			$this->report->addWarning($file, JText::_('COM_JEDCHECKER_MANIFEST_MISSED_METHOD_UPGRADE'));
		}

		// Check 'client' attribute is "site" or "administrator" (for module/template only)
		if ($type === 'module' || $type === 'template')
		{
			$client = (string) $xml['client'];

			if (!isset($xml['client']))
			{
				$this->report->addError($file, JText::sprintf('COM_JEDCHECKER_MANIFEST_MISSED_ATTRIBUTE', $xml->getName(), 'client'));
			}
			elseif ($client !== 'site' && $client !== 'administrator')
			{
				$this->report->addError($file, JText::sprintf('COM_JEDCHECKER_MANIFEST_UNKNOWN_ATTRIBUTE_VALUE', $xml->getName(), 'client', $client));
			}
		}

		$data = json_decode(file_get_contents($jsonFilename), true);
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
	 * @param   SimpleXMLElement  $node        XML node object
	 * @param   string            $ruleset     ruleset name in the DTD array
	 *
	 * @return  void
	 */
	protected function validateXml($node, $ruleset)
	{
		// Get node name
		$name = $node->getName();

		// Check attributes
		$DTDattributes = isset($this->DTDAttrRules[$ruleset]) ? $this->DTDAttrRules[$ruleset] : array();

		if (count($DTDattributes) === 0)
		{
			// No known attributes for this node
			foreach ($node->attributes() as $attr)
			{
				$this->infos[] = JText::sprintf('COM_JEDCHECKER_MANIFEST_UNKNOWN_ATTRIBUTE', $name, (string) $attr->getName());
			}
		}
		elseif ($DTDattributes[0] !== '*') // Skip node with arbitrary attributes (e.g. "field")
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
		$DTDchildRules = isset($this->DTDNodeRules[$ruleset]) ? $this->DTDNodeRules[$ruleset] : array();

		// Child node name to ruleset name mapping
		$DTDchildToRule = array();

		if (count($DTDchildRules) === 0)
		{
			// No known children for this node
			if ($node->count() > 0)
			{
				$this->infos[] = JText::sprintf('COM_JEDCHECKER_MANIFEST_UNKNOWN_CHILDREN', $name);
			}
		}
		elseif (!isset($DTDchildRules['*'])) // Skip node with arbitrary children
		{
			// 1) check required single elements
			foreach ($DTDchildRules as $childRuleset => $mode)
			{
				$child = $childRuleset;

				if (strpos($child, ':') !== false)
				{
					// Split ruleset name into a prefix and the child node name
					list ($prefix, $child) = explode(':', $child, 2);
				}

				// Populate node-to-ruleset mapping
				$DTDchildToRule[$child] = $childRuleset;

				$count = $node->$child->count();

				switch ($mode)
				{
					case '!':
						if ($count === 0)
						{
							// The node doesn't contain required child element
							$this->errors[] = JText::sprintf('COM_JEDCHECKER_MANIFEST_MISSED_REQUIRED', $name, $child);
						}
						elseif ($count > 1)
						{
							// The node contains multiple child elements when single only is expected
							$this->errors[] = JText::sprintf('COM_JEDCHECKER_MANIFEST_MULTIPLE_FOUND', $name, $child);
						}

						break;

					case '=':
						if ($count === 0)
						{
							// The node doesn't contain optional child element
							$this->infos[] = JText::sprintf('COM_JEDCHECKER_MANIFEST_MISSED_OPTIONAL', $name, $child);
						}
						elseif ($count > 1)
						{
							// The node contains multiple child elements when single only is expected
							$this->warnings[] = JText::sprintf('COM_JEDCHECKER_MANIFEST_MULTIPLE_FOUND', $name, $child);
						}

						break;
				}
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
				if (!isset($DTDchildToRule[$child]))
				{
					// The node contains unknown child element
					$this->infos[] = JText::sprintf('COM_JEDCHECKER_MANIFEST_UNKNOWN_CHILD', $name, $child);
				}
				else
				{
					if ($DTDchildRules[$DTDchildToRule[$child]] === '?' && $node->$child->count() > 1)
					{
						// The node contains multiple child elements when single only is expected
						$this->errors[] = JText::sprintf('COM_JEDCHECKER_MANIFEST_MULTIPLE_FOUND', $name, $child);
					}
				}
			}

			// 3) check empty elements
			foreach ($node as $child)
			{
				if ($child->count() === 0 && $child->attributes()->count() === 0 && (string) $child === '')
				{
					$this->infos[] = JText::sprintf('COM_JEDCHECKER_MANIFEST_EMPTY_CHILD', $child->getName());
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

			if (isset($DTDchildToRule[$childName]))
			{
				$this->validateXml($child, $DTDchildToRule[$childName]);
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
