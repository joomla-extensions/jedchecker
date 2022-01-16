(() => {
  'use strict';

  let jedOptions;
  try {
    jedOptions = JSON.parse(document.getElementById('jed-rules-json').innerHTML);
  } catch (e) {
    throw new Error('Initialization data is missing');
  }

  if (!jedOptions) throw new Error('Initialization data is missing');

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
      .then(response => {
        if (!response.ok) {
          throw new Error(`${response.status} ${response.statusCode}`);
        }
        return response.text();
      })
      .then(data => {
        const sidebar = document.getElementById(`jed-${rule}`);
        const target = document.getElementById(`police-check-result-${rule}`);

        target.innerHTML = data;

        const error = [...target.querySelectorAll('.alert-danger')].length;
        [...sidebar.querySelectorAll('.badge.bg-danger')].forEach(el => el.textContent = error || '');

        const warning = [...target.querySelectorAll('.alert-warning')].length;
        [...sidebar.querySelectorAll('.badge.bg-warning')].forEach(el => el.textContent = warning || '');

        const compat = [...target.querySelectorAll('.alert-secondary')].length;
        [...sidebar.querySelectorAll('.badge.bg-secondary')].forEach(el => el.textContent = compat || '');

        const info = [...target.querySelectorAll('.alert-info')].length;
        [...sidebar.querySelectorAll('.badge.bg-info')].forEach(el => el.textContent = info || '');

        const success = [...target.querySelectorAll('.alert-success')].length;
        [...sidebar.querySelectorAll('.badge.bg-info')].forEach(el => el.classList.toggle('hidden', !success));

        [...sidebar.querySelectorAll('.spinner-border')].forEach(el => el.classList.add('hidden'));
      })
      .catch(error => {
        console.error(error);
        const sidebar = document.getElementById(`jed-${rule}`);
        const target = document.getElementById(`police-check-result-${rule}`);

        target.innerHTML = `<span class="text-danger"><b>${error.name}:</b> ${error.message}</span>`;

        [...sidebar.querySelectorAll('.badge.bg-danger')].forEach(el => el.textContent = '?');
        [...sidebar.querySelectorAll('.badge.bg-warning, .badge.bg-secondary, .badge.bg-info')].map(el => el.textContent = '');
        [...sidebar.querySelectorAll('.badge.bg-success, .spinner-border')].map(el => el.classList.add('hidden'));
      });
  }

  let jed_collapse_init = false;
  window.Joomla.submitbutton = function (task) {
    if (task === 'check') {
      [...document.querySelectorAll('.jedchecker-results')].forEach(el => el.classList.remove('hidden'));
      [...document.querySelectorAll('.jedchecker-results .badge:not(.bg-success)')].map((el) => el.innerHTML = '');
      [...document.querySelectorAll('.jedchecker-results .badge.bg-success')].map((el) => el.classList.add('hidden'));
      [...document.querySelectorAll('.jedchecker-results .spinner-border')].map((el) => el.classList.remove('hidden'));
      [...document.querySelectorAll('.police-check-result')].forEach(el => el.innerHTML = '<div class='text-center text-info'><span class='spinner-border'></span></div>');

      if (!jed_collapse_init) {
        [...document.querySelectorAll('.card-header[data-bs-toggle]')].forEach(el => {
               el.classList.add('accordion-button');
               el.classList.add('collapsed');
          el.setAttribute('href', el.dataset.href);
        });

        new bootstrap.Collapse(document.getElementById('jedchecker-welcome'));
        new bootstrap.Collapse(document.getElementById('jedchecker-contributors'));

        jed_collapse_init = true;
      }

      jedOptions["rules"].forEach(rule => check(jedOptions["url"], rule));
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
