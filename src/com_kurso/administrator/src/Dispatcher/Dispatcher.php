<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Administrator\Dispatcher;

use Joomla\CMS\Access\Exception\NotAllowed;
use Joomla\CMS\Dispatcher\ComponentDispatcher;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Dispatcher of the administrator part.
 *
 * @since  1.0.0
 */
class Dispatcher extends ComponentDispatcher
{
    /**
     * Checks access. The "select" dialog of the editor button is also available to users who may edit
     * content but cannot manage KURSO, so they can embed queries into articles.
     *
     * @return  void
     *
     * @throws  NotAllowed
     *
     * @since   1.0.0
     */
    protected function checkAccess()
    {
        if ($this->input->getCmd('view') === 'select' && !$this->input->getCmd('task')) {
            if (!Session::checkToken('get') || !self::canUseSelectDialog($this->app->getIdentity())) {
                throw new NotAllowed(Text::_('JERROR_ALERTNOAUTHOR'), 403);
            }

            return;
        }

        parent::checkAccess();
    }

    /**
     * @param   \Joomla\CMS\User\User|null  $user  The user
     *
     * @return  boolean  True if the user may open the "insert KURSO query" dialog
     *
     * @since   1.0.0
     */
    public static function canUseSelectDialog($user): bool
    {
        if ($user === null || $user->guest) {
            return false;
        }

        return $user->authorise('core.manage', 'com_kurso')
            || $user->authorise('core.create', 'com_content')
            || $user->authorise('core.edit', 'com_content')
            || $user->authorise('core.edit.own', 'com_content')
            || $user->authorise('core.create', 'com_modules')
            || $user->authorise('core.edit', 'com_modules');
    }
}
