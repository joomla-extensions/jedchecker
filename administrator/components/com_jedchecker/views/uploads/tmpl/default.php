<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2019 Open Source Matters, Inc. All rights reserved.
 * 			   Copyright (C) 2008 - 2016 compojoom.com . All rights reserved.
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');

if (version_compare(JVERSION, '3.3.0', '>='))
{
	JHtml::_('behavior.core');
}
else
{
	JHtml::_('behavior.framework', true);
}

JHtml::stylesheet('media/com_jedchecker/css/style.min.css');

$document = JFactory::getDocument();

$options = json_encode($this->jsOptions);
$document->addScriptDeclaration(<<<END
	function add_validation() {
		// Fetch all the forms we want to apply custom Bootstrap validation styles to
		var forms = document.getElementsByClassName('needs-validation');
		// Loop over them and prevent submission
		var validation = Array.prototype.filter.call(forms, function(form) {
			form.addEventListener('submit', function(event) {
				if (form.checkValidity() === false) {
					event.preventDefault();
					event.stopPropagation();
				}
				form.classList.add('was-validated');
			}, false);
		});
	}

	function check(url,rule) {
		jQuery.ajax({
			url: url + 'index.php?option=com_jedchecker&task=police.check&format=raw&rule='+rule,
			method: 'GET',
			success: function(result){
				jQuery('#police-check-result').append(result);
			}
		});
	}

	Joomla.submitbutton = function (task) {
		var options = $options;

		if (task == 'check') {
			jQuery("#police-check-result").empty();

			for (index = 0; index < options["rules"].length; ++index) {
				check(options["url"],options["rules"][index]);
			}

			jQuery("#prison" ).show();

		}  else {
			Joomla.submitform(task);
		}
	}
END
);

	if ( version_compare(JVERSION, '3.20', '<') || version_compare(JVERSION, '4.0', '>=') ) {
	?>
	<!-- Styling of Bootstrap 4 core CSS-->
	<link href="<?php echo JURI::root(); ?>media/com_jedchecker/css/j3-style.min.css" rel="stylesheet">
	<?php } ?>

	<div class="row">
		<div class="col-xs-12 col-md-8">
			<form action="<?php echo JRoute::_('index.php?option=com_jedchecker&view=uploads'); ?>"
			  method="post" class="needs-validation" name="adminForm" id="adminForm" enctype="multipart/form-data">

				<div class="card bg-light mb-3">
					<div class="card-body">
						<p class="card-text"><?php echo JText::sprintf('COM_JEDCHECKER_CONGRATS', 'https://extensions.joomla.org/community/terms-of-service/'); ?></p>
						<p class="card-text"><?php echo JText::sprintf('COM_JEDCHECKER_CODE_STANDARDS', 'https://developer.joomla.org/coding-standards.html'); ?></p>
						<p class="card-text"><?php echo JText::_('COM_JEDCHECKER_HOW_TO_USE'); ?></p>
						<p class="card-text">
							<ol>
								<li> <?php echo JText::_('COM_JEDCHECKER_STEP1'); ?></li>
								<li> <?php echo JText::_('COM_JEDCHECKER_STEP2'); ?></li>
							</ol>
						</p>
						<div class="form-row">
							<div class="col-md-6 mb-3">
								<div class="custom-file">
									<input type="file" class="custom-file-input" name="extension" id="extension" required>
									<label class="custom-file-label" for="extension"><?php echo JText::_('COM_JEDCHECKER_UPLOAD_FILE'); ?></label>
									<div class="invalid-feedback"><?php echo JText::_('COM_JEDCHECKER_EMPTY_UPLOAD_FIELD'); ?></div>
								</div>
							</div>
							<div class="col-md-6 mb-3">
								<button onclick="add_validation(); Joomla.submitbutton('uploads.upload')" class="btn btn-success">
									<span class="icon-upload "></span> <?php echo JText::_('JSUBMIT'); ?>
								</button>
							</div>
						</div>
					</div>
				</div>

				<input type="hidden" name="task" value=""/>
				<?php echo JHtml::_('form.token'); ?>

			</form>
		</div>

		<div class="col-xs-6 col-md-4">

			<div class="card text-white bg-info mb-3">
				<div class="card-header"><?php echo JText::_('COM_JEDCHECKER_WALL_OF_HONOR'); ?></div>
				<div class="card-body">
					<h5 class="card-title"><?php echo JText::_('COM_JEDCHECKER_PEOPLE_THAT_HAVE_HELPED_WITH_THE_DEVELOPMENT'); ?></h5>
					<p class="card-text">
						<a href="https://github.com/joomla-extensions/jedchecker/graphs/contributors" target="_blank" class="btn btn-light">
						<?php echo JText::_('COM_JEDCHECKER_CONTRIBUTORS'); ?></a>
					</p>
				</div>
			</div>
		</div>
	</div>

	<div id="prison" style="display: none">
		<div class="row">

			<div class="col-md-8">
				<div class="card bg-light mb-3">
					<div class="card-header"><?php echo JText::_('COM_JEDCHECKER_RESULTS'); ?></div>
					<div class="card-body">
						<p class="card-text">
							<div id="police-check-result"></div>
						</p>
					</div>
					<div class="card-footer">
					  <small class="text-muted">
						<?php echo JText::sprintf('COM_JEDCHECKER_LEAVE_A_REVIEW_JED', 'https://extensions.joomla.org/extensions/tools/development-tools/21336'); ?>
						<?php echo JText::sprintf('COM_JEDCHECKER_DEVELOPED_BY', 'https://github.com/joomla-extensions/jedchecker'); ?> :)
					  </small>
					</div>
				</div>
			</div>

			<div class="col-md-4">
				<div class="card bg-light mb-3">
					<div class="card-header"><?php echo JText::_('COM_JEDCHECKER_HOW_TO_INTERPRET_RESULTS'); ?></div>
					<div class="card-body">
						<p class="card-text">
							<div id="accordion">
								<?php
								foreach ($this->jsOptions['rules'] AS $rule) {
									$class = 'jedcheckerRules' . ucfirst($rule);

									if (!class_exists($class)) continue;
									$rule = new $class();
									?>
									<div class="card">
										<?php
											echo '<div class="card-header" id="heading' . $rule->get('id') .'">';
										?>
											<h5 class="mb-0">
										<?php
											echo '<button class="btn btn-link" data-toggle="collapse" data-target="#collapse' . $rule->get('id') . '" aria-expanded="true" aria-controls="collapse' . $rule->get('id') . '">' . JText::_('COM_JEDCHECKER_RULE') . ' ' . $rule->get('id') . ' - ' . JText::_($rule->get('title'));
										?>
												</button>
											</h5>
										</div>

										<?php
											echo '<div id="collapse' . $rule->get('id') . '" class="collapse" aria-labelledby="heading' . $rule->get('id') . '" data-parent="#accordion">';
										?>
											<div class="card-body">
												<?php echo JText::_($rule->get('description')); ?>
											</div>
										</div>
									</div>
								<?php
								}
								?>
							</div>
						</p>
					</div>
				</div>
			</div>
		</div>
	</div>
