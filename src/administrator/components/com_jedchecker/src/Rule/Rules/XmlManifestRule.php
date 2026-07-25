<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2021 - 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public Licence version 2 or later; see LICENCE.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Rule\Rules;

use Joomla\CMS\Language\Text;
use Joomla\Component\Jedchecker\Administrator\Helper\CheckerHelper;
use Joomla\Component\Jedchecker\Administrator\Rule\AbstractRule;
use SimpleXMLElement;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * XmlManifestRule validates all XML manifests against DTD-like rules.
 *
 * @since  3.0.0
 */
class XmlManifestRule extends AbstractRule
{
    /**
     * Rule ordering.
     *
     * @var integer
     * @since 3.0.0
     */
    public static int $ordering = 200;
    /**
     * Rule ID.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $id = 'MANIFEST';
    /**
     * Rule title.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $title = 'COM_JEDCHECKER_MANIFEST';
    /**
     * Description of the rule.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $description = 'COM_JEDCHECKER_MANIFEST_DESC';
    /**
     * List of errors.
     *
     * @var array
     * @since 3.0.0
     */
    protected array $errors = [];
    /**
     * List of warnings.
     *
     * @var array
     * @since 3.0.0
     */
    protected array $warnings = [];
    /**
     * List of notices.
     *
     * @var array
     * @since 3.0.0
     */
    protected array $notices = [];
    /**
     * Rules for XML nodes: ? optional single, = optional warn-if-missed, ! required, * multiple optional
     *
     * @var array
     * @since 3.0.0
     */
    protected array $DTDNodeRules = [];
    /**
     * Rules for attributes (list of allowed attribute names)
     *
     * @var array
     * @since 3.0.0
     */
    protected array $DTDAttrRules = [];

    /**
     * List of Joomla types.
     *
     * @var array
     * @since 3.0.0
     */
    protected array $joomlaTypes = [
            'component',
            'file',
            'language',
            'library',
            'module',
            'package',
            'plugin',
            'template',
    ];

    /**
     * check
     *
     * Runs the rule.
     *
     * @since  3.0.0
     */
    public function check(): void
    {
        $this->report->setDefaultSubtype($this->id);

        $files = CheckerHelper::findManifests($this->basedir);

        foreach ($files as $file) {
            $this->find($file);
        }
    }

