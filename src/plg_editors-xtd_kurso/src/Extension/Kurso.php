<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  plg_editors-xtd_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Plugin\EditorsXtd\Kurso\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Editor\Button\Button;
use Joomla\CMS\Event\Editor\EditorButtonsSetupEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;
use Joomla\Event\SubscriberInterface;
use Kurso\Component\Kurso\Administrator\Dispatcher\Dispatcher as KursoDispatcher;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Editor button "KURSO": opens a dialog listing the KURSO queries and inserts {kurso query="…"}.
 *
 * @since  1.0.0
 */
final class Kurso extends CMSPlugin implements SubscriberInterface
{
    /**
     * Load the language file on instantiation.
     *
     * @var    boolean
     * @since  1.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * @return  array
     *
     * @since   1.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return ['onEditorButtonsSetup' => 'onEditorButtonsSetup'];
    }

    /**
     * Adds the button to the editor.
     *
     * @param   EditorButtonsSetupEvent  $event  The event
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function onEditorButtonsSetup(EditorButtonsSetupEvent $event): void
    {
        if (\in_array($this->_name, $event->getDisabledButtons(), true) || !ComponentHelper::isEnabled('com_kurso')) {
            return;
        }

        if (!KursoDispatcher::canUseSelectDialog($this->getApplication()->getIdentity())) {
            return;
        }

        $link = 'index.php?option=com_kurso&view=select&tmpl=component&'
            . Session::getFormToken() . '=1&editor=' . rawurlencode($event->getEditorId());

        $event->getButtonsRegistry()->add(new Button(
            $this->_name,
            [
                'action'  => 'modal',
                'link'    => $link,
                'text'    => Text::_('PLG_EDITORS-XTD_KURSO_BUTTON'),
                'icon'    => 'calendar',
                'iconSVG' => '<svg viewBox="0 0 448 512" width="24" height="24" aria-hidden="true"><path d="M152 24c0-13.3-10.7-24-24-24s-24 '
                    . '10.7-24 24v40H64C28.7 64 0 92.7 0 128v320c0 35.3 28.7 64 64 64h320c35.3 0 64-28.7 64-64V128c0-35.3-28.7-64-64-64h-40V24'
                    . 'c0-13.3-10.7-24-24-24s-24 10.7-24 24v40H152V24zM48 192h352v256c0 8.8-7.2 16-16 16H64c-8.8 0-16-7.2-16-16V192z"/></svg>',
                // The whole plugin name, needed by Joomla for backward compatibility.
                'name'    => $this->_type . '_' . $this->_name,
            ]
        ));
    }
}
