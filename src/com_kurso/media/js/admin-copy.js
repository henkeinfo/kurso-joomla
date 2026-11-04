/**
 * KURSO for Joomla – copy embed tags to the clipboard.
 *
 * @copyright  (C) 2026 KURSO <https://www.kurso.de>
 * @license    MIT; see LICENSE
 */

const copyText = async (text) => {
  if (navigator.clipboard && window.isSecureContext) {
    await navigator.clipboard.writeText(text);
    return;
  }

  // Fallback for administrators served over plain HTTP.
  const textarea = document.createElement('textarea');
  textarea.value = text;
  textarea.setAttribute('readonly', '');
  textarea.style.position = 'fixed';
  textarea.style.left = '-9999px';
  document.body.appendChild(textarea);
  textarea.select();

  try {
    if (!document.execCommand('copy')) {
      throw new Error('copy failed');
    }
  } finally {
    document.body.removeChild(textarea);
  }
};

document.addEventListener('click', async (event) => {
  const button = event.target.closest('[data-kurso-copy]');

  if (!button) {
    return;
  }

  event.preventDefault();

  const label = button.querySelector('.kurso-copy__label');
  const original = label ? label.textContent : '';

  try {
    await copyText(button.getAttribute('data-kurso-copy'));
  } catch (error) {
    // Select the tag next to the button so it can be copied manually.
    const code = button.parentElement && button.parentElement.querySelector('code');

    if (code) {
      const range = document.createRange();
      range.selectNodeContents(code);
      window.getSelection().removeAllRanges();
      window.getSelection().addRange(range);
    }

    return;
  }

  button.classList.add('is-copied');

  if (label) {
    label.textContent = Joomla.Text._('COM_KURSO_COPIED');
  }

  setTimeout(() => {
    button.classList.remove('is-copied');

    if (label) {
      label.textContent = original;
    }
  }, 1500);
});
