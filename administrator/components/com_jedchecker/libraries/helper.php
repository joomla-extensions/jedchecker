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
 * @since  2.4
 */
abstract class JEDCheckerHelper
{
	const CLEAN_HTML     = 1;
	const CLEAN_COMMENTS = 2;
	const CLEAN_STRINGS  = 4;

	/**
	 * Returns XML manifest files in the package (sorted by depth)
	 *
	 * @param   string $basedir Extension's directory
	 *
	 * @return  string[]
	 * @since  2.4
	 */
	public static function findManifests($basedir)
	{
		// Find all XML files of the extension
		$files = JFolder::files($basedir, '\.xml$', true, true);

		$excludeList = array();

		foreach ($files as $file)
		{
			$xml = simplexml_load_file($file);

			if (!$xml || ($xml->getName() !== 'extension' && $xml->getName() !== 'install'))
			{
				// Exclude non-install-manifest XML files
				$excludeList[] = $file;
			}
			elseif ((string) $xml['type'] === 'component' && isset($xml->administration->files['folder']))
			{
				// Exclude possible duplicates of manifest in components
				$excludeList[] = dirname($file) . '/' . trim($xml->administration->files['folder'], ' /') . '/' . basename($file);
			}
			elseif ((string) $xml['type'] === 'file' && isset($xml->fileset->files))
			{
				// Exclude possible duplicates of file-type extension manifest
				foreach ($xml->fileset->files as $child)
				{
					if (isset($child['folder']))
					{
						$excludeList[] = dirname($file) . '/' . trim($child['folder'], ' /') . '/' . basename($file);
					}
				}
			}
		}

		$files = array_diff($files, $excludeList);
		usort($files, array(__CLASS__, 'sortPathsCmp'));

		return $files;
	}

	/**
	 * Sort directories by depth
	 *
	 * @param   string $path1 1st path to compare
	 * @param   string $path2 2nd path to compare
	 *
	 * @return  integer
	 * @since  2.4
	 */
	public static function sortPathsCmp($path1, $path2)
	{
		$depth1 = substr_count($path1, '/');
		$depth2 = substr_count($path2, '/');

		return ($depth1 === $depth2) ? strcmp($path1, $path2) : ($depth1 - $depth2);
	}

	/**
	 * Split text into lines
	 *
	 * @param   string $content Text to split
	 *
	 * @return  string[]
	 * @since  2.4
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
	 * @since  2.4
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
	 * @since  2.4
	 */
	public static function cleanPhpCode($content, $options = self::CLEAN_HTML | self::CLEAN_COMMENTS)
	{
		$isCleanHtml     = $options & self::CLEAN_HTML;
		$isCleanComments = $options & self::CLEAN_COMMENTS;
		$isCleanStrings  = $options & self::CLEAN_STRINGS;

		if (!preg_match('/<\?(?:php\s|\s|=)/i', $content, $match, PREG_OFFSET_CAPTURE))
		{
			// No PHP code found
			return $isCleanHtml ? '' : $content;
		}

		$pos = $match[0][1];
		$code = substr($content, 0, $pos);
		$cleanContent = $isCleanHtml ? self::removeContent($code) : $code;

		while (preg_match('/[\'"`]|<<<|\/\*|\/\/|#|\?>/', $content, $match, PREG_OFFSET_CAPTURE, $pos))
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

					$code = substr($match[0], 1, -1);
					$cleanContent .= $q . ($isCleanStrings ? self::removeContent($code, $q === '"') : $code) . $q;
					$pos += strlen($match[0]);
					break;

				case '`':
					if (!preg_match("/`(?>[^`\\\\]+|\\\\.)*`/As", $content, $match, 0, $pos))
					{
						return $cleanContent . substr($content, $pos);
					}

					$code = $match[0];
					$cleanContent .= $code;
					$pos += strlen($code);
					break;

				case '<<<':
					$cleanContent .= '<<<';
					$pos += 3;

					if (!preg_match('/([a-z_]\w*|\'.*?\'|".*?")\n/iA', $content, $match, 0, $pos))
					{
						break;
					}

					$identifier = $match[1];
					$cleanContent .= $match[0];
					$pos += strlen($match[0]);

					$foundPos = strpos($content, $identifier, $pos);

					if ($foundPos === false)
					{
						return $cleanContent . ($isCleanStrings ? '' : substr($content, $pos));
					}

					$code = substr($content, $pos, $foundPos - $pos);
					$cleanContent .= ($isCleanStrings ? self::removeContent($code, $identifier[0] !== "'") : $code) . $identifier;
					$pos += strlen($code) + strlen($identifier);
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
				case '#':
					$commentLen = strcspn($content, "\r\n", $pos);
					$endPhpPos = strpos($content, '?>', $pos);

					if ($endPhpPos !== false && $endPhpPos < $pos + $commentLen)
					{
						$commentLen = $endPhpPos - $pos;
					}

					if (!$isCleanComments)
					{
						$cleanContent .= substr($content, $pos, $commentLen);
					}

					$pos += $commentLen;

					break;

				case '?>':
					$cleanContent .= '?>';
					$pos += 2;

					if (!preg_match('/<\?(?:php\s|\s|=)/i', $content, $match, PREG_OFFSET_CAPTURE, $pos))
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
	 * @since  2.4
	 */
	protected static function removeContent($content, $parse = false)
	{
		if (!$parse)
		{
			return str_repeat("\n", substr_count($content, "\n"));
		}

		$pos = 0;
		$cleanContent = '';

		while (preg_match('/\n|\\|\{\$|\$\{/', $content, $match, PREG_OFFSET_CAPTURE, $pos))
		{
			$foundPos = $match[0][1];
			$cleanContent .= substr($content, $pos, $foundPos - $pos);
			$pos = $foundPos;

			switch ($match[0][0])
			{
				case "\n":
					$cleanContent .= "\n";
					$pos++;
					break;

				case '\\':
					$pos++;

					if ($pos < strlen($content) && $content[$pos] === "\n")
					{
						$cleanContent .= "\\\n";
					}

					$pos++;
					break;

				case '{$':
				case '${':
					$posx = $pos + 2;
					$braces = 1;
					$strlen = strlen($content);

					while ($braces > 0 && $posx < $strlen)
					{
						$q = $content[$posx];

						switch ($q)
						{
							case '{':
								$braces++;
								break;

							case '}':
								$braces--;
								break;

							case '"':
							case "'":
								if (!preg_match("/$q(?>[^$q\\\\]+|\\\\.)*$q/As", $content, $match, 0, $posx))
								{
									return $cleanContent . substr($content, $pos);
								}

								$posx += strlen($match[0]);
								break;

							case '`':
								if (!preg_match("/`.*?`/As", $content, $match, 0, $posx))
								{
									return $cleanContent . substr($content, $pos);
								}

								$posx += strlen($match[0]);
								break;
						}

						$posx++;
					}

					$cleanContent .= substr($content, $pos, $posx - $pos);
					$pos = $posx;
					break;
			}
		}

		return $cleanContent;
	}
}
