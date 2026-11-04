/**
 * KURSO for Joomla – GraphQL syntax mode for the CodeMirror 6 editor bundled with Joomla.
 * Loaded through the import map as "@codemirror/lang-kursographql" (field attribute syntax="kursographql").
 *
 * @copyright  (C) 2026 KURSO <https://www.kurso.de>
 * @license    MIT; see LICENSE
 */

import { LanguageSupport, StreamLanguage } from '@codemirror/language';

const KEYWORDS = new Set([
  'query', 'mutation', 'subscription', 'fragment', 'on', 'type', 'interface', 'union', 'enum', 'input',
  'extend', 'schema', 'directive', 'scalar', 'implements', 'repeatable',
]);

const ATOMS = new Set(['true', 'false', 'null']);

const parser = {
  name: 'graphql',

  startState: () => ({ inBlockString: false }),

  token(stream, state) {
    if (state.inBlockString) {
      if (stream.skipTo('"""')) {
        stream.match('"""');
        state.inBlockString = false;
      } else {
        stream.skipToEnd();
      }

      return 'string';
    }

    if (stream.eatSpace()) {
      return null;
    }

    if (stream.peek() === '#') {
      stream.skipToEnd();
      return 'comment';
    }

    if (stream.match('"""')) {
      state.inBlockString = true;
      return 'string';
    }

    if (stream.peek() === '"') {
      stream.next();
      let escaped = false;

      while (!stream.eol()) {
        const ch = stream.next();

        if (ch === '"' && !escaped) {
          break;
        }

        escaped = !escaped && ch === '\\';
      }

      return 'string';
    }

    if (stream.match(/^-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?/)) {
      return 'number';
    }

    if (stream.match(/^\$[_A-Za-z][_0-9A-Za-z]*/)) {
      return 'variableName.definition';
    }

    if (stream.match(/^@[_A-Za-z][_0-9A-Za-z]*/)) {
      return 'meta';
    }

    if (stream.match('...')) {
      return 'operator';
    }

    if (stream.match(/^[_A-Za-z][_0-9A-Za-z]*/)) {
      const word = stream.current();

      if (KEYWORDS.has(word)) {
        return 'keyword';
      }

      if (ATOMS.has(word)) {
        return 'atom';
      }

      return /^[A-Z]/.test(word) ? 'typeName' : 'propertyName';
    }

    const ch = stream.next();

    if ('{}()[]'.includes(ch)) {
      return 'bracket';
    }

    return ':!=|&'.includes(ch) ? 'operator' : null;
  },

  languageData: {
    commentTokens: { line: '#' },
    closeBrackets: { brackets: ['(', '[', '{', '"'] },
  },
};

export const graphqlLanguage = StreamLanguage.define(parser);

export function kursographql() {
  return new LanguageSupport(graphqlLanguage);
}
