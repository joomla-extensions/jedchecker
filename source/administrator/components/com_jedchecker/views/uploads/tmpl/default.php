<?php
/**
 * @author Daniel Dimitrov - compojoom.com
 * @date: 02.06.12
 *
 * @copyright  Copyright (C) 2008 - 2012 compojoom.com . All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE
 */

defined('_JEXEC') or die('Restricted access');

JHTML::_('behavior.framework', true);
JHtml::_('behavior.formvalidation');
JHtml::_('behavior.keepalive');
JHtml::stylesheet('media/com_jedchecker/css/css.css');
JHtml::script('media/com_jedchecker/js/police.js');
?>
<script type="text/javascript">
	Joomla.submitbutton = function (task) {
		var options = <?php echo json_encode($this->jsOptions); ?>;
		if (task == 'police.check') {
			new police(options);
			return false;
		}
		Joomla.submitform(task);
	}
</script>

<div class="fltlft width-60 span8">
	<form action="<?php echo JRoute::_('index.php?option=com_jedchecker&view=uploads'); ?>"
		  method="post" class="form form-validate" name="adminForm" id="adminForm" enctype="multipart/form-data">
		<fieldset>
			<p>
				<?php echo JText::sprintf('COM_JEDCHECKER_CONGRATS', 'http://extensions.joomla.org/index.php?option=com_content&id=50'); ?>
			</p>

			<p>
				<?php echo JText::sprintf('COM_JEDCHECKER_CODE_STANDARDS', 'http://developer.joomla.org/coding-standards.html', 'https://github.com/compojoom/jedchecker'); ?>
			</p>

			<p>
				<?php echo JText::_('COM_JEDCHECKER_HOW_TO_USE'); ?>
			</p>
			<ol>
				<li> 1. <?php echo JText::_('COM_JEDCHECKER_STEP1'); ?></li>
				<li> 2. <?php echo JText::_('COM_JEDCHECKER_STEP2'); ?></li>
				<li> 3. <?php echo JText::_('COM_JEDCHECKER_STEP3'); ?></li>
			</ol>


			<input type="file" name="extension" class="required"/>
			<button onclick="Joomla.submitbutton('uploads.upload')">
				<?php echo JText::_('JSUBMIT'); ?>
			</button>
			<input type="hidden" name="task" value=""/>
			<?php echo JHTML::_('form.token'); ?>
		</fieldset>
	</form>
</div>
<div class="fltrt width-40 span4">
	<div class="help">
		<h2><?php echo JText::_('COM_JEDCHECKER_WALL_OF_HONOR'); ?></h2>

		<p><?php echo JText::_('COM_JEDCHECKER_PEOPLE_THAT_HAVE_HELPED_WITH_THE_DEVELOPMENT'); ?></p>
		<ul>
			<li>Tobias Kuhn (<a href="http://projectfork.net" target="_blank">projectfork</a>)</li>
			<li>Jisse Reitsma (<a href="http://www.yireo.com/" target="_blank">yireo</a>)</li>
			<li>Denis Dulici (<a href="http://mijosoft.com/" target="_blank">mijosoft</a>)</li>
			<li>Peter van Westen (<a href="http://www.nonumber.nl" target="_blank">NoNumber</a>)</li>
			<li>Alain Rivest (<a href="http://aldra.ca" target="_blank">Aldra.ca</a>)</li>
			<li>OpenTranslators (<a href="http://opentranslators.org" target="_blank">opentranslators.org</a>)</li>
		</ul>
	</div>
</div>
<div id="prison" style="display:none;">
	<div class="fltlft width-60 span8">
		<div id="police-check-result"></div>
	</div>
	<div class="fltrt width-40 span4">
		<div class="help">
			<h2>
				<?php echo JText::_('COM_JEDCHECKER_HOW_TO_INTERPRET_RESULTS'); ?>
			</h2>
			<ul>
				<?php
				foreach ($this->jsOptions['rules'] AS $rule) {
					$class = 'jedcheckerRules' . ucfirst($rule);

					if (!class_exists($class)) continue;
					$rule = new $class();
					?>
					<li>
						<p>
                            <span class="rule">
                                <?php echo JText::_('COM_JEDCHECKER_RULE') . ' ' . $rule->get('id') . ' - ' . JText::_($rule->get('title'));?>
                            </span>

						<p><?php echo JText::_($rule->get('description')); ?></p>
						</p>
					</li>
				<?php
				}
				?>
			</ul>
		</div>
	</div>
</div>
<div class="clr clearfix"></div>
<div class="copyright row-fluid">
	<?php echo JText::sprintf('COM_JEDCHECKER_LEAVE_A_REVIEW_JED', 'http://extensions.joomla.org/extensions/tools/development-tools/21336'); ?>
	<br/>
	<?php echo JText::sprintf('COM_JEDCHECKER_DEVELOPED_BY', 'https://compojoom.com'); ?> :)
</div>
