<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Site\View\Query;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\Registry\Registry;
use Kurso\Component\Kurso\Administrator\Extension\KursoComponent;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Displays a KURSO query on its own page (menu item type "KURSO » Display Query").
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var  Registry  The menu item parameters merged with the component options
     */
    protected $params;

    /**
     * @var  string  The rendered query
     */
    protected string $html = '';

    /**
     * @var  string  Page class suffix
     */
    protected string $pageclass_sfx = '';

    /**
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function display($tpl = null): void
    {
        $app = Factory::getApplication();

        /** @var \Joomla\CMS\Application\SiteApplication $app */
        $this->params        = $app->getParams();
        $this->pageclass_sfx = htmlspecialchars((string) $this->params->get('pageclass_sfx', ''), ENT_COMPAT, 'UTF-8');

        /** @var KursoComponent $kurso */
        $kurso = $app->bootComponent('com_kurso');

        $this->html = $kurso->getRenderer()->renderById(
            $app->getInput()->getInt('id'),
            (string) $this->params->get('css_class', '')
        );

        $this->prepareDocument();

        parent::display($tpl);
    }

    /**
     * Sets the page title and meta data from the menu item.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    protected function prepareDocument(): void
    {
        $this->setDocumentTitle($this->params->get('page_title', ''));

        if ($this->params->get('menu-meta_description')) {
            $this->getDocument()->setDescription($this->params->get('menu-meta_description'));
        }

        if ($this->params->get('robots')) {
            $this->getDocument()->setMetaData('robots', $this->params->get('robots'));
        }
    }
}
