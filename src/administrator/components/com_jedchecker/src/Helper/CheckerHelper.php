<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2021 - 2026 Open Source Matters, Inc. All rights reserved.
 *
 * @license    GNU General Public Licence version 2 or later; see LICENCE.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Helper;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\Filesystem\File;
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

                    // Spaces/tabs are allowed after <<<, and the line may end with \r\n.
                    if (! preg_match('/[ \t]*([a-z_]\w*|\'[a-z_]\w*\'|"[a-z_]\w*")\r?\n/iA', $content, $match, 0, $pos)) {
                        break;
                    }

                    $identifier   = $match[1];
                    $cleanContent .= $match[0];
                    $pos += \strlen($match[0]);

                    // The closing marker is the bare label (no quotes, also for <<<'EOT' and <<<"EOT")
                    // at the start of a line, optionally indented (PHP 7.3+), and not followed by
                    // another identifier character.
                    $label = trim($identifier, '\'"');

                    if (
                        ! preg_match(
                            '/^[ \t]*' . preg_quote($label, '/') . '\b/m',
                            $content,
                            $match,
                            PREG_OFFSET_CAPTURE,
                            $pos
                        )
                    ) {
                        return $cleanContent . ($isCleanStrings ? '' : substr($content, $pos));
                    }

                    $foundPos     = (int)$match[0][1] + \strlen($match[0][0]) - \strlen($label);
                    $code         = substr($content, $pos, $foundPos - $pos);
                    $cleanContent .= ($isCleanStrings ? self::removeContent(
                        $code,
                        $identifier[0] !== "'"
                    ) : $code) . $label;
                    $pos += \strlen($code) + \strlen($label);
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
     * directorySize
     *
     * Returns the total size in bytes of all files under a path (recursively), or the size of
     * a single file. Used to bound decompression-bomb risk when extracting untrusted archives.
     *
     * @param   string  $path
     *
     * @return int
     *
     * @since  3.0.0
     */
    public static function directorySize(string $path): int
    {
        if (! is_dir($path)) {
            return is_file($path) ? (int)filesize($path) : 0;
        }

        $size     = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    /**
     * getExcludedPaths
     *
     * Loads and caches the exclusion entries listed in administrator/components/com_jedchecker/exclude.txt.
     * Each non-comment, non-blank line has the form "<package>:<fragment>", where <package> is matched
     * (case-insensitively, as a prefix) against one of the directory segments of a scanned file's path - the
     * one corresponding to the sub-extension zip it came from. When checking a bundled package (e.g. a
     * pkg_* zip containing many component/module/plugin zips), every nested zip is unpacked into a folder
     * named after that zip (extension stripped, so a version suffix in the zip's own filename survives,
     * e.g. "plg_fabrik_cron_geocode_5.0.1"), and that folder shows up somewhere inside the full path of
     * every file it contains. Scoping to that segment, rather than to the single outermost folder the whole
     * bundle was unpacked into, is what lets an exclusion written for one sub-extension avoid "bleeding"
     * into a different sub-extension that happens to share a folder name such as "vendor" or "libs". A line
     * with no ':' has no package scoping and applies everywhere (kept for simple, package-agnostic
     * exclusions). <fragment> itself is matched as a trailing sequence of path segments anywhere in the path
     * (the same way a bare pattern in a .gitignore file matches at any depth). Blank lines and lines
     * starting with '#' are ignored, and '#' may also be used to document, on its own line, why a given
     * exclusion exists.
     *
     * @return array  List of ['package' => string|null, 'fragment' => string] entries
     *
     * @since __DEPLOY_VERSION__
     */
    public static function getExcludedPaths(): array
    {
        static $excluded = null;

        if ($excluded !== null) {
            return $excluded;
        }

        $excluded = [];

        $excludeFile = JPATH_ADMINISTRATOR . '/components/com_jedchecker/exclude.txt';

        if (! is_file($excludeFile)) {
            return $excluded;
        }

        $content = file_get_contents($excludeFile);

        if ($content === false || $content === '') {
            return $excluded;
        }

        foreach (self::splitLines($content) as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#') {
                continue;
            }

            $package  = null;
            $fragment = $line;

            if (strpos($line, ':') !== false) {
                [$package, $fragment] = explode(':', $line, 2);
                $package              = trim($package, '/ ');
            }

            $excluded[] = [
                    'package'  => $package !== null && $package !== '' ? $package : null,
                    'fragment' => trim($fragment, '/ '),
            ];
        }

        return $excluded;
    }

    /**
     * pathHasPackageSegment
     *
     * Determines whether any '/'-separated segment of $path starts with (case-insensitively) the given
     * package name - i.e. whether $path lies inside a folder that a zip named "$package[-_...version]"
     * was unpacked into. See getExcludedPaths() for why this is how a path's "current package" is found.
     *
     * @param   string  $path
     * @param   string  $package
     *
     * @return bool
     *
     * @since __DEPLOY_VERSION__
     */
    protected static function pathHasPackageSegment(string $path, string $package): bool
    {
        foreach (explode('/', trim($path, '/')) as $segment) {
            if ($segment !== '' && strncasecmp($segment, $package, \strlen($package)) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * isExcludedPath
     *
     * Determines whether the given path matches one of the exclusion entries configured in exclude.txt
     * (see getExcludedPaths()). An entry scoped to a package only applies when $path has a directory
     * segment identifying that package (see pathHasPackageSegment()), so exclusions never bleed between
     * the different sub-extensions unpacked from a bundled package.
     *
     * @param   string  $path
     *
     * @return bool
     *
     * @since __DEPLOY_VERSION__
     */
    public static function isExcludedPath(string $path): bool
    {
        $excludedEntries = self::getExcludedPaths();

        if (empty($excludedEntries)) {
            return false;
        }

        $path = rtrim(str_replace('\\', '/', $path), '/');

        foreach ($excludedEntries as $entry) {
            if ($entry['package'] !== null && ! self::pathHasPackageSegment($path, $entry['package'])) {
                continue;
            }

            $needle = '/' . $entry['fragment'];

            if (substr($path, -\strlen($needle)) === $needle || strpos($path, $needle . '/') !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * filterExcludedPaths
     *
     * Removes any paths that match a configured excluded path fragment (see exclude.txt) from the given list.
     *
     * @param   array  $paths
     *
     * @return array
     *
     * @since __DEPLOY_VERSION__
     */
    public static function filterExcludedPaths(array $paths): array
    {
        if (empty(self::getExcludedPaths())) {
            return $paths;
        }

        return array_values(array_filter($paths, static function (string $path): bool {
            return ! self::isExcludedPath($path);
        }));
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
        $files = self::filterExcludedPaths(Folder::files($basedir, '\.xml$', true, true));

        $excludeList = [];

        foreach ($files as $file) {
            $xml = self::loadXml($file);

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
        $aliases = [];

        // Single imports: use A\B\C; / use A\B\C as D;
        if (preg_match_all('/\buse\s+([\\\\\w]+)(?:\s+as\s+(\w+))?\s*;/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                self::addAlias($aliases, $match[1], $match[2] ?? null);
                $content = str_replace($match[0], self::cleanLines($match[0]), $content);
            }
        }

        // Group imports: use A\B\{C, D as E};
        if (preg_match_all('/\buse\s+([\\\\\w]+?)\\\\\{([^}]*)\}\s*;/i', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                foreach (explode(',', $match[2]) as $item) {
                    if (preg_match('/^\s*([\\\\\w]+)(?:\s+as\s+(\w+))?\s*$/i', $item, $itemMatch)) {
                        self::addAlias($aliases, $match[1] . '\\' . $itemMatch[1], $itemMatch[2] ?? null);
                    }
                }

                $content = str_replace($match[0], self::cleanLines($match[0]), $content);
            }
        }

        if (\count($aliases) === 0) {
            return $content;
        }

        // One pass over the content, so a name that was already expanded is never rewritten again.
        // Only bare names are replaced: not a part of a qualified name (preceded by "\"), a variable
        // ("$Name"), or a property/method/constant ("->Name", "?->Name", "::Name").
        $names = array_map(static fn (string $alias): string => preg_quote($alias, '/'), array_keys($aliases));

        return preg_replace_callback(
            '/(?<![\\\\$\w]|->|::)\b(?:' . implode('|', $names) . ')\b/',
            static fn (array $match): string => $aliases[$match[0]],
            $content
        );
    }

    /**
     * addAlias
     *
     * Registers the name under which an imported class is used in the code.
     *
     * @param   array        $aliases  Alias => fully qualified name
     * @param   string       $fqn      The imported name
     * @param   string|null  $alias    The "as" alias, if any
     *
     * @return void
     *
     * @since  __DEPLOY_VERSION__
     */
    protected static function addAlias(array &$aliases, string $fqn, ?string $alias): void
    {
        if ($alias === null || $alias === '') {
            $path  = explode('\\', $fqn);
            $alias = end($path);
        }

        if ($alias !== '' && $alias !== false) {
            $aliases[$alias] = $fqn;
        }
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

    /**
     * loadXml
     *
     * Parse an XML file from the checked package. The package is untrusted input: no network access
     * while parsing (LIBXML_NONET), no entity substitution, and parse errors are collected instead of
     * being printed into the report as PHP warnings.
     *
     * @param   string  $file  The XML file
     *
     * @return  \SimpleXMLElement|false  False when it is not well-formed XML
     *
     * @since   __DEPLOY_VERSION__
     */
    public static function loadXml(string $file): \SimpleXMLElement|false
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_file($file, \SimpleXMLElement::class, LIBXML_NONET);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $xml instanceof \SimpleXMLElement ? $xml : false;
    }

    /**
     * workPath
     *
     * The folder JED Checker's own screen uploads and unpacks into: one per administrator, named with
     * an HMAC of the user id and the site secret. Packages contain PHP, and Joomla's tmp_path is often
     * inside the web root - a fixed, guessable folder (the former "tmp/jed_checker") would let anyone
     * who can upload a package request its PHP files over the web where .htaccess is not honoured
     * (nginx). A per-user folder also keeps two administrators from overwriting each other's upload.
     *
     * @param   int|null  $userId  The user; the current one when null
     *
     * @return  string
     *
     * @since   __DEPLOY_VERSION__
     */
    public static function workPath(?int $userId = null): string
    {
        $app    = Factory::getApplication();
        $userId = $userId ?? (int) $app->getIdentity()->id;
        $secret = (string) $app->get('secret');

        return rtrim((string) $app->get('tmp_path'), '/\\') . '/jed_checker_' . substr(hash_hmac('sha256', 'jedchecker:' . $userId, $secret), 0, 24);
    }

    /**
     * isInsidePackage
     *
     * Whether a path taken from a manifest stays inside the package. A manifest entry such as
     * "../../../../etc/passwd" must not be used to probe the server's file system.
     *
     * @param   string  $relativePath  A path relative to the manifest's folder
     *
     * @return  bool
     *
     * @since   __DEPLOY_VERSION__
     */
    public static function isInsidePackage(string $relativePath): bool
    {
        $path = str_replace('\\', '/', $relativePath);

        if ($path !== '' && ($path[0] === '/' || preg_match('#^[a-zA-Z]:/#', $path) || str_contains($path, '://'))) {
            return false;
        }

        return ! \in_array('..', explode('/', $path), true);
    }

    /**
     * exceedsSizeLimit
     *
     * Whether an archive declares more uncompressed data than the given budget - checked before it is
     * unpacked, so that a small "zip bomb" is refused instead of filling the disk or the memory.
     * Zip archives are measured from their central directory (needs the zip extension), gzip files from
     * their size trailer; other formats cannot be measured up front and pass.
     *
     * @param   string  $archive  The archive
     * @param   int     $budget   Bytes still allowed
     *
     * @return  bool
     *
     * @since   __DEPLOY_VERSION__
     */
    public static function exceedsSizeLimit(string $archive, int $budget): bool
    {
        $name = strtolower($archive);

        if (str_ends_with($name, '.zip') && class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();

            if ($zip->open($archive) !== true) {
                return false;
            }

            $total = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat   = $zip->statIndex($i);
                $total += $stat ? (int) $stat['size'] : 0;

                if ($total > $budget) {
                    $zip->close();

                    return true;
                }
            }

            $zip->close();

            return false;
        }

        if (preg_match('/\.(?:gz|gzip|tgz)$/', $name) && filesize($archive) >= 18) {
            // ISIZE: the uncompressed size modulo 2^32, in the last four bytes.
            $handle = fopen($archive, 'rb');

            if ($handle === false) {
                return false;
            }

            fseek($handle, -4, SEEK_END);
            $isize = unpack('V', (string) fread($handle, 4));
            fclose($handle);

            return \is_array($isize) && $isize[1] > $budget;
        }

        return false;
    }

    /**
     * protectDirectory
     *
     * Prevent execution of any extracted/uploaded file if this directory (or a subdirectory of it)
     * is ever reachable over the web, e.g. because Joomla's tmp_path was left at its default,
     * inside the site's webroot. The package contents are fully attacker-controlled, so any
     * .htaccess/web.config bundled inside the archive is removed first to stop it overriding
     * the rules written here.
     *
     * @param   string  $path  Directory to protect
     *
     * @return  void
     *
     * @since   3.0.0
     */
    public static function protectDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        foreach (Folder::files($path, '^(\.htaccess|web\.config|\.user\.ini)$', true, true, [], []) as $existing) {
            File::delete($existing);
        }

        File::write(
            $path . '/.htaccess',
            "<FilesMatch \"\\.(php[0-9]*|phtml|phar|pl|py|jsp|asp|aspx|sh|cgi|exe|dll|so)\$\">\n"
            . "    <IfModule mod_authz_core.c>\n"
            . "        Require all denied\n"
            . "    </IfModule>\n"
            . "    <IfModule !mod_authz_core.c>\n"
            . "        Order allow,deny\n"
            . "        Deny from all\n"
            . "    </IfModule>\n"
            . "</FilesMatch>\n"
            . "<IfModule mod_php.c>\n"
            . "    php_flag engine off\n"
            . "</IfModule>\n"
            . "<IfModule mod_php7.c>\n"
            . "    php_flag engine off\n"
            . "</IfModule>\n"
            . "<IfModule mod_php8.c>\n"
            . "    php_flag engine off\n"
            . "</IfModule>\n"
        );

        File::write(
            $path . '/web.config',
            "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<configuration>\n"
            . "    <system.webServer>\n"
            . "        <handlers>\n"
            . "            <clear />\n"
            . "        </handlers>\n"
            . "        <security>\n"
            . "            <requestFiltering>\n"
            . "                <fileExtensions>\n"
            . "                    <add fileExtension=\".php\" allowed=\"false\" />\n"
            . "                    <add fileExtension=\".phtml\" allowed=\"false\" />\n"
            . "                    <add fileExtension=\".phar\" allowed=\"false\" />\n"
            . "                    <add fileExtension=\".asp\" allowed=\"false\" />\n"
            . "                    <add fileExtension=\".aspx\" allowed=\"false\" />\n"
            . "                    <add fileExtension=\".exe\" allowed=\"false\" />\n"
            . "                    <add fileExtension=\".dll\" allowed=\"false\" />\n"
            . "                </fileExtensions>\n"
            . "            </requestFiltering>\n"
            . "        </security>\n"
            . "    </system.webServer>\n"
            . "</configuration>\n"
        );
    }
}
