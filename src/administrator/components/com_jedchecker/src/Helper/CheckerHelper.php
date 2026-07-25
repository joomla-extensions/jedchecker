<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2021 - 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public Licence version 2 or later; see LICENCE.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Helper;

use Joomla\CMS\Filter\InputFilter;
use Joomla\Filesystem\Folder;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * CheckerHelper provides static utility methods used across JEDChecker rules.
 *
 * @since  3.0.0
 */
abstract class CheckerHelper
{
    public const CLEAN_HTML     = 1;
    public const CLEAN_COMMENTS = 2;
    public const CLEAN_STRINGS  = 4;

    /**
     * cleanPhpCode
     *
     * Removes unnecessary PHP code from a string, based on specified options.
     *
     * @param   string  $content
     * @param   int     $options
     *
     * @return string
     *
     * @since  3.0.0
     */
    public static function cleanPhpCode(string $content, int $options = self::CLEAN_HTML | self::CLEAN_COMMENTS): string
    {
        $isCleanHtml     = $options & self::CLEAN_HTML;
        $isCleanComments = $options & self::CLEAN_COMMENTS;
        $isCleanStrings  = $options & self::CLEAN_STRINGS;

        if (! preg_match('/<\?(?:php\s|\s|=)/i', $content, $match, PREG_OFFSET_CAPTURE)) {
            return $isCleanHtml ? '' : $content;
        }

        $pos          = $match[0][1];
        $code         = substr($content, 0, $pos);
        $cleanContent = $isCleanHtml ? self::removeContent($code) : $code;

        while (preg_match('/[\'"`]|<<<|\/\*|\/\/|#|\?>/', $content, $match, PREG_OFFSET_CAPTURE, $pos)) {
            $foundPos     = (int)$match[0][1];
            $cleanContent .= substr($content, $pos, $foundPos - $pos);
            $pos          = $foundPos;

            switch ($match[0][0]) {
                case '"':
                case "'":
                    $q = $match[0][0];

                    if (! preg_match("/$q(?>[^$q\\\\]+|\\\\.)*$q/As", $content, $match, 0, $pos)) {
                        return $cleanContent . ($isCleanStrings ? $q : substr($content, $pos));
                    }

                    $code         = substr($match[0], 1, -1);
                    $cleanContent .= $q . ($isCleanStrings ? self::removeContent($code, $q === '"') : $code) . $q;
                    $pos += \strlen($match[0]);
                    break;

                case '`':
                    if (! preg_match("/`(?>[^`\\\\]+|\\\\.)*`/As", $content, $match, 0, $pos)) {
                        return $cleanContent . substr($content, $pos);
                    }

                    $code         = $match[0];
                    $cleanContent .= $code;
                    $pos += \strlen($code);
                    break;

                case '<<<':
                    $cleanContent .= '<<<';
                    $pos += 3;

                    if (! preg_match('/([a-z_]\w*|\'.*?\'|".*?")\n/iA', $content, $match, 0, $pos)) {
                        break;
                    }

                    $identifier   = $match[1];
                    $cleanContent .= $match[0];
                    $pos += \strlen($match[0]);

                    $foundPos = strpos($content, $identifier, $pos);

                    if ($foundPos === false) {
                        return $cleanContent . ($isCleanStrings ? '' : substr($content, $pos));
                    }

                    $code         = substr($content, $pos, $foundPos - $pos);
                    $cleanContent .= ($isCleanStrings ? self::removeContent(
                        $code,
                        $identifier[0] !== "'"
                    ) : $code) . $identifier;
                    $pos += \strlen($code) + \strlen($identifier);
                    break;

                case '/*':
                    $cleanContent .= '/*';
                    $pos += 2;

                    $endPos = strpos($content, '*/', $pos);

                    if ($endPos === false) {
                        return $isCleanComments ? $cleanContent : $cleanContent . substr($content, $pos);
                    }

                    $code         = substr($content, $pos, $endPos - $pos);
                    $cleanContent .= $isCleanComments ? self::removeContent($code) : $code;
                    $cleanContent .= '*/';
                    $pos          = $endPos + 2;
                    break;

                case '//':
                case '#':
                    $commentLen = strcspn($content, "\r\n", $pos);
                    $endPhpPos  = strpos($content, '?>', $pos);

                    if ($endPhpPos !== false && $endPhpPos < $pos + $commentLen) {
                        $commentLen = $endPhpPos - $pos;
                    }

                    if (! $isCleanComments) {
                        $cleanContent .= substr($content, $pos, $commentLen);
                    }

                    $pos += $commentLen;
                    break;

                case '?>':
                    $cleanContent .= '?>';
                    $pos += 2;

                    if (! preg_match('/<\?(?:php\s|\s|=)/i', $content, $match, PREG_OFFSET_CAPTURE, $pos)) {
                        return $cleanContent . ($isCleanHtml ? '' : substr($content, $pos));
                    }

                    $foundPos     = (int)$match[0][1];
                    $code         = substr($content, $pos, $foundPos - $pos);
                    $cleanContent .= $isCleanHtml ? self::removeContent($code) : $code;

                    $phpPreamble  = $match[0][0];
                    $cleanContent .= $phpPreamble;
                    $pos          = $foundPos + \strlen($phpPreamble);
                    break;
            }
        }

        $cleanContent .= substr($content, $pos);

        return $cleanContent;
    }

