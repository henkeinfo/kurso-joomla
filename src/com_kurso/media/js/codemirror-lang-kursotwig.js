/**
 * KURSO for Joomla – HTML + Twig syntax mode for the CodeMirror 6 editor bundled with Joomla.
 * Loaded through the import map as "@codemirror/lang-kursotwig" (field attribute syntax="kursotwig").
 *
 * HTML is highlighted by Joomla's HTML mode; Twig tags ({{ … }}, {% … %}, {# … #}) are marked on top.
 *
 * @copyright  (C) 2026 KURSO <https://www.kurso.de>
 * @license    MIT; see LICENSE
 */

import { html } from '@codemirror/lang-html';
import {
  Decoration, EditorView, MatchDecorator, ViewPlugin,
} from '@codemirror/view';

const twigMark = Decoration.mark({ class: 'cm-kurso-twig' });
const twigCommentMark = Decoration.mark({ class: 'cm-kurso-twig-comment' });

const matcher = new MatchDecorator({
  regexp: /\{\{.*?\}\}|\{%.*?%\}|\{#.*?#\}/g,
  decoration: (match) => (match[0].startsWith('{#') ? twigCommentMark : twigMark),
});

const twigHighlighter = ViewPlugin.fromClass(class {
  constructor(view) {
    this.decorations = matcher.createDeco(view);
  }

  update(update) {
    this.decorations = matcher.updateDeco(update, this.decorations);
  }
}, {
  decorations: (plugin) => plugin.decorations,
});

const twigTheme = EditorView.baseTheme({
  '.cm-kurso-twig': { color: '#7c3aed', backgroundColor: 'rgba(124, 58, 237, .08)', borderRadius: '2px' },
  '.cm-kurso-twig *': { color: 'inherit' },
  '.cm-kurso-twig-comment, .cm-kurso-twig-comment *': { color: '#6b7280', fontStyle: 'italic' },
  '&dark .cm-kurso-twig': { color: '#c4b5fd', backgroundColor: 'rgba(196, 181, 253, .12)' },
});

export function kursotwig(options = {}) {
  return [html({ selfClosingTags: true, ...options }), twigHighlighter, twigTheme];
}
