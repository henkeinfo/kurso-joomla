# KURSO for Joomla — CLAUDE.md

## Project

Joomla extension package that displays course data from the KURSO course management system (https://www.kurso.de) via GraphQL. Joomla counterpart of the WordPress plugin `../kurso-wordpress` (https://github.com/henkeinfo/kurso-wordpress); keep both functionally in line.

- **Package:** `pkg_kurso` = `com_kurso` + `mod_kurso` + `plg_content_kurso` + `plg_editors-xtd_kurso` + `plg_task_kurso`
- **Namespaces:** `Kurso\Component\Kurso`, `Kurso\Module\Kurso`, `Kurso\Plugin\{Content,EditorsXtd,Task}\Kurso`
- **Specification:** `specs/specification.md` (German)
- **Targets:** Joomla 5.4+ and 6.x, PHP 8.1+, MySQL/MariaDB and PostgreSQL

## Workflow Rules

- When an issue or feature is completed: **update `specs/specification.md` and `CHANGELOG.md`** (`## [Unreleased]`).
- No commits without a matching changelog entry.
- Every UI string goes into the language files, **both `en-GB` and `de-DE`** (keys must match).
- Test every change on Joomla 5.4 and Joomla 6 before committing.
- Never commit credentials or local test setup files.
- Use only Joomla APIs available in 5.4 and 6.x; avoid anything deprecated (e.g. `getDbo()`, `Factory::getUser()`, `Joomla.editors.instances`, passing a dispatcher to plugin constructors).

## Architecture

- `com_kurso/administrator/src/Service/` holds all logic; `KursoComponent` creates the services lazily. Module and plugins use them via `Factory::getApplication()->bootComponent('com_kurso')->getRenderer()` etc.
- **Connection settings** live in `#__kurso_settings`, not in component params: the Joomla Options dialog rewrites all params on save. The password is encrypted with sodium secretbox (key derived from `$secret` in configuration.php) and never sent to the browser.
- **Cache:** `#__kurso_cache` (one row per query: data, fetched_at, attempted_at, error). A failed fetch never overwrites data. `QueryRepository::claim()` is an atomic UPDATE that prevents concurrent fetches of the same query.
- **Refresh:** scheduled task `kurso.refresh` (created by the package installer, every 5 min) fetches due queries; on page view, missing data is fetched synchronously and data older than 2× the interval is refreshed (option `page_view_refresh`).
- **Twig:** loaded by a PSR-4 autoloader in `TwigFactory::ensureLoaded()` (no Composer autoloader at runtime). Always sandboxed (strict allow-list). Without the sandbox Twig allows PHP execution (`map('system')`), so never remove it.
- **Output:** rendered HTML goes through `InputFilter` (blocklist mode) unless `filter_output` is off. The admin preview renders inside a sandboxed iframe.
- **Editor button dialog:** `view=select` (admin), proxied on the site by the site `DisplayController`; access check in both dispatchers.
- **CodeMirror modes:** `media/js/codemirror-lang-kursographql.js` and `-kursotwig.js` are registered as import-map entries `@codemirror/lang-<mode>`; the editor field uses `syntax="kursographql"` / `syntax="kursotwig"`.

## Release Process

1. Bump the version in all manifests (`src/pkg_kurso/pkg_kurso.xml`, `src/com_kurso/kurso.xml`, `src/mod_kurso/mod_kurso.xml`, `src/plg_*/kurso.xml`) and `KursoComponent::VERSION`. `bin/build.sh` refuses to build if they differ.
2. If the database schema changes, add `sql/updates/{mysql,postgresql}/<version>.sql`.
3. Move `## [Unreleased]` in `CHANGELOG.md` to `## [x.y.z] – YYYY-MM-DD`.
4. Add an `<update>` block for the version to `updates/pkg_kurso.xml` (Joomla update server).
5. Commit `Release x.y.z`, then `git tag vx.y.z && git push origin vx.y.z`.
6. **The GitHub Release is created by GitHub Actions** — do not run `gh release create` manually.

## Demo API for Testing

```
URL:      https://demo.kurso.de/api/graphql
User:     demo
Password: demodemo
```

The demo API accepts any credentials, so wrong-password handling cannot be tested against it.
