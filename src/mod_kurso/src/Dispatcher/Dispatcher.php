<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  mod_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Module\Kurso\Site\Dispatcher;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Kurso\Component\Kurso\Administrator\Extension\KursoComponent;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Dispatcher of the KURSO module: renders the selected query.
 *
 * @since  1.0.0
 */
class Dispatcher extends AbstractModuleDispatcher
{
    /**
     * @return  array|false  The layout data, or false to not render the module
     *
     * @since   1.0.0
     */
    protected function getLayoutData()
    {
        $data = parent::getLayoutData();

        if (!ComponentHelper::isEnabled('com_kurso')) {
            return false;
        }

        /** @var \Joomla\Registry\Registry $params */
        $params = $data['params'];

        /** @var KursoComponent $kurso */
        $kurso = $this->getApplication()->bootComponent('com_kurso');

        $override = trim((string) $params->get('template_override', ''));

        $data['kursoHtml'] = $kurso->getRenderer()->renderById(
            (int) $params->get('query_id', 0),
            (string) $params->get('css_class', ''),
            $override !== '' ? $override : null
        );

        // Nothing to show (e.g. an error that is hidden from visitors): suppress the empty module.
        if ($data['kursoHtml'] === '') {
            return false;
        }

        return $data;
    }
}
