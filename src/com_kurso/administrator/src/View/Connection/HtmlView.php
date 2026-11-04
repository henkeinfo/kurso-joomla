<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Administrator\View\Connection;

use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Kurso\Component\Kurso\Administrator\Model\ConnectionModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Connection settings form.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var  Form
     */
    protected $form;

    /**
     * @var  array{configured: bool, hasPassword: bool, unreadable: bool}
     */
    protected array $status = [];

    /**
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function display($tpl = null): void
    {
        /** @var ConnectionModel $model */
        $model = $this->getModel();

        $this->form   = $model->getForm();
        $this->status = $model->getStatus();

        if ($errors = $model->getErrors()) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * @return  void
     *
     * @since   1.0.0
     */
    protected function addToolbar(): void
    {
        $toolbar = $this->getDocument()->getToolbar();
        $canDo   = ContentHelper::getActions('com_kurso');

        ToolbarHelper::title(Text::_('COM_KURSO_CONNECTION_TITLE'), 'plug');

        $toolbar->apply('connection.apply');
        $toolbar->save('connection.save');
        $toolbar->cancel('connection.cancel', 'JTOOLBAR_CLOSE');

        if ($canDo->get('core.admin') || $canDo->get('core.options')) {
            $toolbar->preferences('com_kurso');
        }
    }
}
