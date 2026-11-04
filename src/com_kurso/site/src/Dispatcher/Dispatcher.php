<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Site\Dispatcher;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Dispatcher\ComponentDispatcher;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;
use Kurso\Component\Kurso\Administrator\Dispatcher\Dispatcher as AdministratorDispatcher;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Dispatcher of the site part.
 *
 * @since  1.0.0
 */
class Dispatcher extends ComponentDispatcher
{
    /**
     * Loads the site language and, for the editor button dialog, the administrator language.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function loadLanguage()
    {
        parent::loadLanguage();

        if ($this->input->getCmd('view') === 'select') {
            $this->app->getLanguage()->load('com_kurso', JPATH_ADMINISTRATOR)
                || $this->app->getLanguage()->load('com_kurso', JPATH_ADMINISTRATOR . '/components/com_kurso');
        }
    }

    /**
     * The editor button dialog (view=select) is only available to logged-in content editors.
     *
     * @return  void
     *
     * @throws  NotAllowed
     *
     * @since   1.0.0
     */
    protected function checkAccess()
    {
        parent::checkAccess();

        if ($this->input->getCmd('view') === 'select') {
            if (!Session::checkToken('get') || !AdministratorDispatcher::canUseSelectDialog($this->app->getIdentity())) {
                throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }
        }
    }
}
