<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Administrator\Controller;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\CMS\Response\JsonResponse;
use Joomla\CMS\Router\Route;
use Joomla\Input\Input;
use Kurso\Component\Kurso\Administrator\Exception\KursoException;
use Kurso\Component\Kurso\Administrator\Model\ConnectionModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Controller for the KURSO API connection settings.
 *
 * @since  1.0.0
 */
class ConnectionController extends BaseController
{
    use JsonResponseTrait;

    private const STATE_KEY = 'com_kurso.edit.connection.data';

    /**
     * @param   array                         $config   An optional associative array of configuration settings.
     * @param   MVCFactoryInterface|null      $factory  The factory.
     * @param   CMSApplicationInterface|null  $app      The Application for the dispatcher
     * @param   Input|null                    $input    Input
     *
     * @since   1.0.0
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null, $app = null, $input = null)
    {
        parent::__construct($config, $factory, $app, $input);

        // "apply" saves and stays in the form, "save" saves and closes it.
        $this->registerTask('apply', 'save');
    }

    /**
     * Saves the connection settings (tasks "apply" and "save").
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function save(): void
    {
        $this->checkToken();

        $redirect = Route::_('index.php?option=com_kurso&view=connection', false);

        if (!$this->canEdit()) {
            $this->setRedirect($redirect, Text::_('JERROR_ALERTNOAUTHOR'), 'error');

            return;
        }

        /** @var ConnectionModel $model */
        $model = $this->getModel('Connection', 'Administrator');
        $data  = (array) $this->input->post->get('jform', [], 'array');
        $form  = $model->getForm($data, false);
        $valid = $form ? $model->validate($form, $data) : false;

        if ($valid === false || !$model->save($valid)) {
            foreach ($model->getErrors() as $error) {
                $this->app->enqueueMessage($error instanceof \Throwable ? $error->getMessage() : (string) $error, 'error');
            }

            unset($data['password']);
            $this->app->setUserState(self::STATE_KEY, $data);
            $this->setRedirect($redirect);

            return;
        }

        $this->app->setUserState(self::STATE_KEY, null);

        $status = $model->getStatus();

        $this->setRedirect(
            $this->getTask() === 'save' ? Route::_('index.php?option=com_kurso&view=queries', false) : $redirect,
            Text::_('COM_KURSO_CONNECTION_SAVED') . ($status['configured'] ? '' : ' ' . Text::_('COM_KURSO_CONNECTION_INCOMPLETE')),
            $status['configured'] ? 'success' : 'warning'
        );
    }

    /**
     * Tests the connection with the values currently entered in the form (AJAX, returns JSON).
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function test(): void
    {
        if (!$this->checkToken('post', false)) {
            $this->sendJson(new JsonResponse(null, Text::_('JINVALID_TOKEN_NOTICE'), true), 403);

            return;
        }

        if (!$this->canEdit()) {
            $this->sendJson(new JsonResponse(null, Text::_('JERROR_ALERTNOAUTHOR'), true), 403);

            return;
        }

        /** @var ConnectionModel $model */
        $model = $this->getModel('Connection', 'Administrator');
        $data  = (array) $this->input->post->get('jform', [], 'array');

        try {
            $model->test($data);
        } catch (KursoException $e) {
            $this->sendJson(new JsonResponse(null, Text::sprintf('COM_KURSO_CONNECTION_TEST_FAILED', $e->getMessage()), true));

            return;
        }

        $this->sendJson(new JsonResponse(null, Text::_('COM_KURSO_CONNECTION_TEST_SUCCESS')));
    }

    /**
     * Leaves the connection form.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function cancel(): void
    {
        $this->checkToken();

        $this->app->setUserState(self::STATE_KEY, null);
        $this->setRedirect(Route::_('index.php?option=com_kurso&view=queries', false));
    }

    /**
     * @return  boolean  True if the user may change the connection settings
     *
     * @since   1.0.0
     */
    private function canEdit(): bool
    {
        return $this->app->getIdentity()->authorise('kurso.connection', 'com_kurso');
    }
}
