<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Administrator\View\Queries;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Pagination\Pagination;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\Registry\Registry;
use Kurso\Component\Kurso\Administrator\Extension\KursoComponent;
use Kurso\Component\Kurso\Administrator\Model\QueriesModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * List of queries.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var  array
     */
    protected $items = [];

    /**
     * @var  Pagination
     */
    protected $pagination;

    /**
     * @var  Registry
     */
    protected $state;

    /**
     * @var  Form|null
     */
    public $filterForm;

    /**
     * @var  array
     */
    public $activeFilters = [];

    /**
     * @var  boolean  Whether the KURSO connection is configured
     */
    protected bool $connectionConfigured = false;

    /**
     * @var  boolean  Whether the current user may edit the connection settings
     */
    protected bool $canEditConnection = false;

    /**
     * @var  boolean
     */
    private bool $isEmptyState = false;

    /**
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function display($tpl = null): void
    {
        /** @var QueriesModel $model */
        $model = $this->getModel();

        $this->items         = $model->getItems();
        $this->pagination    = $model->getPagination();
        $this->state         = $model->getState();
        $this->filterForm    = $model->getFilterForm();
        $this->activeFilters = $model->getActiveFilters();

        if ($errors = $model->getErrors()) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        /** @var KursoComponent $kurso */
        $kurso = Factory::getApplication()->bootComponent('com_kurso');

        $this->connectionConfigured = $kurso->getSettings()->isConfigured();
        $this->canEditConnection    = $this->getCurrentUser()->authorise('kurso.connection', 'com_kurso');

        if (!\count($this->items) && $this->isEmptyState = $model->getIsEmptyState()) {
            $this->setLayout('emptystate');
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
        $canDo   = ContentHelper::getActions('com_kurso');
        $toolbar = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_('COM_KURSO_QUERIES_TITLE'), 'calendar');

        if ($canDo->get('core.create')) {
            $toolbar->addNew('query.add');
        }

        if (!$this->isEmptyState && ($canDo->get('core.edit.state') || $canDo->get('core.edit'))) {
            $dropdown = $toolbar->dropdownButton('status-group', 'JTOOLBAR_CHANGE_STATUS')
                ->toggleSplit(false)
                ->icon('icon-ellipsis-h')
                ->buttonClass('btn btn-action')
                ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();

            if ($canDo->get('core.edit')) {
                $childBar->standardButton('refresh', 'COM_KURSO_TOOLBAR_REFRESH', 'queries.refresh')
                    ->icon('icon-refresh')
                    ->listCheck(true);
            }

            if ($canDo->get('core.edit.state')) {
                $childBar->publish('queries.publish')->listCheck(true);
                $childBar->unpublish('queries.unpublish')->listCheck(true);
                $childBar->checkin('queries.checkin')->listCheck(true);
            }
        }

        if (!$this->isEmptyState && $canDo->get('core.delete')) {
            $toolbar->delete('queries.delete')
                ->message('COM_KURSO_CONFIRM_DELETE')
                ->listCheck(true);
        }

        if ($this->canEditConnection) {
            $toolbar->link('COM_KURSO_TOOLBAR_CONNECTION', 'index.php?option=com_kurso&view=connection')
                ->icon('icon-plug');
        }

        if ($canDo->get('core.admin') || $canDo->get('core.options')) {
            $toolbar->preferences('com_kurso');
        }
    }
}
