<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Administrator\Service;

use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Example queries with matching templates. Used as defaults for new queries and offered in the editor.
 *
 * The examples are tested against the KURSO demo system (https://demo.kurso.de/api/graphql).
 *
 * @since  1.0.0
 */
final class Examples
{
    /**
     * @return  array<string, array{title: string, graphql: string, variables: string, template: string}>
     *
     * @since   1.0.0
     */
    public static function all(): array
    {
        return [
            'table'      => [
                'title'     => Text::_('COM_KURSO_EXAMPLE_TABLE'),
                'graphql'   => self::TABLE_QUERY,
                'variables' => self::TABLE_VARIABLES,
                'template'  => self::TABLE_TEMPLATE,
            ],
            'cards'      => [
                'title'     => Text::_('COM_KURSO_EXAMPLE_CARDS'),
                'graphql'   => self::TABLE_QUERY,
                'variables' => self::TABLE_VARIABLES,
                'template'  => self::CARDS_TEMPLATE,
            ],
            'coursetype' => [
                'title'     => Text::_('COM_KURSO_EXAMPLE_COURSETYPE'),
                'graphql'   => self::COURSETYPE_QUERY,
                'variables' => self::COURSETYPE_VARIABLES,
                'template'  => self::COURSETYPE_TEMPLATE,
            ],
            'detail'     => [
                'title'     => Text::_('COM_KURSO_EXAMPLE_DETAIL'),
                'graphql'   => self::DETAIL_QUERY,
                'variables' => self::DETAIL_VARIABLES,
                'template'  => self::DETAIL_TEMPLATE,
            ],
        ];
    }

    /**
     * @return  array{graphql: string, variables: string, template: string}  The defaults for a new query
     *
     * @since   1.0.0
     */
    public static function defaults(): array
    {
        return [
            'graphql'   => self::TABLE_QUERY,
            'variables' => self::TABLE_VARIABLES,
            'template'  => self::TABLE_TEMPLATE,
        ];
    }

    private const TABLE_QUERY = <<<'GRAPHQL'
query Courses($startDateMin: Date!) {
  allCourses(
    filter: { startDate_gte: $startDateMin }
    orderBy: startDate_ASC
    first: 20
  ) {
    id
    name
    startDate
    endDate
    location {
      name
      town
    }
    maxPart
    # Active bookings (without cancellations) for the availability display
    _bookingsMeta(filter: { workflowState_not_in: ["CANCELLED"] }) {
      count
    }
    onlineEnrollmentUrl
  }
}
GRAPHQL;

    private const TABLE_VARIABLES = <<<'JSON'
{
  "startDateMin": "{{ date()|date('Y-m-d') }}"
}
JSON;

    private const TABLE_TEMPLATE = <<<'TWIG'
<table class="kurso-table">
  <thead>
    <tr>
      <th>Kurs</th>
      <th>Beginn</th>
      <th>Ende</th>
      <th>Ort</th>
      <th>Freie Plätze</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
    {% for course in allCourses %}
    {% set frei = course.maxPart - course._bookingsMeta.count %}
    <tr>
      <td>{{ course.name }}</td>
      <td>{{ course.startDate|date("d.m.Y") }}</td>
      <td>{{ course.endDate|date("d.m.Y") }}</td>
      <td>{{ course.location.name }}{% if course.location.town %}, {{ course.location.town }}{% endif %}</td>
      <td>{% if frei > 0 %}{{ frei }} / {{ course.maxPart }}{% else %}<span class="kurso-badge--voll">Ausgebucht</span>{% endif %}</td>
      <td>{% if frei > 0 and course.onlineEnrollmentUrl %}<a href="{{ course.onlineEnrollmentUrl }}" class="kurso-button">Jetzt buchen</a>{% endif %}</td>
    </tr>
    {% else %}
    <tr><td colspan="6">Zurzeit sind keine Kurse geplant.</td></tr>
    {% endfor %}
  </tbody>
</table>
TWIG;

    private const CARDS_TEMPLATE = <<<'TWIG'
<div class="kurso-grid">
  {% for course in allCourses %}
  {% set frei = course.maxPart - course._bookingsMeta.count %}
  <article class="kurso-card">
    <h3 class="kurso-card__title">{{ course.name }}</h3>
    <p class="kurso-card__date">{{ course.startDate|jdate('d. F Y') }} – {{ course.endDate|jdate('d. F Y') }}</p>
    <p class="kurso-card__location">{{ course.location.town|default('–') }}</p>
    <p class="kurso-card__capacity">
      {% if frei > 0 %}{{ frei }} von {{ course.maxPart }} Plätzen frei{% else %}<span class="kurso-badge--voll">Ausgebucht</span>{% endif %}
    </p>
    {% if frei > 0 and course.onlineEnrollmentUrl %}
      <a href="{{ course.onlineEnrollmentUrl }}" class="kurso-button">Jetzt buchen</a>
    {% endif %}
  </article>
  {% endfor %}
</div>
TWIG;

    private const COURSETYPE_QUERY = <<<'GRAPHQL'
query CourseTypes($from: Date!) {
  allCourseTypes {
    name
    courses(filter: { startDate_gte: $from }, orderBy: startDate_ASC) {
      startDate
      location {
        town
      }
      maxPart
      _bookingsMeta(filter: { workflowState_not_in: ["CANCELLED"] }) {
        count
      }
      onlineEnrollmentUrl
    }
  }
}
GRAPHQL;

    private const COURSETYPE_VARIABLES = <<<'JSON'
{
  "from": "{{ date()|date('Y-m-d') }}"
}
JSON;

    private const COURSETYPE_TEMPLATE = <<<'TWIG'
{% for ct in allCourseTypes|filter(ct => ct.courses|length > 0) %}
<section class="kurso-coursetype">
  <h2>{{ ct.name }}</h2>
  <table class="kurso-table">
    <thead>
      <tr><th>Datum</th><th>Ort</th><th>Plätze</th><th></th></tr>
    </thead>
    <tbody>
      {% for course in ct.courses %}
      {% set frei = course.maxPart - course._bookingsMeta.count %}
      <tr>
        <td>{{ course.startDate|date("d.m.Y") }}</td>
        <td>{{ course.location.town }}</td>
        <td>{{ frei > 0 ? frei : "Ausgebucht" }}</td>
        <td>
          {% if frei > 0 and course.onlineEnrollmentUrl %}
            <a href="{{ course.onlineEnrollmentUrl }}" class="kurso-button">Jetzt buchen</a>
          {% endif %}
        </td>
      </tr>
      {% endfor %}
    </tbody>
  </table>
</section>
{% endfor %}
TWIG;

    private const DETAIL_QUERY = <<<'GRAPHQL'
query CourseDetail($id: ID!) {
  Course(id: $id) {
    name
    startDate
    endDate
    location {
      name
      town
    }
    minPart
    maxPart
    _bookingsMeta(filter: { workflowState_not_in: ["CANCELLED"] }) {
      count
    }
    onlineEnrollmentUrl
  }
}
GRAPHQL;

    private const DETAIL_VARIABLES = <<<'JSON'
{
  "id": "70"
}
JSON;

    private const DETAIL_TEMPLATE = <<<'TWIG'
{% set frei = Course.maxPart - Course._bookingsMeta.count %}
<article class="kurso-detail">
  <h2>{{ Course.name }}</h2>
  <dl>
    <dt>Beginn</dt><dd>{{ Course.startDate|jdate('l, d. F Y') }}</dd>
    <dt>Ende</dt><dd>{{ Course.endDate|jdate('l, d. F Y') }}</dd>
    <dt>Ort</dt><dd>{{ Course.location.name }}, {{ Course.location.town }}</dd>
    <dt>Kapazität</dt><dd>mind. {{ Course.minPart }}, max. {{ Course.maxPart }}</dd>
    <dt>Freie Plätze</dt><dd>{{ frei > 0 ? frei : "Ausgebucht" }}</dd>
  </dl>
  {% if frei > 0 and Course.onlineEnrollmentUrl %}
    <a href="{{ Course.onlineEnrollmentUrl }}" class="kurso-button kurso-button--large">Jetzt anmelden</a>
  {% else %}
    <p class="kurso-ausgebucht">Dieser Kurs ist leider ausgebucht.</p>
  {% endif %}
</article>
TWIG;
}
