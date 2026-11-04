/**
 * KURSO for Joomla – filter in the "insert KURSO query" dialog of the editor button.
 * Inserting itself is handled by Joomla's modal-content-select script (data-content-select attributes).
 *
 * @copyright  (C) 2026 KURSO <https://www.kurso.de>
 * @license    MIT; see LICENSE
 */

const filter = document.getElementById('kurso-select-filter');

if (filter) {
  filter.addEventListener('input', () => {
    const term = filter.value.trim().toLowerCase();

    document.querySelectorAll('#kurso-select-list tbody tr').forEach((row) => {
      row.hidden = term !== '' && !(row.getAttribute('data-kurso-search') || '').includes(term);
    });
  });

  filter.focus();
}
