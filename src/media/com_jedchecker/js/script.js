(() => {
  'use strict';

  // Set by the view with addScriptOptions('com_jedchecker', ...).
  const jedOptions = window.Joomla && Joomla.getOptions ? Joomla.getOptions('com_jedchecker') : null;

  if (!jedOptions || !Array.isArray(jedOptions.rules)) {
    throw new Error('Initialization data is missing');
  }

  const add_validation = () => {
    // Loop over them and prevent submission
    [...document.querySelectorAll('.needs-validation')].forEach(form => {
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
    const csrfToken = encodeURIComponent(jedOptions['csrfToken']);
    fetch(`${url}index.php?option=com_jedchecker&task=uploads.check&format=raw&rule=${encodeURIComponent(rule)}&${csrfToken}=1`, { credentials: 'same-origin' })
      .then(response => {
        if (!response.ok) {
          throw new Error(`${response.status} ${response.statusText}`);
        }
        return response.text();
      })
      .then(data => {
        const sidebar = document.getElementById(`jed-${rule}`);
        const target = document.getElementById(`police-check-result-${rule}`);

        target.innerHTML = data;

        const error = target.querySelectorAll('.alert-danger').length;
        [...sidebar.querySelectorAll('.badge.bg-danger')].forEach(el => el.textContent = error || '');

        const warning = target.querySelectorAll('.alert-warning').length;
        [...sidebar.querySelectorAll('.badge.bg-warning')].forEach(el => el.textContent = warning || '');

        const compat = target.querySelectorAll('.alert-secondary').length;
        [...sidebar.querySelectorAll('.badge.bg-secondary')].forEach(el => el.textContent = compat || '');

        const info = target.querySelectorAll('.alert-info').length;
        [...sidebar.querySelectorAll('.badge.bg-info')].forEach(el => el.textContent = info || '');

        const success = target.querySelectorAll('.alert-success').length;
        [...sidebar.querySelectorAll('.badge.bg-success')].forEach(el => el.classList.toggle('hidden', !success));

        [...sidebar.querySelectorAll('.spinner-border')].forEach(el => el.classList.add('hidden'));
      })
      .catch(error => {
        console.error(error);
        const sidebar = document.getElementById(`jed-${rule}`);
        const target = document.getElementById(`police-check-result-${rule}`);

        // Text, not HTML: the message may echo parts of the server's answer.
        const message = document.createElement('span');
        message.className = 'text-danger';
        message.textContent = `${error.name}: ${error.message}`;
        target.replaceChildren(message);

        [...sidebar.querySelectorAll('.badge.bg-danger')].forEach(el => el.textContent = '?');
        [...sidebar.querySelectorAll('.badge.bg-warning, .badge.bg-secondary, .badge.bg-info')].forEach(el => el.textContent = '');
        [...sidebar.querySelectorAll('.badge.bg-success, .spinner-border')].forEach(el => el.classList.add('hidden'));
      });
  }

  let jed_collapse_init = false;
  window.Joomla.submitbutton = function (task) {
    if (task === 'check') {
      [...document.querySelectorAll('.jedchecker-results')].forEach(el => el.classList.remove('hidden'));
      [...document.querySelectorAll('.jedchecker-results .badge:not(.bg-success)')].forEach(el => el.innerHTML = '');
      [...document.querySelectorAll('.jedchecker-results .badge.bg-success')].forEach(el => el.classList.add('hidden'));
      [...document.querySelectorAll('.jedchecker-results .spinner-border')].forEach(el => el.classList.remove('hidden'));
      [...document.querySelectorAll('.police-check-result')].forEach(el => el.innerHTML = '<div class="text-center text-info"><span class="spinner-border"></span></div>');

      if (!jed_collapse_init) {
        [...document.querySelectorAll('.card-header[data-bs-toggle]')].forEach(el => {
          el.classList.add('accordion');
          el.classList.add('accordion-button');
          el.classList.add('collapsed');
          el.setAttribute('href', el.dataset.href);
        });

        new bootstrap.Collapse(document.getElementById('jedchecker-welcome'));
        new bootstrap.Collapse(document.getElementById('jedchecker-contributors'));

        jed_collapse_init = true;
      }

      jedOptions['rules'].forEach(rule => check(jedOptions['url'], rule));
    } else {
      Joomla.submitform(task);
    }
  }

  document.getElementById('extension-upload').addEventListener('click', () => {
    add_validation();
    document.getElementById('jed_uploading_spinner').classList.remove('hidden');
    Joomla.submitbutton('uploads.upload')
  });

  new bootstrap.Tooltip(document.getElementById('jedchecker'), {container: 'body', selector: '[data-bs-toggle=tooltip]'});

})();
