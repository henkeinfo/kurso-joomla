<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Administrator\Exception;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Exception for expected, user-facing KURSO errors (configuration, API, template problems).
 *
 * The message is already translated and safe to show to administrators. It never contains credentials.
 *
 * @since  1.0.0
 */
class KursoException extends \RuntimeException
{
}
