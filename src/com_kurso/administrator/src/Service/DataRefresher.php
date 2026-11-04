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
use Joomla\CMS\Log\Log;
use Kurso\Component\Kurso\Administrator\Exception\KursoException;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Fetches query results from the KURSO API and stores them in the cache.
 *
 * Refreshes are triggered by the scheduled task (plg_task_kurso), by the "Refresh data" button in the
 * administrator and, as a fallback, while a page is rendered (see OutputRenderer). Concurrent refreshes
 * of the same query are prevented with an atomic claim on the cache row. A failed fetch never replaces
 * the cached data.
 *
 * @since  1.0.0
 */
final class DataRefresher
{
    /**
     * Minimum seconds between two scheduled attempts of the same query, protects against overlapping runs.
     */
    private const SCHEDULED_COOLDOWN = 50;

    /**
     * Whether the log category has been registered
     *
     * @var boolean
     */
    private static bool $loggerRegistered = false;

    /**
     * @param   QueryRepository  $repository  The repository
     * @param   GraphQLClient    $client      The API client
     *
     * @since   1.0.0
     */
    public function __construct(private readonly QueryRepository $repository, private readonly GraphQLClient $client)
    {
    }

    /**
     * Fetches a query now and stores the result.
     *
     * @param   object  $query  The query row (id, alias, graphql, variables)
     *
     * @return  array  The fetched data
     *
     * @throws  KursoException  If fetching fails (the error is recorded as well)
     *
     * @since   1.0.0
     */
    public function refresh(object $query): array
    {
        try {
            $data = $this->client->execute((string) $query->graphql, (string) $query->variables);
        } catch (KursoException $e) {
            $this->repository->storeError((int) $query->id, $e->getMessage());
            self::log(Text::sprintf('COM_KURSO_LOG_FETCH_FAILED', $query->alias, $e->getMessage()), Log::WARNING);

            throw $e;
        }

        $this->repository->storeResult((int) $query->id, $data);

        return $data;
    }

    /**
     * Fetches a query by id now.
     *
     * @param   int  $id  The query id
     *
     * @return  array  The fetched data
     *
     * @throws  KursoException
     *
     * @since   1.0.0
     */
    public function refreshById(int $id): array
    {
        $query = $this->repository->findById($id);

        if ($query === null) {
            throw new KursoException(Text::sprintf('COM_KURSO_ERROR_QUERY_ID_NOT_FOUND', $id));
        }

        return $this->refresh($query);
    }

    /**
     * Refreshes all published queries whose refresh interval has elapsed.
     *
     * @param   int|null  $now  The current time (for testing)
     *
     * @return  array{refreshed: string[], failed: array<string, string>, skipped: int}
     *
     * @since   1.0.0
     */
    public function refreshDue(?int $now = null): array
    {
        $now    = $now ?? time();
        $report = ['refreshed' => [], 'failed' => [], 'skipped' => 0];

        foreach ($this->repository->getPublishedWithStatus() as $query) {
            $interval  = self::intervalSeconds($query);
            $fetchedAt = QueryRepository::toTimestamp($query->fetched_at);

            // A small tolerance keeps queries from slipping one task run because of timing jitter.
            if ($fetchedAt !== null && $fetchedAt > $now - $interval + 30) {
                $report['skipped']++;

                continue;
            }

            if (!$this->repository->claim((int) $query->id, self::SCHEDULED_COOLDOWN, $now)) {
                $report['skipped']++;

                continue;
            }

            try {
                $this->refresh($query);
                $report['refreshed'][] = $query->alias;
            } catch (KursoException $e) {
                $report['failed'][$query->alias] = $e->getMessage();
            }
        }

        return $report;
    }

    /**
     * Returns usable data for rendering, fetching it if necessary.
     *
     * - No data cached yet: fetch synchronously (first page view).
     * - Data older than twice the refresh interval (the scheduled task is apparently not running) and
     *   $refreshStale is set: fetch synchronously, but fall back to the old data on failure.
     * - Otherwise: use the cached data.
     *
     * Failed attempts are retried at most once per back-off period, so an unreachable API does not slow
     * down every page view.
     *
     * @param   object  $query         The query row
     * @param   bool    $refreshStale  Whether stale data may be refreshed during the page view
     *
     * @return  CacheEntry
     *
     * @since   1.0.0
     */
    public function getForDisplay(object $query, bool $refreshStale): CacheEntry
    {
        $cache    = $this->repository->getCache((int) $query->id);
        $interval = self::intervalSeconds($query);
        $now      = time();

        $needsFetch = !$cache->hasData() || ($refreshStale && $cache->isOlderThan(2 * $interval, $now));

        if (!$needsFetch) {
            return $cache;
        }

        $backoff = min(max($interval, 60), 900);

        if (!$this->repository->claim((int) $query->id, $backoff)) {
            return $cache;
        }

        try {
            $this->refresh($query);
        } catch (KursoException) {
            // Recorded by refresh(); keep using the previous data.
        }

        return $this->repository->getCache((int) $query->id);
    }

    /**
     * @param   object  $query  The query row
     *
     * @return  int  The refresh interval in seconds (at least one minute)
     *
     * @since   1.0.0
     */
    private static function intervalSeconds(object $query): int
    {
        return max(1, (int) ($query->refresh_interval ?? 60)) * 60;
    }

    /**
     * Writes to the com_kurso log file (administrator/logs/com_kurso.php).
     *
     * @param   string  $message   The message
     * @param   int     $priority  The Log priority
     *
     * @return  void
     *
     * @since   1.0.0
     */
    private static function log(string $message, int $priority): void
    {
        if (!self::$loggerRegistered) {
            Log::addLogger(['text_file' => 'com_kurso.php'], Log::ALL, ['com_kurso']);
            self::$loggerRegistered = true;
        }

        try {
            Log::add($message, $priority, 'com_kurso');
        } catch (\Throwable) {
            // Logging must never break page rendering.
        }
    }
}
