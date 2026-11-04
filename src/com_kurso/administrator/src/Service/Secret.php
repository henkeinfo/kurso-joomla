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
 * Authenticated symmetric encryption for stored credentials.
 *
 * Uses libsodium's secretbox (XSalsa20-Poly1305). Joomla ships paragonie/sodium_compat, so this works
 * even where the sodium extension is missing. The key is derived from the site's secret in
 * configuration.php, so a database dump alone does not reveal the KURSO password.
 *
 * @since  1.0.0
 */
final class Secret
{
    /**
     * Version prefix of the stored format, allows changing the algorithm later.
     */
    private const PREFIX = 'sb1:';

    /**
     * @param   string  $siteSecret  The Joomla site secret ($secret in configuration.php)
     *
     * @since   1.0.0
     */
    public function __construct(private readonly string $siteSecret)
    {
    }

    /**
     * Encrypts a value.
     *
     * @param   string  $plain  The plain text
     *
     * @return  string  Printable cipher text including the format prefix
     *
     * @since   1.0.0
     */
    public function encrypt(string $plain): string
    {
        $nonce  = random_bytes(\SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plain, $nonce, $this->key());

        return self::PREFIX . base64_encode($nonce . $cipher);
    }

    /**
     * Decrypts a value produced by encrypt().
     *
     * @param   string  $stored  The stored cipher text
     *
     * @return  string|null  The plain text, or null if the value cannot be decrypted (e.g. the site secret changed)
     *
     * @since   1.0.0
     */
    public function decrypt(string $stored): ?string
    {
        if (!str_starts_with($stored, self::PREFIX)) {
            return null;
        }

        $raw = base64_decode(substr($stored, \strlen(self::PREFIX)), true);

        if ($raw === false || \strlen($raw) <= \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            return null;
        }

        $nonce  = substr($raw, 0, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, \SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        try {
            $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->key());
        } catch (\Throwable) {
            return null;
        }

        return $plain === false ? null : $plain;
    }

    /**
     * Derives the 256 bit encryption key from the site secret.
     *
     * @return  string
     *
     * @since   1.0.0
     */
    private function key(): string
    {
        if ($this->siteSecret === '') {
            throw new \RuntimeException('The Joomla site secret is empty, cannot encrypt the KURSO password.');
        }

        return hash_hmac('sha256', 'com_kurso:connection-password:v1', $this->siteSecret, true);
    }
}
