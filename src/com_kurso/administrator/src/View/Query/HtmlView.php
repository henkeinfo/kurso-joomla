<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Administrator\View\Query;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Kurso\Component\Kurso\Administrator\Extension\KursoComponent;
use Kurso\Component\Kurso\Administrator\Model\QueryModel;
use Kurso\Component\Kurso\Administrator\Service\CacheEntry;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Edit form of a query.
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
     * @var  object
     */
    protected $item;

    /**
     * @var  CacheEntry|null  Fetch status of a saved query
     */
    protected ?CacheEntry $cache = null;

    /**
     * @var  string  The GraphQL endpoint, used by "Open in GraphiQL"
     */
    protected string $graphqlUrl = '';

    /**
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function display($tpl = null): void
    {
        /** @var QueryModel $model */
        $model = $this->getModel();

        $this->form = $model->getForm();
        $this->item = $model->getItem();

        if ($errors = $model->getErrors()) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        if (!empty($this->item->id)) {
            $this->cache = $model->getCacheEntry((int) $this->item->id);
        }

        /** @var KursoComponent $kurso */
        $kurso            = Factory::getApplication()->bootComponent('com_kurso');
        $this->graphqlUrl = $kurso->getSettings()->getUrl();

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
        Factory::getApplication()->getInput()->set('hidemainmenu', true);

        $user       = $this->getCurrentUser();
        $isNew      = empty($this->item->id);
        $checkedOut = !empty($this->item->checked_out) && (int) $this->item->checked_out !== (int) $user->id;
        $canDo      = ContentHelper::getActions('com_kurso');
        $toolbar    = $this->getDocument()->getToolbar();

        ToolbarHelper::title(Text::_($isNew ? 'COM_KURSO_QUERY_NEW' : 'COM_KURSO_QUERY_EDIT'), 'calendar');

        $canSave = !$checkedOut && ($isNew ? $canDo->get('core.create') : $canDo->get('core.edit'));

        if ($canSave) {
            $toolbar->apply('query.apply');

            $saveGroup = $toolbar->dropdownButton('save-group');
            $childBar  = $saveGroup->getChildToolbar();

            $childBar->save('query.save');

            if ($canDo->get('core.create')) {
                $childBar->save2new('query.save2new');

                if (!$isNew) {
                    $childBar->save2copy('query.save2copy');
                }
            }
        }

        $toolbar->cancel('query.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
    }
}
