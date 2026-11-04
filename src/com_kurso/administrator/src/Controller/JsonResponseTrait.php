<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Administrator\Controller;

use Joomla\CMS\Response\JsonResponse;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Sends JSON responses from controller tasks used by the administrator JavaScript.
 *
 * @since  1.0.0
 */
trait JsonResponseTrait
{
    /**
     * Sends a JSON response and ends the request.
     *
     * @param   JsonResponse  $response  The response
     * @param   int           $status    The HTTP status
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function sendJson(JsonResponse $response, int $status = 200): void
    {
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $this->app->setHeader('status', (string) $status, true);
        $this->app->setHeader('Cache-Control', 'no-store', true);
        $this->app->setHeader('X-Content-Type-Options', 'nosniff', true);
        $this->app->sendHeaders();

        echo $response;

        $this->app->close();
    }
}
