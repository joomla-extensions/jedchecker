<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2026 Open Source Matters, Inc. All rights reserved.
 *             Copyright (C) 2008 - 2016 fasterjoomla.com. All rights reserved.
 * @author     Riccardo Zorn <support@fasterjoomla.com>
 *             Bernard Toplak <bernard@orion-web.hr>
 *
 * @license    GNU General Public Licence version 2 or later; see LICENCE.txt
 */

namespace Joomla\Component\Jedchecker\Administrator\Rule\Rules;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Language\Text;
use Joomla\Component\Jedchecker\Administrator\Helper\CheckerHelper;
use Joomla\Component\Jedchecker\Administrator\Rule\AbstractRule;
use Joomla\Filesystem\Folder;

/**
 * JamssRule attempts to identify deprecated code, unsafe code, leftover stuff.
 *
 * @since  3.0.0
 */
class JamssRule extends AbstractRule
{
    /**
     * Rule ordering.
     *
     * @var integer
     * @since 3.0.0
     */
    public static int $ordering = 1000;
    /**
     * Rule ID.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $id = 'Jamss';
    /**
     * Rule title.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $title = 'COM_JEDCHECKER_RULE_JAMSS';
    /**
     * Description of the rule.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $description = 'COM_JEDCHECKER_RULE_JAMSS_DESC';
    /**
     * Rule type.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $ext = '';

    /**
     * Patterns to search for.
     *
     * @var array
     * @since 3.0.0
     */
    protected array $patterns = [];

    /**
     * Malicious file names.
     *
     * @var array
     * @since 3.0.0
     */
    protected array $jamssFileNames = [];

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

        $files = Folder::files($this->basedir, '', true, true);

        $this->initJamss();

