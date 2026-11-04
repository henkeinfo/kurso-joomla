<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Administrator\Field;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Kurso\Component\Kurso\Administrator\Extension\KursoComponent;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Select list of the configured KURSO queries (used by the module and the menu item).
 *
 * Usage: <field type="kursoquery" addfieldprefix="Kurso\Component\Kurso\Administrator\Field" … />
 *
 * @since  1.0.0
 */
class KursoqueryField extends ListField
{
    /**
     * The form field type.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $type = 'Kursoquery';

    /**
     * @return  object[]  The field option objects.
     *
     * @since   1.0.0
     */
    protected function getOptions()
    {
        $options = parent::getOptions();

        try {
            /** @var KursoComponent $kurso */
            $kurso   = Factory::getApplication()->bootComponent('com_kurso');
            $queries = $kurso->getRepository()->getList();
        } catch (\Throwable) {
            $queries = [];
        }

        foreach ($queries as $query) {
            $text = $query->title . ' (' . $query->alias . ')';

            if ((int) $query->state !== 1) {
                $text .= ' – ' . Text::_('JUNPUBLISHED');
            }

            $options[] = HTMLHelper::_('select.option', (string) $query->id, $text);
        }

        return $options;
    }
}
