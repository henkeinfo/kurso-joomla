<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Administrator\Service;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Database access for queries (#__kurso_queries) and their cached results (#__kurso_cache).
 *
 * All statements use the query builder with bound parameters and only portable SQL, so MySQL/MariaDB
 * and PostgreSQL are supported.
 *
 * @since  1.0.0
 */
final class QueryRepository
{
    private const QUERY_COLUMNS = ['id', 'title', 'alias', 'graphql', 'variables', 'template', 'refresh_interval', 'state'];

    /**
     * @param   DatabaseInterface  $db  The database
     *
     * @since   1.0.0
     */
    public function __construct(private readonly DatabaseInterface $db)
    {
    }

    /**
     * Finds a query by its alias.
     *
     * @param   string   $alias          The alias
     * @param   boolean  $publishedOnly  Only return published queries
     *
     * @return  object|null
     *
     * @since   1.0.0
     */
    public function findByAlias(string $alias, bool $publishedOnly = true): ?object
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(self::QUERY_COLUMNS))
            ->from($this->db->quoteName('#__kurso_queries'))
            ->where($this->db->quoteName('alias') . ' = :alias')
            ->bind(':alias', $alias);

        if ($publishedOnly) {
            $query->where($this->db->quoteName('state') . ' = 1');
        }

        return $this->db->setQuery($query)->loadObject() ?: null;
    }

    /**
     * Finds a query by its id.
     *
     * @param   int      $id             The id
     * @param   boolean  $publishedOnly  Only return published queries
     *
     * @return  object|null
     *
     * @since   1.0.0
     */
    public function findById(int $id, bool $publishedOnly = false): ?object
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(self::QUERY_COLUMNS))
            ->from($this->db->quoteName('#__kurso_queries'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);

        if ($publishedOnly) {
            $query->where($this->db->quoteName('state') . ' = 1');
        }

        return $this->db->setQuery($query)->loadObject() ?: null;
    }

    /**
     * Lists queries for selection lists (id, title, alias, state).
     *
     * @param   boolean  $publishedOnly  Only published queries
     *
     * @return  object[]
     *
     * @since   1.0.0
     */
    public function getList(bool $publishedOnly = false): array
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['id', 'title', 'alias', 'state']))
            ->from($this->db->quoteName('#__kurso_queries'))
            ->order($this->db->quoteName('title') . ' ASC');

        if ($publishedOnly) {
            $query->where($this->db->quoteName('state') . ' = 1');
        }

        return $this->db->setQuery($query)->loadObjectList();
    }

    /**
     * Lists all published queries together with their fetch timestamps (without the cached data).
     *
     * @return  object[]  Query rows with additional fetched_at and attempted_at columns
     *
     * @since   1.0.0
     */
    public function getPublishedWithStatus(): array
    {
        $columns = array_map(fn (string $column): string => 'q.' . $column, self::QUERY_COLUMNS);

        $query = $this->db->createQuery()
            ->select($this->db->quoteName($columns))
            ->select($this->db->quoteName(['c.fetched_at', 'c.attempted_at']))
            ->from($this->db->quoteName('#__kurso_queries', 'q'))
            ->join('LEFT', $this->db->quoteName('#__kurso_cache', 'c'), $this->db->quoteName('c.query_id') . ' = ' . $this->db->quoteName('q.id'))
            ->where($this->db->quoteName('q.state') . ' = 1')
            ->order($this->db->quoteName('q.id') . ' ASC');

        return $this->db->setQuery($query)->loadObjectList();
    }

    /**
     * Loads the cache entry of a query.
     *
     * @param   int  $queryId  The query id
     *
     * @return  CacheEntry
     *
     * @since   1.0.0
     */
    public function getCache(int $queryId): CacheEntry
    {
        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['data', 'fetched_at', 'attempted_at', 'error', 'error_at']))
            ->from($this->db->quoteName('#__kurso_cache'))
            ->where($this->db->quoteName('query_id') . ' = :id')
            ->bind(':id', $queryId, ParameterType::INTEGER);

        $row = $this->db->setQuery($query)->loadObject();

        if (!$row) {
            return new CacheEntry(null, null, null, null, null);
        }

        $data = null;

        if ($row->data !== null && $row->data !== '') {
            try {
                $decoded = json_decode($row->data, true, 512, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
                $data    = \is_array($decoded) ? $decoded : null;
            } catch (\JsonException) {
                $data = null;
            }
        }

        return new CacheEntry(
            $data,
            self::toTimestamp($row->fetched_at),
            self::toTimestamp($row->attempted_at),
            $row->error !== null && $row->error !== '' ? (string) $row->error : null,
            self::toTimestamp($row->error_at)
        );
    }

    /**
     * Atomically claims a query for fetching, so that concurrent requests do not fetch the same query.
     *
     * The claim succeeds if the query was not attempted within the cooldown period.
     *
     * @param   int       $queryId          The query id
     * @param   int       $cooldownSeconds  Minimum time between two attempts
     * @param   int|null  $time             The current time (for testing)
     *
     * @return  boolean  True if this request may fetch the query
     *
     * @since   1.0.0
     */
    public function claim(int $queryId, int $cooldownSeconds, ?int $time = null): bool
    {
        $this->ensureCacheRow($queryId);

        $time      = $time ?? time();
        $now       = Factory::getDate('@' . $time)->toSql();
        $threshold = Factory::getDate('@' . ($time - max(0, $cooldownSeconds)))->toSql();

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__kurso_cache'))
            ->set($this->db->quoteName('attempted_at') . ' = :now')
            ->where($this->db->quoteName('query_id') . ' = :id')
            ->extendWhere('AND', [
                $this->db->quoteName('attempted_at') . ' IS NULL',
                $this->db->quoteName('attempted_at') . ' <= :threshold',
            ], 'OR')
            ->bind(':now', $now)
            ->bind(':id', $queryId, ParameterType::INTEGER)
            ->bind(':threshold', $threshold);

        $this->db->setQuery($query)->execute();

        return $this->db->getAffectedRows() > 0;
    }

    /**
     * Stores a successfully fetched result.
     *
     * @param   int    $queryId  The query id
     * @param   array  $data     The `data` object of the response
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function storeResult(int $queryId, array $data): void
    {
        $this->ensureCacheRow($queryId);

        $json = json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION);
        $now  = Factory::getDate()->toSql();

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__kurso_cache'))
            ->set($this->db->quoteName('data') . ' = :data')
            ->set($this->db->quoteName('fetched_at') . ' = :fetched')
            ->set($this->db->quoteName('attempted_at') . ' = :attempted')
            ->set($this->db->quoteName('error') . ' = NULL')
            ->set($this->db->quoteName('error_at') . ' = NULL')
            ->where($this->db->quoteName('query_id') . ' = :id')
            ->bind(':data', $json)
            ->bind(':fetched', $now)
            ->bind(':attempted', $now)
            ->bind(':id', $queryId, ParameterType::INTEGER);

        $this->db->setQuery($query)->execute();
    }

    /**
     * Records a failed fetch. The previously cached data is kept (graceful degradation).
     *
     * @param   int     $queryId  The query id
     * @param   string  $message  The error message
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function storeError(int $queryId, string $message): void
    {
        $this->ensureCacheRow($queryId);

        $message = mb_substr($message, 0, 1000);
        $now     = Factory::getDate()->toSql();

        $query = $this->db->createQuery()
            ->update($this->db->quoteName('#__kurso_cache'))
            ->set($this->db->quoteName('error') . ' = :error')
            ->set($this->db->quoteName('error_at') . ' = :errorAt')
            ->set($this->db->quoteName('attempted_at') . ' = :attempted')
            ->where($this->db->quoteName('query_id') . ' = :id')
            ->bind(':error', $message)
            ->bind(':errorAt', $now)
            ->bind(':attempted', $now)
            ->bind(':id', $queryId, ParameterType::INTEGER);

        $this->db->setQuery($query)->execute();
    }

    /**
     * Deletes the cache entries of the given queries.
     *
     * @param   int[]  $queryIds  The query ids
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function deleteCache(array $queryIds): void
    {
        $queryIds = array_values(array_filter(array_map('intval', $queryIds)));

        if ($queryIds === []) {
            return;
        }

        $query = $this->db->createQuery()
            ->delete($this->db->quoteName('#__kurso_cache'))
            ->whereIn($this->db->quoteName('query_id'), $queryIds);

        $this->db->setQuery($query)->execute();
    }

    /**
     * Makes sure a cache row exists for the query (inserting an empty one if necessary).
     *
     * @param   int  $queryId  The query id
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function ensureCacheRow(int $queryId): void
    {
        $query = $this->db->createQuery()
            ->select('COUNT(*)')
            ->from($this->db->quoteName('#__kurso_cache'))
            ->where($this->db->quoteName('query_id') . ' = :id')
            ->bind(':id', $queryId, ParameterType::INTEGER);

        if ((int) $this->db->setQuery($query)->loadResult() > 0) {
            return;
        }

        $query = $this->db->createQuery()
            ->insert($this->db->quoteName('#__kurso_cache'))
            ->columns($this->db->quoteName(['query_id']))
            ->values(':id')
            ->bind(':id', $queryId, ParameterType::INTEGER);

        try {
            $this->db->setQuery($query)->execute();
        } catch (\RuntimeException) {
            // A concurrent request inserted the row in the meantime (primary key violation).
        }
    }

    /**
     * Converts a SQL datetime (UTC) to a unix timestamp.
     *
     * @param   string|null  $value  The SQL datetime
     *
     * @return  int|null
     *
     * @since   1.0.0
     */
    public static function toTimestamp(?string $value): ?int
    {
        if ($value === null || $value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        try {
            return Factory::getDate($value, 'UTC')->toUnix();
        } catch (\Exception) {
            return null;
        }
    }
}