        foreach ($files as $file) {
            $this->find($file);
        }
    }

    /**
     * initJamss
     *
     * Initializes Jamss rule with predefined patterns and file names.
     *
     * @param   bool  $deepscan
     *
     * @since  3.0.0
     */
    private function initJamss(bool $deepscan = true): void
    {
        // Patterns Start

        $jamssStrings = 'r0nin|m0rtix|upl0ad|r57shell|c99shell|shellbot|phpshell|void\.ru|';
        $jamssStrings .= 'phpremoteview|directmail|bash_history|multiviews|cwings|vandal|bitchx|';
        $jamssStrings .= 'eggdrop|guardservices|psybnc|dalnet|undernet|vulnscan|spymeta|raslan58|';
        $jamssStrings .= 'Webshell|str_rot13|FilesMan|FilesTools|Web Shell|ifrm|bckdrprm|';
        $jamssStrings .= 'hackmeplz|wrgggthhd|WSOsetcookie|Hmei7|Inbox Mass Mailer|HackTeam|Hackeado|';
        $jamssStrings .= 'Janissaries|Miyachung|ccteam|Adminer|OOO000000|$GLOBALS|findsysfolder';

        $jamssDeepSearchStrings = 'eval|base64_decode|base64_encode|gzdecode|gzdeflate|';
        $jamssDeepSearchStrings .= 'gzuncompress|gzcompress|readgzfile|zlib_decode|zlib_encode|';
        $jamssDeepSearchStrings .= 'gzfile|gzget|gzpassthru|iframe|strrev|lzw_decompress|strtr|';
        $jamssDeepSearchStrings .= 'exec|passthru|shell_exec|system|proc_|popen';

        $jamssPatterns = [
                [
                        'preg_replace\s*\(\s*[\"\']\s*(\W)(?-s).*\1[imsxADSUXJu\s]*e[imsxADSUXJu\s]*[\"\'].*\)',
                        'PHP: preg_replace Eval',
                        '1',
                        'Detected preg_replace function that evaluates (executes) matched code. ' .
                        'This means if PHP code is passed it will be executed . ',
                        'php',
                        'Part example code from http://sucuri.net/malware/backdoor-phppreg_replaceeval',
                ],
                [
                        'c999*sh_surl',
                        'Backdoor: PHP:C99:045',
                        '2',
                         'Detected the "C99? backdoor that allows attackers to manage (and reinfect) your site remotely. ' .
                        'It is often used as part of a compromise to maintain access to the hacked sites . ',
                        'php',
                        'http://sucuri.net/malware/backdoor-phpc99045',
                ],
                [
                        'preg_match\s*\(\s*\"\s*/\s*bot\s*/\s*\"',
                        'Backdoor: PHP:R57:01',
                        '3',
                        'Detected the "R57? backdoor that allows attackers to access, modify and reinfect your site. ' .
                        'It is often hidden in the filesystem and hard to find without access to the server or logs . ',
                        'php',
                        'http://sucuri.net/malware/backdoor-phpr5701',
                ],
                [
                        'eval[\s/\*\#]*\(stripslashes[\s/\*\#]*\([\s/\*\#]*\$_(REQUEST|POST|GET)\s*\[\s*\\\s*[\'\"]\s*asc\s*\\\s*[\'\"]',
                        'Backdoor: PHP:GENERIC:07',
                        '5',
                        'Detected a generic backdoor that allows attackers to ' .
                        'upload files, delete files, access, modify and/or reinfect your site. ' .
                        'It is often hidden in the filesystem and hard to find without access to the server or logs. ' .
                        'It also includes uploadify scripts and similars that offer upload options without security. ',
                        'php',
                        'http://sucuri.net/malware/backdoor-phpgeneric07',
                ],
                [
                        'preg_replace\s*\(\s*[\"\'\"]\s*/\s*\.\s*\*\s*/\s*e\s*[\"\'\"]\s*,\s*[\"\'\"]\s*\\x65\\x76\\x61\\x6c',
                        'Backdoor: PHP:Filesman:02',
                        '7',
                        'Detected the "Filesman" backdoor that allows attackers to access, modify and reinfect your site. ' .
                        'It is often hidden in the filesystem and hard to find without access to the server or logs . ',
                        'php',
                        'http://sucuri.net/malware/backdoor-phpfilesman02',
                ],
                [
                        '(include|require)(_once)*\s*[\"\'][\w\W\s/\*]*php://input[\w\W\s/\*]*[\"\']',
                        'PHP:\input include',
                        '8',
                        'Detected the method of reading input through PHP protocol handler in include/require statements . ',
                        'php',
                ],
                [
                        'data:;base64',
                        'data:;base64 include',
                        '9',
                        'Detected the method of executing base64 data in include . ',
                        'php',
                ],
                [
                        'RewriteCond\s*%\{HTTP_REFERER\}',
                        '.HTACCESS RewriteCond-Referer',
                        '10',
                        'Your .htaccess file has a conditional redirection based on "HTTP Referer". ' .
                        'This means it redirects according to site/url from where your visitors came to your site. ' .
                        'Such technique has been used for unwanted redirections after coming from Google or other search engines, ' .
                        'so check this directive carefully . ',
                        'full',
                ],
                [
                        'brute\s*force',
                        '"Brute Force" words',
                        '11',
                        'Detected the "Brute Force" words mentioned in code. <u>Sometimes it\'s a "false positive"</u> because ' .
                        'several developers like to mention it in they code, but it\'s worth double-checking if this file ' .
                        'is untouched (eg. compare it with one in original extension package) . ',
                        'full',
                ],
                [
                        'GIF89a.*[\r\n]*.*<\?php',
                        'PHP file desguised as GIF image',
                        '15',
                        'Detected a PHP file that was most probably uploaded as an image via webform that loosely only checks ' .
                        'file headers . ',
                        'full',
                ],
                [
                        '\$ip[\w\W\s/\*]*=[\w\W\s/\*]*getenv\(["\']REMOTE_ADDR["\']\);[\w\W\s/\*]*[\r\n]\$message',
                        'Probably malicious PHP script that "calls home"',
                        '16',
                        'Detected script variations often used to inform the attackers about found vulnerable website . ',
                        'php',
                ],
                [
                        '(?:\b(?:eval|gzuncompress|gzinflate|base64_decode|str_rot13|strrev|strtr|rawurldecode|' .
                        'assert|unpack|urldecode)[\s/\*\w\W\(]*){2,}',
                        'PHP: multiple encoded, most probably obfuscated code found',
                        '17',
                        'This pattern could be used in highly encoded, malicious code hidden under a loop of code obfuscation function ' .
                        'calls. In most cases the decoded hacker code goes through an eval call to execute it. ' .
                        'This pattern is also often used for legitimate purposes, e.g. storing configuration information or ' .
                        'serialised object data. ' .
                        'Please inspect the file manually and compare it with the one in the original extension or ' .
                        'Joomla package to verify that this is not a false positive . ',
                        'code',
                        'Thanks to Dario Pintarić (dario.pintaric[et}orion-web.hr for this report!',
                ],
                [
                        '<\s*iframe',
                        'IFRAME element',
                        '18',
                        'Found IFRAME element in code. It\'s mostly benevolent, but often used for bad stuff, ' .
                        'so please check if it\'s a valid code . ',
                        'clean',
                ],
                [
                        'strrev[\s/\*\#]*\([\s/\*\#]*[\'"]\s*tressa\s*[\'"]\s*\)',
                        'Reversed string "assert"',
                        '19',
                        'Assert function name is being hidden behind strrev() . ',
                        'php',
                ],
                [
                        'is_writable[\s/\*\#]*\([\s/\*\#]*getcwd',
                        'Is the current DIR Writable?',
                        '20',
                        'This could be harmless, but used in some malware',
                        'code',
                ],
                [
                        '(?:\\\\x[0-9A-Fa-f]{1,2}|\\\\[0-7]{1,3}){2,}',
                        'At least two characters in hexadecimal or octal notation',
                        '21',
                        'Found at least two characters in hexadecimal or octal notation. It doesn\'t mean it is malicious, ' .
                        'but it could be code hiding behind such notation . ',
                        'php',
                ],
                [
                        '\$_F\s*=\s*__FILE__\s*;\s*\$_X\s*=',
                        'SourceCop encoded code',
                        '22',
                        'Found the SourceCop encoded code. It is often used for malicious code ' .
                        'hiding, so go and check the code with some online SourceCop decoders',
                        'code',
                ],
                [
                        '\b(?:exec|passthru|shell_exec|system|proc_\w+|popen)\b[\w\W\s/\*]*\([\s/\*\#\'\"\w\W\-\_]*(?:\$_GET|\$_POST)',
                        'shell command execution from POST/GET variables',
                        '23',
                        'Found direct shell command execution getting variables from POST/GET, ' .
                        'which is highly dangerous security flaw or a part of malicious webrootkit',
                        'code',
                ],
                [
                        '`',
                        'PHP execution operator: backticks (``)',
                        '24',
                        'PHP execution operator found. Note that these are not single-quotes! ' .
                        'PHP will attempt to execute the contents of the backticks as a shell ' .
                        'command, which might indicate a part of a webrootkit',
                        'code',
                ],
        ];

        $jamssFileNames = [
                'Probably an OpenFlashChart library demo file that has known input validation error (CVE-2009-4140)'
                => 'ofc_upload_image.php',
                'Probably an R57 shell'
                => 'r57.php',
                'PhpInfo() file? It is advisable to remove such file, as it could reveal too
			much info to potential attackers'
                => 'phpinfo.php',
        ];

        // Patterns End

        if (isset($_GET['deepscan'])) {
            $patterns = array_merge($jamssPatterns, explode('|', $jamssStrings), explode('|', $jamssDeepSearchStrings));
        } else {
            $patterns = array_merge($jamssPatterns, explode('|', $jamssStrings));
        }

        $this->patterns       = $patterns;
        $this->jamssFileNames = $jamssFileNames;
        $validExtensions      = explode('|', $this->params->get('fileExt'));
        $this->ext            = implode('|', $validExtensions);
    }

    /**
     * find
     *
     * Searches for malicious patterns and file names within a PHP file.
     *
     * @param   string  $file
     *
     * @return bool
     *
     * @since  3.0.0
     */
    protected function find(string $file): bool
    {
        $this->scanFile($file);

        return false;
    }

    /**
     * scanFile
     *
     * Checks if a PHP file contains malicious patterns and file names.
     *
     * @param   string  $path
     *
     * @return bool
     *
     * @since  3.0.0
     */
    private function scanFile(string $path): bool
    {
        $ext            = explode('|', $this->ext);
        $patterns       = $this->patterns;
        $total_results  = 0;
        $jamssFileNames = $this->jamssFileNames;

        if (in_array(pathinfo($path, PATHINFO_EXTENSION), $ext) && filesize($path)) {
            if ($malic_file_descr = array_search(pathinfo($path, PATHINFO_BASENAME), $jamssFileNames)) {
                $this->jamssWarning(
                    $path,
                    Text::_('COM_JEDCHECKER_ERROR_JAMSS_SUSPICIOUS_FILENAME'),
                    $malic_file_descr,
                    '',
                    0
                );
            }

            $content = file_get_contents($path);

            if (! $content) {
                $this->report->addError($path, Text::_('COM_JEDCHECKER_ERROR_JAMSS_CANNOT_OPEN'), 0);

                return true;
            }

            $origContent = CheckerHelper::splitLines($content);
            $scopes      = [
                    'full'  => $content,
                    'clean' => CheckerHelper::cleanPhpCode($content, CheckerHelper::CLEAN_COMMENTS),
                    'php'   => CheckerHelper::cleanPhpCode(
                        $content,
                        CheckerHelper::CLEAN_COMMENTS | CheckerHelper::CLEAN_HTML
                    ),
                    'code'  => CheckerHelper::cleanPhpCode(
                        $content,
                        CheckerHelper::CLEAN_COMMENTS | CheckerHelper::CLEAN_HTML | CheckerHelper::CLEAN_STRINGS
                    ),
            ];

            foreach ($patterns as $pattern) {
                $scope          = (is_array($pattern) && isset($pattern[4])) ? $pattern[4] : 'clean';
                $scoped_content = $scopes[$scope];

                if (is_array($pattern)) {
                    preg_match_all('#' . $pattern[0] . '#isS', $scoped_content, $found, PREG_OFFSET_CAPTURE);
                } else {
                    preg_match_all('#' . $pattern . '#isS', $scoped_content, $found, PREG_OFFSET_CAPTURE);
                }

                $all_results   = $found[0];
                $results_count = count($all_results);
                $total_results += $results_count;
                $first_line    = 0;
                $first_code    = '';

                if (! empty($all_results)) {
                    foreach ($all_results as $match) {
                        $offset = $match[1];
                        $start  = strrpos($scoped_content, "\n", $offset - strlen($scoped_content));

                        if ($start === false) {
                            $start = 0;
                        }

                        $end = strpos($scoped_content, "\n", $offset);

                        if ($end === false) {
                            $end = strlen($scoped_content);
                        }

                        $first_line = $this->calculateLineNumber($offset, $scoped_content);
                        $first_code = $origContent[$first_line - 1];
                        break;
                    }

                    if (is_array($pattern)) {
                        $this->jamssWarning(
                            $path,
                            Text::_('COM_JEDCHECKER_ERROR_JAMSS_PATTERN') . "#$pattern[2] - $pattern[1]",
                            $pattern[3],
                            $first_code,
                            $first_line
                        );
                    } else {
                        $this->jamssWarning(
                            $path,
                            Text::_('COM_JEDCHECKER_ERROR_JAMSS_STRING') . $pattern,
                            '',
                            $first_code,
                            $first_line
                        );
                    }
                }
            }
        }

        return false;
    }

    /**
     * jamssWarning
     *
     * Logs a warning for a Jamss rule violation.
     *
     * @param   string  $path
     * @param   string  $title
     * @param   mixed   $info
     * @param   string  $code
     * @param   int     $line
     *
     * @since  3.0.0
     */
    private function jamssWarning(string $path, string $title, mixed $info, string $code, int $line): void
    {
        $info = ! empty($info) ? sprintf($this->params->get('info'), htmlentities($info, ENT_QUOTES)) : '';
        $this->report->addWarning($path, $info . $title, $line, $code);
    }

    private function calculateLineNumber(int $length, string $fileContent, int $offset = 0): int
    {
        return substr_count($fileContent, "\n", $offset, $length) + 1;
    }
}
