/**
 * KURSO for Joomla – tools of the query editor: evaluate variables, open GraphiQL, load examples,
 * run the query and preview the template.
 *
 * @copyright  (C) 2026 KURSO <https://www.kurso.de>
 * @license    MIT; see LICENSE
 */

import { JoomlaEditor } from 'editor-api';

const options = Joomla.getOptions('com_kurso.queryEdit') || {};
const text = (key) => Joomla.Text._(key);

const FIELDS = {
  graphql: 'jform_graphql',
  variables: 'jform_variables',
  template: 'jform_template',
};

/**
 * Reads a field through the Joomla editor API (CodeMirror) or directly from the textarea.
 */
const getValue = (id) => {
  const editor = JoomlaEditor.get(id);

  if (editor && typeof editor.getValue === 'function') {
    return editor.getValue();
  }

  const element = document.getElementById(id);

  return element ? element.value : '';
};

const setValue = (id, value) => {
  const editor = JoomlaEditor.get(id);

  if (editor && typeof editor.setValue === 'function') {
    editor.setValue(value);
    return;
  }

  const element = document.getElementById(id);

  if (element) {
    element.value = value;
  }
};

/**
 * POSTs to one of the JSON endpoints (tools.evaluate, tools.run, tools.preview).
 */
const post = async (url, fields) => {
  const body = new FormData();

  Object.entries(fields).forEach(([key, value]) => body.append(key, value));
  body.append(options.token, '1');

  const response = await fetch(url, {
    method: 'POST',
    body,
    credentials: 'same-origin',
    headers: { Accept: 'application/json' },
  });

  let json;

  try {
    json = await response.json();
  } catch (error) {
    throw new Error(`${text('COM_KURSO_REQUEST_FAILED')} (HTTP ${response.status})`);
  }

  if (!json.success) {
    throw new Error(json.message || text('COM_KURSO_REQUEST_FAILED'));
  }

  return json.data;
};

const setBusy = (statusElement, busy, message = '') => {
  if (!statusElement) {
    return;
  }

  statusElement.textContent = message;
  statusElement.classList.toggle('is-busy', busy);
};

/* Variables: evaluate Twig expressions ----------------------------------------------------------- */

const evaluateButton = document.getElementById('kurso-evaluate');
const variablesResult = document.getElementById('kurso-variables-result');

const evaluateVariables = () => post(options.urls.evaluate, { variables: getValue(FIELDS.variables) })
  .then((data) => data.variables);

if (evaluateButton && variablesResult) {
  evaluateButton.addEventListener('click', async () => {
    evaluateButton.disabled = true;

    try {
      const variables = await evaluateVariables();

      variablesResult.classList.remove('kurso-result--error');
      variablesResult.textContent = `${text('COM_KURSO_VARIABLES_RESULT')}\n${JSON.stringify(variables, null, 2)}`;
    } catch (error) {
      variablesResult.classList.add('kurso-result--error');
      variablesResult.textContent = error.message;
    } finally {
      variablesResult.hidden = false;
      evaluateButton.disabled = false;
    }
  });
}

/* Open in GraphiQL --------------------------------------------------------------------------------- */

const graphiqlButton = document.getElementById('kurso-graphiql');

if (graphiqlButton) {
  graphiqlButton.addEventListener('click', async () => {
    if (!options.graphqlUrl) {
      Joomla.renderMessages({ warning: [text('COM_KURSO_GRAPHIQL_NO_URL')] });
      return;
    }

    const buildUrl = (variables) => {
      const url = new URL(options.graphqlUrl);

      url.searchParams.set('query', getValue(FIELDS.graphql));

      if (variables && Object.keys(variables).length) {
        url.searchParams.set('variables', JSON.stringify(variables, null, 2));
      }

      return url.toString();
    };

    const variablesSource = getValue(FIELDS.variables).trim();

    if (!variablesSource.includes('{{') && !variablesSource.includes('{%')) {
      let variables = {};

      try {
        variables = variablesSource ? JSON.parse(variablesSource) : {};
      } catch (error) {
        variables = {};
      }

      window.open(buildUrl(variables), '_blank', 'noopener');
      return;
    }

    // Open the tab synchronously (avoids popup blockers), navigate it once the variables are evaluated.
    const tab = window.open('about:blank', '_blank');

    graphiqlButton.disabled = true;

    try {
      const variables = await evaluateVariables();

      if (tab) {
        tab.opener = null;
        tab.location.href = buildUrl(variables);
      }
    } catch (error) {
      if (tab) {
        tab.opener = null;
        tab.location.href = buildUrl({});
      }
    } finally {
      graphiqlButton.disabled = false;
    }
  });
}

/* Examples -------------------------------------------------------------------------------------------- */

const exampleSelect = document.getElementById('kurso-example');
const exampleButton = document.getElementById('kurso-load-example');

if (exampleSelect && exampleButton) {
  exampleButton.addEventListener('click', () => {
    const example = (options.examples || {})[exampleSelect.value];

    if (!example || !window.confirm(text('COM_KURSO_EXAMPLE_CONFIRM'))) {
      return;
    }

    setValue(FIELDS.graphql, example.graphql);
    setValue(FIELDS.variables, example.variables);
    setValue(FIELDS.template, example.template);
  });
}

/* Preview ---------------------------------------------------------------------------------------------- */

const runButton = document.getElementById('kurso-run');
const cacheButton = document.getElementById('kurso-preview-cache');
const previewStatus = document.getElementById('kurso-preview-status');
const previewError = document.getElementById('kurso-preview-error');
const previewFrame = document.getElementById('kurso-preview-frame');
const previewData = document.getElementById('kurso-preview-data');

const escapeAttribute = (value) => String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');

/**
 * Shows the rendered HTML in a sandboxed iframe: no scripts, no access to the administrator page.
 */
const showRendered = (html) => {
  previewFrame.srcdoc = '<!doctype html><html><head><meta charset="utf-8">'
    + `<link rel="stylesheet" href="${escapeAttribute(options.cssUrl)}">`
    + '<style>body{font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;margin:1rem;color:#1f2937}</style>'
    + `</head><body><div class="kurso">${html}</div></body></html>`;
};

const showPreview = (data) => {
  previewError.hidden = true;

  if (data.renderError) {
    previewError.textContent = `${text('COM_KURSO_PREVIEW_RENDER_ERROR')} ${data.renderError}`;
    previewError.hidden = false;
  }

  showRendered(data.html || '');
  previewData.textContent = JSON.stringify(data.data, null, 2);
};

const runPreview = async (button, url, fields) => {
  button.disabled = true;
  previewError.hidden = true;
  setBusy(previewStatus, true, text('COM_KURSO_PREVIEW_LOADING'));

  try {
    const data = await post(url, fields);

    showPreview(data);
    setBusy(previewStatus, false, data.source || '');
  } catch (error) {
    previewError.textContent = error.message;
    previewError.hidden = false;
    setBusy(previewStatus, false, '');
  } finally {
    button.disabled = false;
  }
};

if (runButton && previewFrame) {
  runButton.addEventListener('click', () => runPreview(runButton, options.urls.run, {
    graphql: getValue(FIELDS.graphql),
    variables: getValue(FIELDS.variables),
    template: getValue(FIELDS.template),
  }));
}

if (cacheButton && previewFrame) {
  cacheButton.addEventListener('click', () => runPreview(cacheButton, options.urls.preview, {
    id: String(options.id || 0),
    template: getValue(FIELDS.template),
  }));
}