    /**
     * removeContent
     *
     * Removes unnecessary content from a string, based on specified options.
     *
     * @param   string  $content
     * @param   bool    $parse
     *
     * @return string
     *
     * @since  3.0.0
     */
    protected static function removeContent(string $content, bool $parse = false): string
    {
        if (! $parse) {
            return self::cleanLines($content);
        }

        $pos          = 0;
        $cleanContent = '';

        while (preg_match('/\n|\\\\|\{\$|\$\{/', $content, $match, PREG_OFFSET_CAPTURE, $pos)) {
            $foundPos     = (int)$match[0][1];
            $cleanContent .= self::cleanLines(substr($content, $pos, $foundPos - $pos));
            $pos          = $foundPos;

            switch ($match[0][0]) {
                case "\n":
                    $cleanContent .= "\n";
                    $pos++;
                    break;

                case '\\':
                    $pos++;

                    if ($pos < \strlen($content) && $content[$pos] === "\n") {
                        $cleanContent .= "\\\n";
                    }

                    $pos++;
                    break;

                case '{$':
                case '${':
                    $posx   = $pos + 2;
                    $braces = 1;
                    $strlen = \strlen($content);

                    while ($braces > 0 && $posx < $strlen) {
                        $q = $content[$posx];

                        switch ($q) {
                            case '{':
                                $braces++;
                                break;

                            case '}':
                                $braces--;
                                break;

                            case '"':
                            case "'":
                                if (! preg_match("/$q(?>[^$q\\\\]+|\\\\.)*$q/As", $content, $match, 0, $posx)) {
                                    return $cleanContent . substr($content, $pos);
                                }

                                $posx += \strlen($match[0]);
                                break;

                            case '`':
                                if (! preg_match("/`.*?`/As", $content, $match, 0, $posx)) {
                                    return $cleanContent . substr($content, $pos);
                                }

                                $posx += \strlen($match[0]);
                                break;
                        }

                        $posx++;
                    }

                    $cleanContent .= substr($content, $pos, $posx - $pos);
                    $pos          = $posx;
                    break;
            }
        }

        return $cleanContent;
    }

    /**
     * cleanLines
     *
     * Removes unnecessary line breaks from a string, based on specified options.
     *
     * @param   string  $content
     *
     * @return string
     *
     * @since  3.0.0
     */
    protected static function cleanLines(string $content): string
    {
        return str_repeat("\n", substr_count($content, "\n"));
    }

    /**
     * findManifests
     *
     * Find and return XML manifest files within a given directory.
     *
     * @param   string  $basedir
     *
     * @return array
     *
     * @since 3.0.0
     */
    public static function findManifests(string $basedir): array
    {
        $files = Folder::files($basedir, '\.xml$', true, true);

        $excludeList = [];

        foreach ($files as $file) {
            $xml = simplexml_load_file($file);

            if (! $xml || ($xml->getName() !== 'extension' && $xml->getName() !== 'install')) {
                $excludeList[] = $file;
            } elseif ((string)$xml['type'] === 'component' && isset($xml->administration->files['folder'])) {
                $excludeList[] = \dirname($file) . '/' . trim($xml->administration->files['folder'], ' /') . '/' . basename(
                    $file
                );
            } elseif ((string)$xml['type'] === 'file' && isset($xml->fileset->files)) {
                foreach ($xml->fileset->files as $child) {
                    if (isset($child['folder'])) {
                        $excludeList[] = \dirname($file) . '/' . trim($child['folder'], ' /') . '/' . basename($file);
                    }
                }
            }
        }

        $files = array_diff($files, $excludeList);
        usort($files, [__CLASS__, 'sortPathsCmp']);

        return $files;
    }

    /**
     * getElementName
     *
     * Returns the element name from an XML manifest.
     *
     * @param   \SimpleXMLElement  $xml
     *
     * @return string
     *
     * @since  3.0.0
     */
    public static function getElementName(\SimpleXMLElement $xml): string
    {
        $type = (string)$xml['type'];

        if (isset($xml->element)) {
            $extension = (string)$xml->element;
        } else {
            $extension = (string)$xml->name;

            if (isset($xml->files)) {
                foreach ($xml->files->children() as $child) {
                    if (isset($child[$type])) {
                        $extension = (string)$child[$type];
                    }
                }
            }
        }

        $extension = strtolower(InputFilter::getInstance()->clean($extension, 'cmd'));

        if ($type === 'component' && strpos($extension, 'com_') !== 0) {
            $extension = 'com_' . $extension;
        }

        return $extension;
    }

    /**
     * resolveAliases
     *
     * Resolves PHP class aliases in the given content by replacing them with their fully qualified names.
     *
     * @param   string  $content
     *
     * @return string
     *
     * @since  3.0.0
     */
    public static function resolveAliases(string $content): string
    {
        if (preg_match_all('/\buse\s+([\\\\\w]+)(?:\s+as\s+(\w+))?\s*;/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $fqn = $match[1];

                if (isset($match[2])) {
                    $alias = $match[2];
                } else {
                    $path  = explode('\\', $fqn);
                    $alias = $path[\count($path) - 1];
                }

                $content = str_replace($match[0], self::cleanLines($match[0]), $content);
                $content = preg_replace('/\b' . $alias . '\b/', $fqn, $content);
            }
        }

        return $content;
    }

    /**
     * sortPathsCmp
     *
     * A comparison function for sorting paths by depth.
     *
     * @param   string  $path1
     * @param   string  $path2
     *
     * @return integer
     *
     * @since  3.0.0
     */
    public static function sortPathsCmp(string $path1, string $path2): int
    {
        $depth1 = substr_count($path1, '/');
        $depth2 = substr_count($path2, '/');

        return ($depth1 === $depth2) ? strcmp($path1, $path2) : ($depth1 - $depth2);
    }

    /**
     * splitLines
     *
     * Splits a string into an array of lines, handling different line endings.
     *
     * @param   string  $content
     *
     * @return array
     *
     * @since  3.0.0
     */
    public static function splitLines(string $content): array
    {
        return preg_split("/(?:\r\n|\n|\r)(?!$)/", $content);
    }
}
