# Changelog

All notable changes to this project are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
versioning follows [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.0.0] – 2026-11-04

First release, with the features of KURSO for WordPress 1.0.0, adapted to Joomla.

### Added
- Package `pkg_kurso` for Joomla 5.4+ and 6.x (PHP 8.1+, MySQL/MariaDB and PostgreSQL) with:
  - Component **KURSO** with the views **Queries** and **Connection** and Joomla's ACL, check-in/out, search, filters and sorting
  - Content plugin: tag `{kurso query="alias"}` with optional `class="…"` in articles and Custom modules
  - Editor button **KURSO** that inserts the tag (administrator and frontend editing)
  - Module **KURSO** with optional template override
  - Menu item type **KURSO » Query**
  - Scheduled task **KURSO – Refresh course data** (created on installation, every 5 minutes)
- Query editor with syntax highlighting for GraphQL and Twig (CodeMirror modes for Joomla's editor), **Evaluate** for the variables, **Open in GraphiQL**, four ready-made examples, a Twig quick reference and a **Preview** tab that runs the unsaved query and shows the rendered template next to the raw data
- **Test Connection** checks the values entered in the form without saving them
- Query list shows the embed tag with a copy button, the last fetch and the last error, and has **Edit**, **Fetch now** and **Delete** buttons in every row (as in WordPress)
- Query, variables and template are edited on one page; new or changed queries are fetched right after saving
- Twig filter `jdate` for dates with month names in the site language
- Twig variables in GraphQL variables (e.g. `{{ date('-2 weeks')|date('Y-m-d') }}`) and template syntax checked on save
- English and German translations

### Changed (compared with KURSO for WordPress)
- Data is refreshed by Joomla's Task Scheduler instead of WP-Cron; when the scheduler does not run, outdated data is refreshed during the page view (option)
- Cached results are kept in a database table: a failed fetch never removes the data shown on the site, and the last error is visible in the administrator
- Concurrent fetches of the same query are prevented by an atomic claim

### Security
- Twig templates run in the Twig sandbox with an allow-list (no `include`, `source`, `constant()`, `extends`, method calls or PHP callables), so editing templates does not allow code execution
- Rendered HTML is filtered (scripts, event handlers, `javascript:` URLs) unless disabled in the options; the administrator preview renders in a sandboxed iframe
- The password is encrypted with libsodium (key derived from the site secret) and never sent to the browser
- HTTPS is required for the API; redirects are not followed, so credentials cannot leak to another host
- Error messages are only shown to users who may manage KURSO
- New permission **Edit Connection** for the credentials

[Unreleased]: https://github.com/henkeinfo/kurso-joomla/compare/v1.0.0...HEAD
[1.0.0]: https://github.com/henkeinfo/kurso-joomla/releases/tag/v1.0.0
