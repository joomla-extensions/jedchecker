<?php
/**
 * @package    Joomla.JEDChecker
 *
 * @copyright  Copyright (C) 2017 - 2022 Open Source Matters, Inc. All rights reserved.
 * 			   Copyright (C) 2008 - 2016 compojoom.com . All rights reserved.
 * @author     Daniel Dimitrov <daniel@compojoom.com>
 *
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die('Restricted access');

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

// Load Bootstrap
if (version_compare(JVERSION, '4.0', '>='))
{
	HTMLHelper::_('bootstrap.collapse');
	HTMLHelper::_('bootstrap.tab');

  	// Tooltips are used by JAMSS reports
	HTMLHelper::_('bootstrap.tooltip');
}
else
{
	HTMLHelper::_('behavior.core');
	HTMLHelper::_('stylesheet', 'com_jedchecker/j4-style.css', array('version' => 'auto', 'relative' => true));
	HTMLHelper::_('script', 'com_jedchecker/bootstrap.bundle.min.js', array('version' => 'auto', 'relative' => true), array('defer' => true));
}

HTMLHelper::_('stylesheet', 'com_jedchecker/style.css', array('version' => 'auto', 'relative' => true));
HTMLHelper::_('script', 'com_jedchecker/script.js', array('version' => 'auto', 'relative' => true), array('defer' => true));
?>
<script id="jed-rules-json" type="application/json"><?php echo json_encode($this->jsOptions); ?></script>
<div id="jedchecker">
	<div class="row g-3">
		<div class="col-12 col-md-8">
			<div class="card">
				<div class="card-header" data-bs-toggle="collapse" data-bs-target="#jedchecker-welcome" role="button">
					<?php echo Text::_('COM_JEDCHECKER'); ?>
				</div>
				<div class="card-body show" id="jedchecker-welcome">
					<p class="card-text">
						<?php echo Text::sprintf('COM_JEDCHECKER_CONGRATS', 'https://extensions.joomla.org/community/terms-of-service/'); ?>
					</p>
					<p class="card-text">
						<?php echo Text::sprintf('COM_JEDCHECKER_CODE_STANDARDS', 'https://developer.joomla.org/coding-standards.html'); ?>
					</p>
					<p class="card-text">
						<?php echo Text::_('COM_JEDCHECKER_HOW_TO_USE'); ?>
					</p>
					<ol class="card-text">
						<li><?php echo Text::_('COM_JEDCHECKER_STEP1'); ?></li>
						<li><?php echo Text::_('COM_JEDCHECKER_STEP2'); ?></li>
					</ol>
					<form action="<?php echo Route::_('index.php?option=com_jedchecker&view=uploads'); ?>"
							method="post" class="needs-validation" name="adminForm" id="adminForm" enctype="multipart/form-data">
						<div class="input-group">
							<input type="file" class="form-control" name="extension" id="extension" required
									accept=".bz2,.bzip2,.gz,.gzip,.tar,.tbz2,.tgz,.zip"
									aria-describedby="extension-upload" aria-label="<?php echo Text::_('COM_JEDCHECKER_UPLOAD_FILE'); ?>">
							<button class="btn btn-success" type="button" id="extension-upload">
								<span class="icon-upload "></span> <?php echo Text::_('JSUBMIT'); ?>
							</button>
							<div class="invalid-feedback"><?php echo Text::_('COM_JEDCHECKER_EMPTY_UPLOAD_FIELD'); ?></div>
						</div>
						<div id="jed_uploading_spinner" class="text-center text-info mt-3 hidden"><span class="spinner spinner-border"></span></div>
						<input type="hidden" name="task" value=""/>
						<?php echo HTMLHelper::_('form.token'); ?>
					</form>
				</div>
			</div>
		</div>

		<div class="col-6 col-md-4">
			<div class="card bg-info">
				<div class="card-header text-white bg-info" data-bs-toggle="collapse" data-bs-target="#jedchecker-contributors" role="button">
					<?php echo Text::_('COM_JEDCHECKER_WALL_OF_HONOR'); ?>
				</div>
				<div class="card-body show" id="jedchecker-contributors">
					<h5 class="card-title text-white"><?php echo Text::_('COM_JEDCHECKER_PEOPLE_THAT_HAVE_HELPED_WITH_THE_DEVELOPMENT'); ?></h5>
					<p class="card-text">
						<a href="https://github.com/joomla-extensions/jedchecker/graphs/contributors" target="_blank" class="btn btn-light">
						<?php echo Text::_('COM_JEDCHECKER_CONTRIBUTORS'); ?></a>
					</p>
				</div>
			</div>
		</div>

		<div class="col-12 col-md-3 jedchecker-results hidden">
			<div class="card">
				<div class="card-header"><?php echo Text::_('COM_JEDCHECKER_RESULTS'); ?></div>
					<div role="tablist" class="list-group list-group-flush">
							<?php
							foreach ($this->jsOptions['rules'] as $i => $rulename)
							{
								$class = 'jedcheckerRules' . ucfirst($rulename);
								$rule = new $class;
								?>
								<a role="tab" id="jed-<?php echo $rulename; ?>" data-bs-toggle="tab" href="#jedtab-<?php echo $rulename; ?>"
								   class="list-group-item list-group-item-action d-flex justify-content-between<?php echo $i === 0 ? ' active' : ''; ?>">
									<?php echo Text::_($rule->get('title')); ?>
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
					<?php echo Text::sprintf('COM_JEDCHECKER_LEAVE_A_REVIEW_JED', 'https://extensions.joomla.org/extensions/tools/development-tools/21336'); ?>
					<?php echo Text::sprintf('COM_JEDCHECKER_DEVELOPED_BY', 'https://github.com/joomla-extensions/jedchecker'); ?> :)
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
						<?php echo Text::_($rule->get('title')); ?>
					</div>
					<div class="card-body">
						<p class="card-text">
							<?php echo Text::_($rule->get('description')); ?>
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
