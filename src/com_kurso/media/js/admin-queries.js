/**
 * KURSO for Joomla – row buttons in the query list ("Fetch now", "Delete").
 *
 * The buttons select their row and submit the list form with the task, like Joomla's own list buttons,
 * so the request carries the form token and goes through the controller's permission checks.
 *
 * @copyright  (C) 2026 KURSO <https://www.kurso.de>
 * @license    MIT; see LICENSE
 */

document.addEventListener('click', (event) => {
  const button = event.target.closest('[data-kurso-row-task]');

  if (!button) {
    return;
  }

  event.preventDefault();

  const message = button.getAttribute('data-kurso-confirm');

  if (message && !window.confirm(message)) {
    return;
  }

  // Stay visible while the page reloads (a disabled button is nearly invisible in the dark theme).
  if (button.classList.contains('is-busy')) {
    return;
  }

  button.classList.add('is-busy');
  button.setAttribute('aria-disabled', 'true');

  if (button.getAttribute('data-kurso-row-task') === 'queries.refresh') {
    button.lastChild.textContent = ` ${Joomla.Text._('COM_KURSO_FETCHING')}`;
  }

  Joomla.listItemTask(button.getAttribute('data-kurso-row'), button.getAttribute('data-kurso-row-task'));
});
