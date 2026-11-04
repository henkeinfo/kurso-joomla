<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Kurso\Component\Kurso\Administrator\Extension\KursoComponent;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Model for the editor button dialog: the published queries that can be embedded.
 *
 * @since  1.0.0
 */
class SelectModel extends BaseDatabaseModel
{
    /**
     * @return  object[]  Rows with id, title and alias
     *
     * @since   1.0.0
     */
    public function getItems(): array
    {
        /** @var KursoComponent $kurso */
        $kurso = Factory::getApplication()->bootComponent('com_kurso');

        return $kurso->getRepository()->getList(true);
    }
}
