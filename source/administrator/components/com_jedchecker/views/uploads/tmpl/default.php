<?php
/**
 * @author Daniel Dimitrov - compojoom.com
 * @date: 02.06.12
 *
 * @copyright  Copyright (C) 2008 - 2012 compojoom.com . All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');

JHTML::_('behavior.mootools', true);
JHtml::_('behavior.formvalidation');
JHtml::_('behavior.keepalive');

?>
<form action="<?php echo JRoute::_('index.php?option=com_jedchecker&view=uploads'); ?>"
      method="post" class="form form-validate" name="adminForm" id="adminForm" enctype="multipart/form-data">

    <input type="file" name="extension" class="required" />
    <button onclick="Joomla.submitbutton('uploads.upload')">
        submit
    </button>
    <input type="hidden" name="task" value="" />
    <?php echo JHTML::_('form.token'); ?>
</form>