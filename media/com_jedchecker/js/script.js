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

function check(url, rule) {
	jQuery.ajax({
		url: url + 'index.php?option=com_jedchecker&task=police.check&format=raw&rule='+rule,
		method: 'GET',
		success: function(result){
			var sidebar = jQuery('#jed-' + rule);
			var target = jQuery('#police-check-result-' + rule);

			target.html(result);

			var error = target.find('.alert-danger').length;
			sidebar.find('.badge.bg-danger').text(error || '');

			var warning = target.find('.alert-warning').length;
			sidebar.find('.badge.bg-warning').text(warning || '');

			var compat = target.find('.alert-secondary').length;
			sidebar.find('.badge.bg-secondary').text(compat || '');

			var info = target.find('.alert-info').length;
			sidebar.find('.badge.bg-info').text(info || '');

			var success = target.find('.alert-success').length;
			sidebar.find('.badge.bg-success').toggleClass('hidden', !success);

			sidebar.find('.spinner-border').addClass('hidden');
		},
		error: function(xhr, status){
			var sidebar = jQuery('#jed-' + rule);
			var target = jQuery('#police-check-result-' + rule);

			target.html('<span class="text-danger"><b>' + status + ':</b> ' + xhr.status + ' ' + xhr.statusText + '</span>');

			sidebar.find('.badge.bg-danger').text('?');
			sidebar.find('.badge.bg-warning,.badge.bg-secondary,.badge.bg-info').text('');
			sidebar.find('.badge.bg-success').addClass('hidden');

			sidebar.find('.spinner-border').addClass('hidden');
		}
	});
}

var jed_collapse_init = false;
Joomla.submitbutton = function (task) {
	if (task == 'check') {
		jQuery(".jedchecker-results").removeClass("hidden");
		jQuery('.jedchecker-results .badge:not(.bg-success)').html('');
		jQuery('.jedchecker-results .badge.bg-success').addClass('hidden');
		jQuery('.jedchecker-results .spinner-border').removeClass('hidden');
		jQuery('.police-check-result').html('<div class="text-center text-info"><span class="spinner-border"></span></div>');

		if (!jed_collapse_init) {
			jQuery(".card-header[data-bs-toggle]")
				.addClass("accordion-button collapsed")
				.each(function () {
					var $this = jQuery(this);
					$this.attr('href', $this.attr('data-href'));
				});

			new bootstrap.Collapse(document.getElementById('jedchecker-welcome'));
			new bootstrap.Collapse(document.getElementById('jedchecker-contributors'));

			jed_collapse_init = true;
		}

		for (index = 0; index < jed_options["rules"].length; ++index) {
			check(jed_options["url"], jed_options["rules"][index]);
		}

	} else {
		Joomla.submitform(task);
	}
}

jQuery(document).ready(function() {
	new bootstrap.Tooltip(document.getElementById('jedchecker'), {container: 'body', selector: '[data-bs-toggle=tooltip]'});
});