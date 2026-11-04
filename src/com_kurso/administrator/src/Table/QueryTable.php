<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Administrator\Table;

use Joomla\CMS\Factory;
use Joomla\CMS\Filter\OutputFilter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Event\DispatcherInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Table for #__kurso_queries.
 *
 * @property  int     $id
 * @property  string  $title
 * @property  string  $alias
 * @property  string  $graphql
 * @property  string  $variables
 * @property  string  $template
 * @property  int     $refresh_interval
 * @property  int     $state
 * @property  string  $note
 * @property  string  $created
 * @property  int     $created_by
 * @property  string  $modified
 * @property  int     $modified_by
 *
 * @since  1.0.0
 */
class QueryTable extends Table
{
    /**
     * Minimum and maximum refresh interval in minutes (one minute to one week).
     */
    public const MIN_INTERVAL = 1;
    public const MAX_INTERVAL = 10080;

    /**
     * Indicates that columns fully support the NULL value in the database
     *
     * @var    boolean
     * @since  1.0.0
     */
    protected $_supportNullValue = true;

    /**
     * @param   DatabaseInterface         $db          Database connector object
     * @param   DispatcherInterface|null  $dispatcher  Event dispatcher for this table
     *
     * @since   1.0.0
     */
    public function __construct(DatabaseInterface $db, ?DispatcherInterface $dispatcher = null)
    {
        $this->typeAlias = 'com_kurso.query';

        $this->setColumnAlias('published', 'state');

        parent::__construct('#__kurso_queries', 'id', $db, $dispatcher);
    }

    /**
     * Validates and normalises the row before it is stored.
     *
     * @return  boolean
     *
     * @since   1.0.0
     */
    public function check()
    {
        try {
            parent::check();
        } catch (\Exception $e) {
            $this->setError($e->getMessage());

            return false;
        }

        $this->title = trim((string) $this->title);

        if ($this->title === '') {
            $this->setError(Text::_('COM_KURSO_ERROR_TITLE_REQUIRED'));

            return false;
        }

        $this->alias = self::normaliseAlias(trim((string) $this->alias) !== '' ? (string) $this->alias : $this->title);

        if ($this->alias === '') {
            $this->alias = Factory::getDate()->format('Y-m-d-H-i-s');
        }

        if ($this->aliasExists($this->alias, (int) $this->id)) {
            $this->setError(Text::sprintf('COM_KURSO_ERROR_ALIAS_EXISTS', $this->alias));

            return false;
        }

        if (trim((string) $this->graphql) === '') {
            $this->setError(Text::_('COM_KURSO_ERROR_QUERY_EMPTY'));

            return false;
        }

        $this->variables        = (string) $this->variables;
        $this->template         = (string) $this->template;
        $this->note             = trim((string) ($this->note ?? ''));
        $this->refresh_interval = min(self::MAX_INTERVAL, max(self::MIN_INTERVAL, (int) $this->refresh_interval ?: 60));
        $this->state            = (int) $this->state === 1 ? 1 : 0;

        return true;
    }

    /**
     * Sets the created/modified columns and stores the row.
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null.
     *
     * @return  boolean
     *
     * @since   1.0.0
     */
    public function store($updateNulls = true)
    {
        $date   = Factory::getDate()->toSql();
        $userId = (int) Factory::getApplication()->getIdentity()?->id;

        if (!(int) $this->id) {
            $this->created    = $this->created ?: $date;
            $this->created_by = $this->created_by ?: $userId;
        }

        $this->modified    = $date;
        $this->modified_by = $userId;

        return parent::store($updateNulls);
    }

    /**
     * Deletes the row and its cached data.
     *
     * @param   mixed  $pk  The primary key
     *
     * @return  boolean
     *
     * @since   1.0.0
     */
    public function delete($pk = null)
    {
        $id = (int) ($pk ?? $this->id);

        if (!parent::delete($pk)) {
            return false;
        }

        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->delete($db->quoteName('#__kurso_cache'))
            ->where($db->quoteName('query_id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        $db->setQuery($query)->execute();

        return true;
    }

    /**
     * Converts a string to a valid alias: lowercase ASCII letters, digits, hyphen and underscore.
     *
     * @param   string  $value  The value
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public static function normaliseAlias(string $value): string
    {
        $alias = OutputFilter::stringURLSafe($value);
        $alias = preg_replace('/[^a-z0-9_-]+/', '-', strtolower($alias)) ?? '';

        return trim(preg_replace('/-{2,}/', '-', $alias) ?? '', '-');
    }

    /**
     * @param   string  $alias      The alias
     * @param   int     $excludeId  The id of the current row
     *
     * @return  boolean  True if another query uses the alias
     *
     * @since   1.0.0
     */
    private function aliasExists(string $alias, int $excludeId): bool
    {
        $db    = $this->getDatabase();
        $query = $db->createQuery()
            ->select('COUNT(*)')
            ->from($db->quoteName('#__kurso_queries'))
            ->where($db->quoteName('alias') . ' = :alias')
            ->where($db->quoteName('id') . ' != :id')
            ->bind(':alias', $alias)
            ->bind(':id', $excludeId, ParameterType::INTEGER);

        return (int) $db->setQuery($query)->loadResult() > 0;
    }
}
