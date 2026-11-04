# KURSO for Joomla

Joomla extension for showing course data from the [KURSO course management system](https://www.kurso.de) via its GraphQL API. It is the Joomla counterpart of [KURSO for WordPress](https://github.com/henkeinfo/kurso-wordpress) and works the same way: you write a GraphQL query and a Twig template, and the extension fetches the data in the background and renders it on your pages.

## Features

- **GraphQL integration**: connects Joomla to any KURSO instance at `https://<systemname>.kurso.de/api/graphql`.
- **Flexible queries**: any number of GraphQL queries, with variables that may contain Twig expressions such as `{{ date()|date('Y-m-d') }}`.
- **Twig templates**: loops, conditions and arithmetic (e.g. free places), running in a secure sandbox.
- **Automatic refresh**: a Joomla scheduled task fetches the data at each query's interval. If the API fails, the last data stays online.
- **Four ways to show data**:
  - the tag `{kurso query="alias"}` in articles and Custom modules,
  - the editor button **KURSO**, which inserts that tag,
  - the module **KURSO** (with an optional template override for sidebars),
  - the menu item type **KURSO » Query**.
- **Easy administration**: query, variables and template on one page; **Edit**, **Fetch now** and **Delete** buttons in every row of the query list.
- **Editor with live preview**: syntax highlighting for GraphQL and Twig, ready-made examples, a "Run Query" button with rendered preview and raw data, and "Open in GraphiQL".
- **Security**: encrypted password, Twig sandbox, HTML output filter, Joomla ACL and CSRF protection on every action.
- **Languages**: English and German.

## Requirements

- Joomla 5.4 or newer, or Joomla 6.x
- PHP 8.1 or newer
- MySQL/MariaDB or PostgreSQL

## Installation

1. Download `pkg_kurso-x.y.z.zip` from [Releases](../../releases).
2. In Joomla: **System » Install » Extensions**, upload the file.
3. Go to **Components » KURSO » Connection**, enter the GraphQL URL, username and password, then click **Test Connection** and **Save**.
4. Under **Components » KURSO » Queries**, click **New**. A new query starts with a working example. Click **Run Query** in the **Preview** tab to see the result, then save.
5. Embed the query with the tag shown in the query list, for example `{kurso query="current-courses"}`.

The installation enables the plugins and creates the scheduled task **KURSO – Refresh course data** (every 5 minutes). Joomla runs scheduled tasks while the site is visited (the "lazy scheduler"). For exact intervals, run `php cli/joomla.php scheduler:run` from a cron job or use the scheduler's web cron.

## Configuration

### Connection (Components » KURSO » Connection)

| Setting | Description |
|---|---|
| GraphQL URL | `https://<systemname>.kurso.de/api/graphql` (HTTPS is required) |
| Username | KURSO username |
| Password | KURSO password. It is stored encrypted with the site's secret key and is never shown again. Leave the field empty to keep the saved password. |

**Test Connection** checks the values in the form without saving them.

To try the extension, use the demo system: URL `https://demo.kurso.de/api/graphql`, username `demo`, password `demodemo`.

### Queries

| Field | Description |
|---|---|
| Title / Alias | The alias identifies the query in the tag `{kurso query="alias"}`. |
| GraphQL Query | Standard GraphQL. |
| Variables (JSON) | GraphQL variables. Twig expressions are evaluated at each fetch, e.g. `{"from": "{{ date('-2 weeks')\|date('Y-m-d') }}"}`. |
| Twig Template | HTML with Twig. The template receives the `data` object of the GraphQL response. |
| Refresh Interval | How often the data is fetched (minutes). |

### Options (Components » KURSO » Options)

| Option | Default | Description |
|---|---|---|
| Load Stylesheet | Yes | Basic styles for tables, cards and buttons. |
| Filter HTML Output | Yes | Removes scripts, event handlers and `javascript:` links from the output. |
| Show Errors To | Users who can manage KURSO | Visitors never see error messages. |
| Refresh Outdated Data on Page View | Yes | Fallback if the scheduler does not run: data older than twice its interval is refreshed during the page view. |
| API Timeout | 15 s | |
| Permissions | | Joomla ACL. The action **Edit Connection** controls access to the credentials (only Super Users by default). |

## Usage

### Tag in articles and modules

```
{kurso query="current-courses"}
{kurso query="current-courses" class="my-css-class"}
```

The tag works wherever Joomla runs content plugins: articles, categories, and Custom modules with **Prepare Content** enabled. The **KURSO** editor button inserts it for you.

### Module

**Content » Site Modules » New » KURSO**: select the query and a position. The optional **Template Override** replaces the query's template for this module, for example a compact list for a sidebar.

### Menu item

**Menus » Add Menu Item » Menu Item Type: KURSO » Query** shows a query on its own page.

## Example

Query:

```graphql
query Courses($startDateMin: Date!) {
  allCourses(filter: { startDate_gte: $startDateMin }, orderBy: startDate_ASC, first: 20) {
    name
    startDate
    location { town }
    maxPart
    _bookingsMeta(filter: { workflowState_not_in: ["CANCELLED"] }) { count }
    onlineEnrollmentUrl
  }
}
```

Variables:

```json
{ "startDateMin": "{{ date()|date('Y-m-d') }}" }
```

Template:

```twig
<table class="kurso-table">
  {% for course in allCourses %}
  {% set free = course.maxPart - course._bookingsMeta.count %}
  <tr>
    <td>{{ course.name }}</td>
    <td>{{ course.startDate|date("d.m.Y") }}</td>
    <td>{{ course.location.town }}</td>
    <td>{{ free > 0 ? free : "Fully booked" }}</td>
    <td>{% if free > 0 and course.onlineEnrollmentUrl %}<a href="{{ course.onlineEnrollmentUrl }}" class="kurso-button">Book now</a>{% endif %}</td>
  </tr>
  {% endfor %}
</table>
```

More examples (cards, dates by course type, single course) are available in the query editor under **Template » Examples**.

## Twig quick reference

| Expression | Meaning |
|---|---|
| `{{ variable }}` | Output a value (HTML-escaped) |
| `{{ object.field }}` | Nested value |
| `{% for item in list %}…{% endfor %}` | Loop |
| `{% if condition %}…{% else %}…{% endif %}` | Condition |
| `{% set free = a - b %}` | Calculation |
| `{{ value\|date("d.m.Y") }}` | Date format |
| `{{ value\|jdate("d. F Y") }}` | Date with month names in the site language (Joomla) |
| `{{ value\|default("–") }}` | Fallback |

Templates run in the Twig sandbox: all common tags, filters and functions are available, but `include`, `source`, `constant()`, `extends`, method calls on objects and PHP callables in `map`/`filter`/`sort` are blocked.

## Building

```bash
bin/build.sh   # creates dist/pkg_kurso-<version>.zip
```

The script installs Twig with Composer (a local `composer` or the `composer:2` Docker image) and packs the component, module and plugins into one installable package. Releases are built the same way by GitHub Actions when a version tag is pushed.

## Project structure

```
kurso-joomla/
├── src/
│   ├── com_kurso/                 # Component
│   │   ├── administrator/
│   │   │   ├── src/Service/       # Core logic: GraphQL client, Twig sandbox, cache, refresh, renderer, encryption
│   │   │   ├── src/{Controller,Model,View,Table,Field,Dispatcher,Extension}/
│   │   │   ├── tmpl/ forms/ sql/ language/
│   │   │   └── vendor/            # Twig (installed by Composer, not in Git)
│   │   ├── site/                  # Menu item view and router
│   │   └── media/                 # CSS, JS, CodeMirror modes for GraphQL and Twig
│   ├── mod_kurso/                 # Module
│   ├── plg_content_kurso/         # {kurso query="…"} tag
│   ├── plg_editors-xtd_kurso/     # Editor button
│   ├── plg_task_kurso/            # Scheduled task
│   └── pkg_kurso/                 # Package manifest and installer script
├── bin/build.sh                   # Builds the installable package
├── updates/pkg_kurso.xml          # Joomla update server
└── specs/specification.md
```

## License

MIT, see [LICENSE](LICENSE). Twig is distributed under the BSD-3-Clause license.
