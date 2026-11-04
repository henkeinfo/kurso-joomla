<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Site\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Default controller of the site part.
 *
 * @since  1.0.0
 */
class DisplayController extends BaseController
{
    /**
     * The default view.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $default_view = 'query';

    /**
     * @param   array                     $config   An optional associative array of configuration settings.
     * @param   MVCFactoryInterface|null  $factory  The factory.
     * @param   mixed                     $app      The application instance.
     * @param   mixed                     $input    The input instance.
     *
     * @since   1.0.0
     */
    public function __construct($config = [], ?MVCFactoryInterface $factory = null, $app = null, $input = null)
    {
        $input = $input ?? Factory::getApplication()->getInput();

        // The editor button dialog on the frontend uses the administrator view (access checked by the dispatcher).
        if ($input->getCmd('view') === 'select') {
            $config['base_path'] = JPATH_ADMINISTRATOR . '/components/com_kurso';
        }

        parent::__construct($config, $factory, $app, $input);
    }

    /**
     * @param   boolean  $cachable   If true, the view output will be cached.
     * @param   array    $urlparams  An array of safe URL parameters and their variable types.
     *
     * @return  static
     *
     * @since   1.0.0
     */
    public function display($cachable = false, $urlparams = [])
    {
        // Not cachable: the output depends on the refresh state of the data and on the user (error messages).
        return parent::display(false, $urlparams);
    }
}
