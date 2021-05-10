<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2021 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');


/**
 * class JedcheckerHelper
 *
 * This is a helper class with a set of static methods used by other JEDChecker classes
 *
 * @since  3.0
 */
abstract class JEDCheckerHelper
{
	const CLEAN_HTML     = 1;
	const CLEAN_COMMENTS = 2;
	const CLEAN_STRINGS  = 4;

	/**
	 * Split text into lines
	 *
	 * @param   string $content Text to split
	 *
	 * @return  string[]
	 * @since  3.0
	 */
	public static function splitLines($content)
	{
		// Split on one of EOL characters (except of EOL at the end of text)
		return preg_split("/(?:\r\n|\n|\r)(?!$)/", $content);
	}

	/**
	 * Get extension name (element)
	 *
	 * @param   SimpleXMLElement $xml XML Manifest
	 *
	 * @return  string
	 * @since  3.0
	 */
	public static function getElementName($xml)
	{
		$type = (string) $xml['type'];

		if (isset($xml->element))
		{
			$extension = (string) $xml->element;
		}
		else
		{
			$extension = (string) $xml->name;

			if (isset($xml->files))
			{
				foreach ($xml->files->children() as $child)
				{
					if (isset($child[$type]))
					{
						$extension = (string) $child[$type];
					}
				}
			}
		}

		$extension = strtolower(JFilterInput::getInstance()->clean($extension, 'cmd'));

		if ($type === 'component' && strpos($extension, 'com_') !== 0)
		{
			$extension = 'com_' . $extension;
		}

		return $extension;
	}

	/**
	 * Removes HTML, comments, and/or strings content keeping EOL characters to preserve line numbers
	 *
	 * @param   string $content PHP sources
	 * @param   int    $options Bitwise set of options
	 *
	 * @return  string
	 * @since  3.0
	 */
	public static function cleanPhpCode($content, $options = self::CLEAN_HTML | self::CLEAN_COMMENTS)
	{
		$isCleanHtml     = $options & self::CLEAN_HTML;
		$isCleanComments = $options & self::CLEAN_COMMENTS;
		$isCleanStrings  = $options & self::CLEAN_STRINGS;

		if (!preg_match('/<\?php\s/i', $content, $match, PREG_OFFSET_CAPTURE))
		{
			// No PHP code found
			return $isCleanHtml ? '' : $content;
		}

		$pos = $match[0][1];
		$code = substr($content, 0, $pos);
		$cleanContent = $isCleanHtml ? self::removeContent($code) : $code;

		while (preg_match('/(?:[\'"]|\/\*|\/\/|\?>)/', $content, $match, PREG_OFFSET_CAPTURE, $pos))
		{
			$foundPos = $match[0][1];
			$cleanContent .= substr($content, $pos, $foundPos - $pos);
			$pos = $foundPos;

			switch ($match[0][0])
			{
				case '"':
				case "'":
					$q = $match[0][0];

					if (!preg_match("/$q(?>[^$q\\\\]+|\\\\.)*$q/As", $content, $match, 0, $pos))
					{
						return $cleanContent . ($isCleanStrings ? $q : substr($content, $pos));
					}

					$code = $match[0];
					$cleanContent .= $isCleanStrings ? $q . self::removeContent($code) . $q : $code;
					$pos += strlen($code);
					break;

				case '/*':
					$cleanContent .= '/*';
					$pos += 2;

					$endPos = strpos($content, '*/', $pos);

					if ($endPos === false)
					{
						return $isCleanComments ? $cleanContent : $cleanContent . substr($content, $pos);
					}

					$code = substr($content, $pos, $endPos - $pos);
					$cleanContent .= $isCleanComments ? self::removeContent($code) : $code;
					$cleanContent .= '*/';
					$pos = $endPos + 2;

					break;

				case '//':
					$commentLen = strcspn($content, "\r\n", $pos);

					if (!$isCleanComments)
					{
						$cleanContent .= substr($content, $pos, $commentLen);
					}

					$pos += $commentLen;
					break;

				case '?>':
					$cleanContent .= '?>';
					$pos += 2;

					if (!preg_match('/<\?php\s/i', $content, $match, PREG_OFFSET_CAPTURE, $pos))
					{
						// No PHP code found (up to the end of the file)
						return $cleanContent . ($isCleanHtml ? '' : substr($content, $pos));
					}

					$foundPos = $match[0][1];
					$code = substr($content, $pos, $foundPos - $pos);
					$cleanContent .= $isCleanHtml ? self::removeContent($code) : $code;

					$phpPreamble = $match[0][0];
					$cleanContent .= $phpPreamble;
					$pos = $foundPos + strlen($phpPreamble);

					break;
			}
		}

		$cleanContent .= substr($content, $pos);

		return $cleanContent;
	}

	/**
	 * Remove all text content by keeping newline characters only (to preserve line numbers)
	 *
	 * @param   string $content Partial content
	 *
	 * @return  string
	 * @since  3.0
	 */
	protected static function removeContent($content)
	{
		return str_repeat("\n", substr_count($content, "\n"));
	}
}
