<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Administrator\View\Select;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Kurso\Component\Kurso\Administrator\Model\SelectModel;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Dialog of the "KURSO" editor button: lists the published queries and inserts the embed tag.
 *
 * @since  1.0.0
 */
class HtmlView extends BaseHtmlView
{
    /**
     * @var  object[]
     */
    protected array $items = [];

    /**
     * @param   string  $tpl  The name of the template file to parse
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function display($tpl = null): void
    {
        /** @var SelectModel $model */
        $model       = $this->getModel();
        $this->items = $model->getItems();

        parent::display($tpl);
    }
}