    /**
     * find
     *
     * Finds and processes a manifest file.
     *
     * @param   string  $file
     *
     * @return bool
     *
     * @since  3.0.0
     */
    protected function find(string $file): bool
    {
        $xml = simplexml_load_file($file);

        if (! $xml) {
            return false;
        }

        $type = (string)$xml['type'];

        if (! \in_array($type, $this->joomlaTypes, true)) {
            $this->report->addError(
                $file,
                Text::sprintf('COM_JEDCHECKER_MANIFEST_UNKNOWN_TYPE', htmlspecialchars($type, ENT_QUOTES))
            );

            return true;
        }

        $jsonFilename = __DIR__ . '/data/xmlmanifest/dtd_' . $type . '.json';

        if (! is_file($jsonFilename)) {
            return true;
        }

        if ((string)$xml['method'] !== 'upgrade') {
            $this->report->addWarning($file, Text::_('COM_JEDCHECKER_MANIFEST_MISSED_METHOD_UPGRADE'));
        }

        switch ($type) {
            case 'language':
            case 'module':
            case 'template':
                $client = (string)$xml['client'];

                if (! isset($xml['client'])) {
                    $this->report->addError(
                        $file,
                        Text::sprintf('COM_JEDCHECKER_MANIFEST_MISSED_ATTRIBUTE', $xml->getName(), 'client')
                    );
                } elseif ($client !== 'site' && $client !== 'administrator') {
                    $this->report->addError(
                        $file,
                        Text::sprintf(
                            'COM_JEDCHECKER_MANIFEST_UNKNOWN_ATTRIBUTE_VALUE',
                            $xml->getName(),
                            'client',
                            htmlspecialchars($client, ENT_QUOTES)
                        )
                    );
                }

                if ($type === 'module') {
                    $elements = $this->collectElements($xml->files, $type);

                    if (\count($elements) >= 2) {
                        $this->report->addWarning(
                            $file,
                            Text::sprintf('COM_JEDCHECKER_MANIFEST_MULTIPLE_ATTRIBUTES', 'module')
                        );
                    }

                    if (isset($xml->element)) {
                        $element = (string)$xml->element;

                        if (\count($elements) && $elements[0] !== $element) {
                            $this->report->addWarning(
                                $file,
                                Text::_('COM_JEDCHECKER_MANIFEST_MODULE_ELEMENT_MISMATCH')
                            );
                        }
                    } else {
                        if (\count($elements) === 0) {
                            $this->report->addError(
                                $file,
                                Text::sprintf('COM_JEDCHECKER_MANIFEST_MISSED_ELEMENT_ATTRIBUTE', 'module')
                            );
                        }
                    }
                }

                break;

            case 'plugin':
                $elements = $this->collectElements($xml->files, $type);

                if (\count($elements) >= 2) {
                    $this->report->addWarning(
                        $file,
                        Text::sprintf('COM_JEDCHECKER_MANIFEST_MULTIPLE_ATTRIBUTES', 'plugin')
                    );
                }

                if (\count($elements) === 0) {
                    $this->report->addError(
                        $file,
                        Text::sprintf('COM_JEDCHECKER_MANIFEST_MISSED_ELEMENT_ATTRIBUTE', 'plugin')
                    );
                }

                break;

            case 'package':
                foreach ($xml->files->children() as $item) {
                    if (! isset($item['type'])) {
                        $this->report->addError(
                            $file,
                            Text::sprintf('COM_JEDCHECKER_MANIFEST_MISSED_ATTRIBUTE', $item->getName(), 'type')
                        );
                    }

                    if (! isset($item['id'])) {
                        $this->report->addError(
                            $file,
                            Text::sprintf('COM_JEDCHECKER_MANIFEST_MISSED_ATTRIBUTE', $item->getName(), 'id')
                        );
                    }

                    switch ((string)$item['type']) {
                        case 'plugin':
                            if (! isset($item['group'])) {
                                $this->report->addError(
                                    $file,
                                    Text::sprintf(
                                        'COM_JEDCHECKER_MANIFEST_MISSED_ATTRIBUTE',
                                        $item->getName(),
                                        'group'
                                    )
                                );
                            }
                            break;

                        case 'language':
                        case 'module':
                        case 'template':
                            $client = (string)$item['client'];

                            if (! isset($item['client'])) {
                                $this->report->addError(
                                    $file,
                                    Text::sprintf(
                                        'COM_JEDCHECKER_MANIFEST_MISSED_ATTRIBUTE',
                                        $item->getName(),
                                        'client'
                                    )
                                );
                            } elseif ($client !== 'site' && $client !== 'administrator') {
                                $this->report->addError(
                                    $file,
                                    Text::sprintf(
                                        'COM_JEDCHECKER_MANIFEST_UNKNOWN_ATTRIBUTE_VALUE',
                                        $item->getName(),
                                        'client',
                                        htmlspecialchars($client, ENT_QUOTES)
                                    )
                                );
                            }
                            break;

                        case 'component':
                        case 'file':
                        case 'library':
                            break;

                        default:
                            $this->report->addError(
                                $file,
                                Text::sprintf(
                                    'COM_JEDCHECKER_MANIFEST_UNKNOWN_TYPE',
                                    htmlspecialchars((string)$item['type'], ENT_QUOTES)
                                )
                            );
                    }
                }
        }

        $data               = json_decode(file_get_contents($jsonFilename), true);
        $this->DTDNodeRules = $data['nodes'];
        $this->DTDAttrRules = $data['attributes'];

        $this->errors   = [];
        $this->warnings = [];
        $this->notices  = [];

        $this->validateXml($xml, 'extension');

        if (\count($this->errors)) {
            $this->report->addError($file, implode('<br />', $this->errors));
        }

        if (\count($this->warnings)) {
            $this->report->addWarning($file, implode('<br />', $this->warnings));
        }

        if (\count($this->notices)) {
            $this->report->addNotice($file, implode('<br />', $this->notices));
        }

        return true;
    }

    /**
     * collectElements
     *
     * Collects elements from a SimpleXMLElement node based on a specified type.
     *
     * @param   \SimpleXMLElement|null  $node
     * @param   string                 $type
     *
     * @return array
     *
     * @since  3.0.0
     */
    protected function collectElements(?\SimpleXMLElement $node, string $type): array
    {
        $elements = [];

        if (isset($node)) {
            foreach ($node->children() as $child) {
                if (isset($child[$type])) {
                    $elements[] = (string)$child[$type];
                }
            }
        }

        return $elements;
    }

