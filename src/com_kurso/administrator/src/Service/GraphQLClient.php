<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Administrator\Service;

use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\Language\Text;
use Kurso\Component\Kurso\Administrator\Exception\KursoException;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Client for the KURSO GraphQL API (HTTP Basic Auth).
 *
 * @since  1.0.0
 */
final class GraphQLClient
{
    /**
     * Responses larger than this are rejected to protect the database and memory.
     */
    public const MAX_RESPONSE_BYTES = 16 * 1024 * 1024;

    /**
     * @param   ConnectionSettings  $settings  The stored connection settings
     * @param   TwigFactory         $twig      Used to evaluate Twig expressions in variables
     * @param   int                 $timeout   Request timeout in seconds
     * @param   string              $version   Extension version for the User-Agent header
     *
     * @since   1.0.0
     */
    public function __construct(
        private readonly ConnectionSettings $settings,
        private readonly TwigFactory $twig,
        private readonly int $timeout = 15,
        private readonly string $version = '1.0.0'
    ) {
    }

    /**
     * Evaluates the Twig expressions in a variables JSON string and decodes it.
     *
     * @param   string  $json  The variables JSON, e.g. {"from": "{{ date()|date('Y-m-d') }}"}
     *
     * @return  array  The decoded variables (empty if $json is blank)
     *
     * @throws  KursoException
     *
     * @since   1.0.0
     */
    public function evaluateVariables(string $json): array
    {
        if (trim($json) === '') {
            return [];
        }

        $evaluated = str_contains($json, '{{') || str_contains($json, '{%') ? $this->twig->renderVariables($json) : $json;

        if (trim($evaluated) === '') {
            return [];
        }

        try {
            $decoded = json_decode($evaluated, true, 64, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (\JsonException $e) {
            throw new KursoException(Text::sprintf('COM_KURSO_ERROR_VARIABLES_JSON', $e->getMessage()), 0, $e);
        }

        if (!\is_array($decoded) || ($decoded !== [] && array_is_list($decoded))) {
            throw new KursoException(Text::_('COM_KURSO_ERROR_VARIABLES_OBJECT'));
        }

        return $decoded;
    }

    /**
     * Executes a GraphQL query with the stored credentials.
     *
     * @param   string  $query          The GraphQL query
     * @param   string  $variablesJson  The variables JSON (may contain Twig expressions)
     *
     * @return  array  The `data` object of the response
     *
     * @throws  KursoException
     *
     * @since   1.0.0
     */
    public function execute(string $query, string $variablesJson = ''): array
    {
        if (trim($query) === '') {
            throw new KursoException(Text::_('COM_KURSO_ERROR_QUERY_EMPTY'));
        }

        self::assertReadOnly($query);

        $variables = $this->evaluateVariables($variablesJson);

        if ($this->settings->isPasswordUnreadable()) {
            throw new KursoException(Text::_('COM_KURSO_ERROR_PASSWORD_UNREADABLE'));
        }

        if (!$this->settings->isConfigured()) {
            throw new KursoException(Text::_('COM_KURSO_ERROR_NOT_CONFIGURED'));
        }

        return $this->request(
            $this->settings->getUrl(),
            $this->settings->getUsername(),
            $this->settings->getPassword(),
            $query,
            $variables
        );
    }

    /**
     * Rejects GraphQL documents that contain a mutation or subscription operation.
     *
     * The extension only reads data. Blocking mutations makes sure that nobody who may edit queries can
     * change or delete data in KURSO with the stored credentials. The check scans the top level of the
     * document (outside of selection sets, strings and comments) for the operation keywords.
     *
     * @param   string  $document  The GraphQL document
     *
     * @return  void
     *
     * @throws  KursoException
     *
     * @since   1.0.0
     */
    public static function assertReadOnly(string $document): void
    {
        $length = \strlen($document);
        $depth  = 0;
        $i      = 0;

        while ($i < $length) {
            $char = $document[$i];

            if ($char === '#') {
                $end = strpos($document, "\n", $i);
                $i   = $end === false ? $length : $end + 1;

                continue;
            }

            if ($char === '"') {
                if (substr($document, $i, 3) === '"""') {
                    $end = strpos($document, '"""', $i + 3);
                    $i   = $end === false ? $length : $end + 3;

                    continue;
                }

                for ($i++; $i < $length && $document[$i] !== '"'; $i++) {
                    if ($document[$i] === '\\') {
                        $i++;
                    }
                }

                $i++;

                continue;
            }

            if ($char === '{') {
                $depth++;
                $i++;

                continue;
            }

            if ($char === '}') {
                $depth = max(0, $depth - 1);
                $i++;

                continue;
            }

            if (($char === '_' || ctype_alpha($char) || $char === '$') && preg_match('/\$?[_A-Za-z][_0-9A-Za-z]*/A', $document, $match, 0, $i)) {
                if ($depth === 0 && ($match[0] === 'mutation' || $match[0] === 'subscription')) {
                    throw new KursoException(Text::_('COM_KURSO_ERROR_READ_ONLY'));
                }

                $i += \strlen($match[0]);

                continue;
            }

            $i++;
        }
    }

    /**
     * Tests a connection by sending a minimal query.
     *
     * @param   string  $url       The GraphQL endpoint URL
     * @param   string  $username  The username
     * @param   string  $password  The password
     *
     * @return  void
     *
     * @throws  KursoException  If the connection does not work
     *
     * @since   1.0.0
     */
    public function testConnection(string $url, string $username, string $password): void
    {
        ConnectionSettings::assertValidUrl($url);

        if ($username === '' || $password === '') {
            throw new KursoException(Text::_('COM_KURSO_ERROR_NOT_CONFIGURED'));
        }

        $this->request($url, $username, $password, '{ __typename }', []);
    }

    /**
     * Sends a GraphQL request.
     *
     * @param   string  $url        The endpoint
     * @param   string  $username   The username
     * @param   string  $password   The password
     * @param   string  $query      The query
     * @param   array   $variables  The variables
     *
     * @return  array  The `data` object
     *
     * @throws  KursoException
     *
     * @since   1.0.0
     */
    private function request(string $url, string $username, string $password, string $query, array $variables): array
    {
        $payload = ['query' => $query];

        if ($variables !== []) {
            $payload['variables'] = $variables;
        }

        try {
            $body = json_encode($payload, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES);
        } catch (\JsonException $e) {
            throw new KursoException(Text::sprintf('COM_KURSO_ERROR_VARIABLES_JSON', $e->getMessage()), 0, $e);
        }

        try {
            // Never follow redirects: the Authorization header must not be sent to another host. Only the cURL and
            // stream transports honour "follow_location", so the socket transport is excluded.
            $http = HttpFactory::getHttp(
                [
                    'follow_location' => false,
                    'userAgent'       => 'KURSO-for-Joomla/' . $this->version,
                ],
                ['curl', 'stream']
            );

            $response = $http->post(
                $url,
                $body,
                [
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode($username . ':' . $password),
                ],
                max(3, $this->timeout)
            );
        } catch (\Throwable $e) {
            throw new KursoException(Text::sprintf('COM_KURSO_ERROR_HTTP_TRANSPORT', self::clean($e->getMessage())), 0, $e);
        }

        $status  = $response->getStatusCode();
        $content = (string) $response->getBody();

        if (\strlen($content) > self::MAX_RESPONSE_BYTES) {
            throw new KursoException(Text::sprintf('COM_KURSO_ERROR_RESPONSE_TOO_LARGE', (int) (self::MAX_RESPONSE_BYTES / 1048576)));
        }

        if ($status === 401 || $status === 403) {
            throw new KursoException(Text::sprintf('COM_KURSO_ERROR_HTTP_AUTH', $status));
        }

        if ($status >= 300 && $status < 400) {
            throw new KursoException(Text::sprintf('COM_KURSO_ERROR_HTTP_REDIRECT', $status));
        }

        try {
            $decoded = json_decode($content, true, 512, \JSON_THROW_ON_ERROR | \JSON_BIGINT_AS_STRING);
        } catch (\JsonException) {
            $decoded = null;
        }

        // GraphQL servers may answer errors with status 400/500 and a JSON body, so errors are checked first.
        if (\is_array($decoded) && !empty($decoded['errors']) && \is_array($decoded['errors'])) {
            $messages = [];

            foreach (\array_slice($decoded['errors'], 0, 3) as $error) {
                $messages[] = self::clean((string) (\is_array($error) ? ($error['message'] ?? '') : $error));
            }

            throw new KursoException(Text::sprintf('COM_KURSO_ERROR_GRAPHQL', implode(' | ', array_filter($messages)) ?: 'unknown'));
        }

        if ($status !== 200) {
            throw new KursoException(Text::sprintf('COM_KURSO_ERROR_HTTP_STATUS', $status, self::clean($content)));
        }

        if (!\is_array($decoded)) {
            throw new KursoException(Text::sprintf('COM_KURSO_ERROR_RESPONSE_NOT_JSON', self::clean($content)));
        }

        if (!isset($decoded['data']) || !\is_array($decoded['data'])) {
            throw new KursoException(Text::_('COM_KURSO_ERROR_RESPONSE_NO_DATA'));
        }

        return $decoded['data'];
    }

    /**
     * Reduces an external message to a short, tag-free excerpt.
     *
     * @param   string  $text  The text
     *
     * @return  string
     *
     * @since   1.0.0
     */
    private static function clean(string $text): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');

        return mb_strlen($text) > 200 ? mb_substr($text, 0, 200) . '…' : $text;
    }
}
