(() => {
	const jsonDataElement = document.getElementById('jed-rules-json');
	if (!jsonDataElement) {
		thrown new Error('Initialization data is missing');
	}
	let jedOptions;
	try {
		jedOptions = JSON.parse(jsonDataElement.innerHTML);
	} catch (e) {
		thrown new Error('Initialization data is missing');
	}

	if (!jedOptions) thrown new Error('Initialization data is missing');

	const add_validation = () => {
		// Loop over them and prevent submission
	  [...document.querySelectorAll('.needs-validation')].forEach((form) => {
			form.addEventListener('submit', (event) => {
	      const form = event.target;
				if (form.checkValidity() === false) {
					event.preventDefault();
					event.stopPropagation();
				}
				form.classList.add('was-validated');
			}, false);
	  });
	}

	function check(url, rule) {
	  fetch(`${url}index.php?option=com_jedchecker&task=police.check&format=raw&rule=${rule}`)
	    .then(response => response.text())
	    .then(data => {
	      const sidebar = document.querySelector(`#jed-${rule}`);
				const target = document.querySelector(`#police-check-result-${rule}`);

				target.innerHTML = data;

				const error = [...target.querySelectorAll('.alert-danger')].length;
				[...sidebar.querySelectorAll('.badge.bg-danger')].map(el => el.textContent = error || '');

				const warning = [...target.querySelectorAll('.alert-warning')].length;
	      [...sidebar.querySelectorAll('.badge.bg-warning')].map(el => el.textContent = warning || '');

				const compat = [...target.querySelectorAll('.alert-secondary')].length;
	      [...sidebar.querySelectorAll('.badge.bg-secondary')].map(el => el.textContent = compat || '');

				var info = [...target.querySelectorAll('.alert-info')].length;
	      [...sidebar.querySelectorAll('.badge.bg-info')].map(el => el.textContent = info || '');

				var success = [...target.querySelectorAll('.alert-success')].length;
	      [...sidebar.querySelectorAll('.badge.bg-info')].map(el => el.classList.toggle('hidden', !success));

	      [...sidebar.querySelectorAll('.badge.bg-border')].map(el => el.classList.add('hidden'));
	    })
	    .catch(error => {
	      console.log(error);
	      const sidebar = document.querySelector(`#jed-${rule}`);
				const target = document.querySelector(`#police-check-result-${rule}`);

				target.innerHTML = `<span class="text-danger"><b>${error.status}:</b> ${xhr.status} ${xhr.statusText}</span>`;

	      [...sidebar.querySelectorAll('.badge.bg-danger')].map(el => el.textContent = '?');
	      [...sidebar.querySelectorAll('.badge.bg-danger, .badge.bg-secondary, .badge.bg-info')].map(el => el.textContent = '');
	      [...sidebar.querySelectorAll('.badge.bg-success, .spinner-border')].map(el => el.addClass('hidden'));
	    });
	}

	let jed_collapse_init = false;
	Joomla.submitbutton = function (task) {
		if (task == 'check') {
			[...document.querySelectorAll(".jedchecker-results")].map((el) => el.removeClass("hidden"));
			[...document.querySelectorAll('.jedchecker-results .badge:not(.bg-success)')].map((el) => el.innerHTML = '');
			[...document.querySelectorAll('.jedchecker-results .badge.bg-success')].map((el) => el.addClass('hidden'));
			[...document.querySelectorAll('.jedchecker-results .spinner-border')].map((el) => el.removeClass('hidden'));
			[...document.querySelectorAll('.police-check-result')].map((el) => el.innerHTML = '<div class="text-center text-info"><span class="spinner-border"></span></div>');

			if (!jed_collapse_init) {
				[...document.querySelectorAll(".card-header[data-bs-toggle]")].forEach((el) => {
		el.addClass("accordion-button collapsed");
					el.setAttribute('href', el.dataset.href);
	      });

	      new bootstrap.Collapse(document.getElementById('jedchecker-welcome'));
	      new bootstrap.Collapse(document.getElementById('jedchecker-contributors'));

	      jed_collapse_init = true;
			}

	    jedOptions["rules"].forEach((rule) => {
	      check(jedOptions["url"], rule);
	    });
		} else {
			Joomla.submitform(task);
		}
	}

	new bootstrap.Tooltip(document.getElementById('jedchecker'), {container: 'body', selector: '[data-bs-toggle=tooltip]'});
})();