    /**
     * validateXml
     *
     * Validates XML node against a specified ruleset.
     *
     * @param   \SimpleXMLElement  $node
     * @param   string            $ruleset
     *
     * @since  3.0.0
     */
    protected function validateXml(\SimpleXMLElement $node, string $ruleset): void
    {
        $name = $node->getName();

        $DTDattributes = $this->DTDAttrRules[$ruleset] ?? [];

        if (\count($DTDattributes) === 0) {
            foreach ($node->attributes() as $attr) {
                $this->notices[] = Text::sprintf(
                    'COM_JEDCHECKER_MANIFEST_UNKNOWN_ATTRIBUTE',
                    $name,
                    (string)$attr->getName()
                );
            }
        } elseif ($DTDattributes[0] !== '*') {
            foreach ($node->attributes() as $attr) {
                $attrName = (string)$attr->getName();

                if (! \in_array($attrName, $DTDattributes, true)) {
                    $this->notices[] = Text::sprintf('COM_JEDCHECKER_MANIFEST_UNKNOWN_ATTRIBUTE', $name, $attrName);
                }
            }
        }

        $DTDchildRules  = $this->DTDNodeRules[$ruleset] ?? [];
        $DTDchildToRule = [];

        if (\count($DTDchildRules) === 0) {
            if ($node->count() > 0) {
                $this->notices[] = Text::sprintf('COM_JEDCHECKER_MANIFEST_UNKNOWN_CHILDREN', $name);
            }
        } elseif (! isset($DTDchildRules['*'])) {
            foreach ($DTDchildRules as $childRuleset => $mode) {
                $child = $childRuleset;

                if (strpos($child, ':') !== false) {
                    [$prefix, $child] = explode(':', $child, 2);
                }

                $DTDchildToRule[$child] = $childRuleset;
                $count                  = $node->$child->count();

                switch ($mode) {
                    case '!':
                        if ($count === 0) {
                            $this->errors[] = Text::sprintf('COM_JEDCHECKER_MANIFEST_MISSED_REQUIRED', $name, $child);
                        } elseif ($count > 1) {
                            $this->errors[] = Text::sprintf('COM_JEDCHECKER_MANIFEST_MULTIPLE_FOUND', $name, $child);
                        }

                        break;

                    case '=':
                        if ($count === 0) {
                            $this->notices[] = Text::sprintf('COM_JEDCHECKER_MANIFEST_MISSED_OPTIONAL', $name, $child);
                        } elseif ($count > 1) {
                            $this->warnings[] = Text::sprintf('COM_JEDCHECKER_MANIFEST_MULTIPLE_FOUND', $name, $child);
                        }

                        break;
                }
            }

            $childNames = [];

            foreach ($node as $child) {
                $childNames[$child->getName()] = 1;
            }

            $childNames = array_keys($childNames);

            foreach ($childNames as $child) {
                if (! isset($DTDchildToRule[$child])) {
                    $this->notices[] = Text::sprintf('COM_JEDCHECKER_MANIFEST_UNKNOWN_CHILD', $name, $child);
                } elseif ($DTDchildRules[$DTDchildToRule[$child]] === '?' && $node->$child->count() > 1) {
                    $this->errors[] = Text::sprintf('COM_JEDCHECKER_MANIFEST_MULTIPLE_FOUND', $name, $child);
                }
            }

            foreach ($node as $child) {
                if ($child->count() === 0 && $child->attributes()->count() === 0 && (string)$child === '') {
                    $this->notices[] = Text::sprintf('COM_JEDCHECKER_MANIFEST_EMPTY_CHILD', $child->getName());
                }
            }
        }

        $method = 'validateXml' . $name;

        if (method_exists($this, $method)) {
            $this->$method($node);
        }

        foreach ($node as $child) {
            $childName = $child->getName();

            if (isset($DTDchildToRule[$childName])) {
                $this->validateXml($child, $DTDchildToRule[$childName]);
            }
        }
    }

    /**
     * validateXmlMenu
     *
     * Validates XML menu node for specific attributes.
     *
     * @param   \SimpleXMLElement  $node
     *
     * @since  3.0.0
     */
    protected function validateXmlMenu(\SimpleXMLElement $node): void
    {
        if (isset($node['link'])) {
            $skipAttrs = ['act', 'controller', 'layout', 'sub', 'task', 'view'];

            foreach ($node->attributes() as $attr) {
                $attrName = $attr->getName();

                if (\in_array($attrName, $skipAttrs, true)) {
                    $this->warnings[] = Text::sprintf('COM_JEDCHECKER_MANIFEST_MENU_UNUSED_ATTRIBUTE', $attrName);
                }
            }
        }
    }
}
