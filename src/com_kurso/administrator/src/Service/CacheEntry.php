<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Administrator\Service;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The cached API result of a query and its fetch status.
 *
 * All timestamps are UTC unix timestamps.
 *
 * @since  1.0.0
 */
final class CacheEntry
{
    /**
     * @param   array|null   $data         The last successfully fetched `data` object, null if never fetched
     * @param   int|null     $fetchedAt    Time of the last successful fetch
     * @param   int|null     $attemptedAt  Time of the last fetch attempt (successful or not)
     * @param   string|null  $error        Message of the last failed attempt, null after a successful fetch
     * @param   int|null     $errorAt      Time of the last failed attempt
     *
     * @since   1.0.0
     */
    public function __construct(
        public readonly ?array $data,
        public readonly ?int $fetchedAt,
        public readonly ?int $attemptedAt,
        public readonly ?string $error,
        public readonly ?int $errorAt
    ) {
    }

    /**
     * @return  boolean  True if data is available
     *
     * @since   1.0.0
     */
    public function hasData(): bool
    {
        return $this->data !== null;
    }

    /**
     * @param   int  $maxAgeSeconds  The maximum age
     * @param   int  $now            The current time
     *
     * @return  boolean  True if there is no data or it is older than the given age
     *
     * @since   1.0.0
     */
    public function isOlderThan(int $maxAgeSeconds, int $now): bool
    {
        return $this->fetchedAt === null || $this->fetchedAt <= $now - $maxAgeSeconds;
    }
}
