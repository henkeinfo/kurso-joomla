/**
 * KURSO for Joomla – "Test connection" button. Tests the values currently entered in the form,
 * without saving them. An empty password field means "use the saved password".
 *
 * @copyright  (C) 2026 KURSO <https://www.kurso.de>
 * @license    MIT; see LICENSE
 */

const options = Joomla.getOptions('com_kurso.connection') || {};
const button = document.getElementById('kurso-test-connection');
const status = document.getElementById('kurso-test-status');
const result = document.getElementById('kurso-test-result');
const form = document.getElementById('connection-form');

const showResult = (success, message) => {
  result.hidden = false;
  result.classList.remove('alert-success', 'alert-danger');
  result.classList.add(success ? 'alert-success' : 'alert-danger');
  result.textContent = message;
};

if (button && form) {
  button.addEventListener('click', async () => {
    const body = new FormData(form);

    // The "task" form field would override the task in the URL.
    body.delete('task');

    button.disabled = true;
    result.hidden = true;
    status.textContent = Joomla.Text._('COM_KURSO_CONNECTION_TESTING');
    status.classList.add('is-busy');

    try {
      const response = await fetch(options.testUrl, {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
      });
      const json = await response.json();

      showResult(Boolean(json.success), json.message || '');
    } catch (error) {
      showResult(false, Joomla.Text._('COM_KURSO_REQUEST_FAILED'));
    } finally {
      button.disabled = false;
      status.textContent = '';
      status.classList.remove('is-busy');
    }
  });
}
