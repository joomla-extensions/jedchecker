<?php

/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2026 Open Source Matters, Inc. All rights reserved.
 *             Copyright (C) 2008 - 2016 compjoom.com All rights reserved.
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
 * EncodingRule checks if base64 encoding is used in extension files.
 *
 * @since  3.0.0
 */
class EncodingRule extends AbstractRule
{
    /**
     * Rule ordering.
     *
     * @var integer
     * @since 3.0.0
     */
    public static int $ordering = 900;
    /**
     * Rule ID.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $id = 'encoding';
    /**
     * Rule title.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $title = 'COM_JEDCHECKER_RULE_ENCODING';
    /**
     * Description of the rule.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $description = 'COM_JEDCHECKER_RULE_ENCODING_DESC';
    /**
     * Regex for matching encodings.
     *
     * @var string
     * @since 3.0.0
     */
    protected string $encodingsRegex;

    /**
     * check
     *
     * Runs the rule.
     *
     * @since  3.0.0
     */
    public function check(): void
    {
        $encodings = explode(',', $this->params->get('encodings'));

        foreach ($encodings as $i => $encoding) {
            $encodings[$i] = preg_quote(trim($encoding), '/');
        }

        $this->encodingsRegex = '/' . implode('|', $encodings) . '/i';

        $files = Folder::files($this->basedir, '\.php$', true, true);

        foreach ($files as $file) {
            $this->find($file);
        }
    }

    /**
     * find
     *
     * Checks if the file contains any of the specified encodings.
     *
     * @param   string  $file
     *
     * @return boolean
     *
     * @since  1.0
     */
    protected function find(string $file): bool
    {
        $content     = file_get_contents($file);
        $origContent = CheckerHelper::splitLines($content);

        $content = CheckerHelper::cleanPhpCode(
            $content,
            CheckerHelper::CLEAN_HTML | CheckerHelper::CLEAN_COMMENTS
        );
        $content = CheckerHelper::splitLines($content);

        $found = false;

        foreach ($content as $i => $line) {
            if (preg_match($this->encodingsRegex, $line)) {
                $found = true;
                $this->report->addWarning($file, Text::_('COM_JEDCHECKER_ERROR_ENCODING'), $i + 1, $origContent[$i]);
            }
        }

        return $found;
    }
}
