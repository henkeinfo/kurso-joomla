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
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Kurso\Component\Kurso\Administrator\Exception\KursoException;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Connection settings for the KURSO GraphQL API (URL, username, encrypted password).
 *
 * Stored in #__kurso_settings rather than in the component parameters: the Joomla "Options" dialog
 * rewrites all component parameters on save, which would drop values that are not part of its form,
 * and the password must never be rendered into a form.
 *
 * @since  1.0.0
 */
final class ConnectionSettings
{
    private const TABLE = '#__kurso_settings';

    /**
     * Cached settings rows (name => value)
     *
     * @var  array<string, string>|null
     */
    private ?array $values = null;

    /**
     * @param   DatabaseInterface  $db      The database
     * @param   Secret             $secret  Encryption helper for the password
     *
     * @since   1.0.0
     */
    public function __construct(private readonly DatabaseInterface $db, private readonly Secret $secret)
    {
    }

    /**
     * @return  string  The GraphQL endpoint URL
     *
     * @since   1.0.0
     */
    public function getUrl(): string
    {
        return $this->load()['graphql_url'] ?? '';
    }

    /**
     * @return  string  The KURSO username
     *
     * @since   1.0.0
     */
    public function getUsername(): string
    {
        return $this->load()['username'] ?? '';
    }

    /**
     * @return  string  The decrypted password, or an empty string if none is stored or it cannot be decrypted
     *
     * @since   1.0.0
     */
    public function getPassword(): string
    {
        $stored = $this->load()['password'] ?? '';

        if ($stored === '') {
            return '';
        }

        return $this->secret->decrypt($stored) ?? '';
    }

    /**
     * @return  boolean  True if a password is stored (whether or not it can be decrypted)
     *
     * @since   1.0.0
     */
    public function hasPassword(): bool
    {
        return ($this->load()['password'] ?? '') !== '';
    }

    /**
     * A stored password becomes unreadable when the site secret in configuration.php changes.
     *
     * @return  boolean
     *
     * @since   1.0.0
     */
    public function isPasswordUnreadable(): bool
    {
        return $this->hasPassword() && $this->getPassword() === '';
    }

    /**
     * @return  boolean  True if URL, username and a readable password are available
     *
     * @since   1.0.0
     */
    public function isConfigured(): bool
    {
        return $this->getUrl() !== '' && $this->getUsername() !== '' && $this->getPassword() !== '';
    }

    /**
     * Stores the connection settings.
     *
     * @param   string       $url       The GraphQL endpoint URL
     * @param   string       $username  The username
     * @param   string|null  $password  The new password, or null to keep the stored one
     *
     * @return  void
     *
     * @throws  KursoException  If the URL is not acceptable
     *
     * @since   1.0.0
     */
    public function save(string $url, string $username, ?string $password): void
    {
        $url = trim($url);
        self::assertValidUrl($url);

        $this->set('graphql_url', $url);
        $this->set('username', trim($username));

        if ($password !== null && $password !== '') {
            $this->set('password', $this->secret->encrypt($password));
        }

        $this->values = null;
    }

    /**
     * Validates a GraphQL endpoint URL.
     *
     * HTTPS is required because the credentials are sent with every request. Plain HTTP is only
     * accepted for local development hosts.
     *
     * @param   string  $url  The URL
     *
     * @return  void
     *
     * @throws  KursoException
     *
     * @since   1.0.0
     */
    public static function assertValidUrl(string $url): void
    {
        $parts  = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host   = strtolower($parts['host'] ?? '');

        if ($url === '' || $host === '' || !\in_array($scheme, ['https', 'http'], true) || isset($parts['user']) || isset($parts['pass'])) {
            throw new KursoException(Text::_('COM_KURSO_ERROR_URL_INVALID'));
        }

        if ($scheme === 'http' && !\in_array($host, ['localhost', '127.0.0.1', '[::1]', 'host.docker.internal'], true) && !str_ends_with($host, '.localhost')) {
            throw new KursoException(Text::_('COM_KURSO_ERROR_URL_NOT_HTTPS'));
        }

        // Rejects URLs that the Joomla URI parser would interpret differently.
        if ((new Uri($url))->getHost() !== ($parts['host'] ?? '')) {
            throw new KursoException(Text::_('COM_KURSO_ERROR_URL_INVALID'));
        }
    }

    /**
     * Loads all settings rows.
     *
     * @return  array<string, string>
     *
     * @since   1.0.0
     */
    private function load(): array
    {
        if ($this->values !== null) {
            return $this->values;
        }

        $query = $this->db->createQuery()
            ->select($this->db->quoteName(['name', 'value']))
            ->from($this->db->quoteName(self::TABLE));

        try {
            $this->values = (array) $this->db->setQuery($query)->loadAssocList('name', 'value');
        } catch (\RuntimeException) {
            // Table missing (e.g. during an incomplete installation): behave as "not configured".
            $this->values = [];
        }

        return $this->values;
    }

    /**
     * Inserts or updates a single settings row.
     *
     * @param   string  $name   The setting name
     * @param   string  $value  The value
     *
     * @return  void
     *
     * @since   1.0.0
     */
    private function set(string $name, string $value): void
    {
        $query = $this->db->createQuery()
            ->select('COUNT(*)')
            ->from($this->db->quoteName(self::TABLE))
            ->where($this->db->quoteName('name') . ' = :name')
            ->bind(':name', $name);

        $exists = (int) $this->db->setQuery($query)->loadResult() > 0;

        $query = $this->db->createQuery();

        if ($exists) {
            $query->update($this->db->quoteName(self::TABLE))
                ->set($this->db->quoteName('value') . ' = :value')
                ->where($this->db->quoteName('name') . ' = :name');
        } else {
            $query->insert($this->db->quoteName(self::TABLE))
                ->columns($this->db->quoteName(['name', 'value']))
                ->values(':name, :value');
        }

        $query->bind(':name', $name, ParameterType::STRING)
            ->bind(':value', $value, ParameterType::STRING);

        $this->db->setQuery($query)->execute();
    }
}
