<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Administrator\Controller;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\Utilities\ArrayHelper;
use Kurso\Component\Kurso\Administrator\Model\QueryModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Controller for list actions on queries (publish, unpublish, delete, check-in, refresh).
 *
 * @since  1.0.0
 */
class QueriesController extends AdminController
{
    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $text_prefix = 'COM_KURSO_QUERIES';

    /**
     * @param   string  $name    The model name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  The array of possible config values. Optional.
     *
     * @return  QueryModel
     *
     * @since   1.0.0
     */
    public function getModel($name = 'Query', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }

    /**
     * Fetches the data of the selected queries from the KURSO API now.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function refresh(): void
    {
        $this->checkToken();

        $this->setRedirect(Route::_('index.php?option=com_kurso&view=queries', false));

        if (!$this->app->getIdentity()->authorise('core.edit', 'com_kurso')) {
            $this->setMessage(Text::_('JLIB_APPLICATION_ERROR_EDIT_NOT_PERMITTED'), 'error');

            return;
        }

        $ids = array_filter(ArrayHelper::toInteger((array) $this->input->get('cid', [], 'array')));

        if ($ids === []) {
            $this->setMessage(Text::_('COM_KURSO_QUERIES_NO_ITEM_SELECTED'), 'warning');

            return;
        }

        $results = $this->getModel()->refresh($ids);
        $failed  = array_filter($results, static fn (?string $error): bool => $error !== null);
        $success = \count($results) - \count($failed);

        if ($success > 0) {
            $this->app->enqueueMessage(Text::plural('COM_KURSO_QUERIES_N_ITEMS_REFRESHED', $success), 'success');
        }

        // Messages are rendered as HTML; titles and API errors are text.
        foreach ($failed as $error) {
            $this->app->enqueueMessage(Text::sprintf('COM_KURSO_REFRESH_FAILED', htmlspecialchars($error, ENT_QUOTES, 'UTF-8')), 'error');
        }
    }
}
