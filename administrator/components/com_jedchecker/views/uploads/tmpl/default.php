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

// Load Joomla framework
if (version_compare(JVERSION, '3.3.0', '>='))
{
	JHtml::_('behavior.core');
}
else
{
	JHtml::_('behavior.framework', true);
}

// Load jQuery
JHtml::_('jquery.framework');

// Load Bootstrap
if (version_compare(JVERSION, '4.0', '>='))
{
	JHtml::_('bootstrap.collapse');
	JHtml::_('bootstrap.tab');
}
else
{
	JHtml::stylesheet('media/com_jedchecker/css/j4-style.min.css');
	JHtml::script('media/com_jedchecker/js/bootstrap.min.js');
}

JHtml::stylesheet('media/com_jedchecker/css/style.css');
JHtml::script('media/com_jedchecker/js/script.js');

// List of rules
$options = json_encode($this->jsOptions);
JFactory::getDocument()->addScriptDeclaration("var jed_options = $options;");

// Load translation for "JED Checker" title from sys.ini file
JFactory::getLanguage()->load('com_jedchecker.sys', JPATH_ADMINISTRATOR);

?>
<div id="jedchecker">
	<div class="row g-3">
		<div class="col-12 col-md-8">
			<div class="card bg-light">
				<div class="card-header" data-bs-toggle="collapse" data-href="#jedchecker-welcome">
					<?php echo JText::_('COM_JEDCHECKER'); ?>
				</div>
				<div class="card-body show" id="jedchecker-welcome">
					<p class="card-text">
						<?php echo JText::sprintf('COM_JEDCHECKER_CONGRATS', 'https://extensions.joomla.org/community/terms-of-service/'); ?>
					</p>
					<p class="card-text">
						<?php echo JText::sprintf('COM_JEDCHECKER_CODE_STANDARDS', 'https://developer.joomla.org/coding-standards.html'); ?>
					</p>
					<p class="card-text">
						<?php echo JText::_('COM_JEDCHECKER_HOW_TO_USE'); ?>
					</p>
					<ol class="card-text">
						<li><?php echo JText::_('COM_JEDCHECKER_STEP1'); ?></li>
						<li><?php echo JText::_('COM_JEDCHECKER_STEP2'); ?></li>
					</ol>
					<form action="<?php echo JRoute::_('index.php?option=com_jedchecker&view=uploads'); ?>"
							method="post" class="needs-validation" name="adminForm" id="adminForm" enctype="multipart/form-data">
						<div class="input-group">
<?php /*
MIME type for accept attribute:
	application/zip			 => .zip (both Chromium and Firefox)
	application/x-gzip		 => .gz (both Chromium and Firefox),
								.tgz (Chromium only)
	application/x-compressed => .tgz (Firefox only)
	application/x-tar		 => .tar (both Chromium and Firefox)
Note: iOS Safari doesn't support file extensions in the accept attribute, so MIME types is the only working solution
*/ ?>
							<input type="file" class="form-control" name="extension" id="extension" required
									accept="application/zip,application/x-gzip,application/x-compressed,application/x-tar"
									aria-describedby="extension-upload" aria-label="<?php echo JText::_('COM_JEDCHECKER_UPLOAD_FILE'); ?>">
							<button class="btn btn-success" type="button" id="extension-upload"
									onclick="add_validation(); Joomla.submitbutton('uploads.upload')">
								<span class="icon-upload "></span> <?php echo JText::_('JSUBMIT'); ?>
							</button>
							<div class="invalid-feedback"><?php echo JText::_('COM_JEDCHECKER_EMPTY_UPLOAD_FIELD'); ?></div>
						</div>
						<input type="hidden" name="task" value=""/>
						<?php echo JHtml::_('form.token'); ?>
					</form>
				</div>
			</div>
		</div>

		<div class="col-6 col-md-4">
			<div class="card text-white bg-info">
				<div class="card-header text-white bg-info" data-bs-toggle="collapse" data-href="#jedchecker-contributors">
					<?php echo JText::_('COM_JEDCHECKER_WALL_OF_HONOR'); ?>
				</div>
				<div class="card-body show" id="jedchecker-contributors">
					<h5 class="card-title"><?php echo JText::_('COM_JEDCHECKER_PEOPLE_THAT_HAVE_HELPED_WITH_THE_DEVELOPMENT'); ?></h5>
					<p class="card-text">
						<a href="https://github.com/joomla-extensions/jedchecker/graphs/contributors" target="_blank" class="btn btn-light">
						<?php echo JText::_('COM_JEDCHECKER_CONTRIBUTORS'); ?></a>
					</p>
				</div>
			</div>
		</div>

		<div class="col-12 col-md-3 jedchecker-results hidden">
			<div class="card bg-light">
				<div class="card-header"><?php echo JText::_('COM_JEDCHECKER_RESULTS'); ?></div>
					<div role="tablist" class="list-group list-group-flush">
							<?php
							foreach ($this->jsOptions['rules'] as $i => $rulename)
							{
								$class = 'jedcheckerRules' . ucfirst($rulename);
								$rule = new $class;
								?>
								<a role="tab" id="jed-<?php echo $rulename; ?>" data-bs-toggle="tab" href="#jedtab-<?php echo $rulename; ?>"
								   class="list-group-item list-group-item-action d-flex justify-content-between<?php echo $i === 0 ? ' active' : ''; ?>">
									<?php echo JText::_($rule->get('title')); ?>
									<span class="text-nowrap ps-1">
										<span class="badge bg-danger rounded-pill border-error"></span>
										<span class="badge bg-warning rounded-pill"></span>
										<span class="badge bg-secondary rounded-pill"></span>
										<span class="badge bg-info rounded-pill"></span>
										<span class="badge bg-success rounded-pill hidden">&#x2713;</span>
										<span class="text-info spinner-border spinner-border-sm"></span>
									</span>
								</a>
							<?php
							}
							?>
					</div>
					<div class="card-text" id="police-check-result"></div>
				<div class="card-footer">
					<small class="text-muted">
					<?php echo JText::sprintf('COM_JEDCHECKER_LEAVE_A_REVIEW_JED', 'https://extensions.joomla.org/extensions/tools/development-tools/21336'); ?>
					<?php echo JText::sprintf('COM_JEDCHECKER_DEVELOPED_BY', 'https://github.com/joomla-extensions/jedchecker'); ?> :)
					</small>
				</div>
			</div>
		</div>

		<div class="col-12 col-md-9 jedchecker-results hidden">
			<div class="tab-content">
			<?php
			foreach ($this->jsOptions['rules'] as $i => $rulename)
			{
				$class = 'jedcheckerRules' . ucfirst($rulename);
				$rule = new $class;
				?>
				<div role="tabpanel" class="tab-pane fade<?php echo $i === 0 ? ' show active' : ''; ?>" id="jedtab-<?php echo $rulename; ?>">
				<div class="card">
					<div class="card-header" id="heading<?php echo $rule->get('id'); ?>">
						<?php echo JText::_($rule->get('title')); ?>
					</div>
					<div class="card-body">
						<p class="card-text">
							<?php echo JText::_($rule->get('description')); ?>
						</p>
						<div class="card-text police-check-result" id="police-check-result-<?php echo $rulename; ?>">
							<div class="text-center text-info"><span class="spinner-border"></span></div>
						</div>
					</div>
				</div>
				</div>
			<?php
			}
			?>
			</div>
		</div>
	</div>
</div>
