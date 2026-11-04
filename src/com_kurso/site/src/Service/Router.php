<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Site\Service;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Categories\CategoryFactoryInterface;
use Joomla\CMS\Component\Router\RouterView;
use Joomla\CMS\Component\Router\RouterViewConfiguration;
use Joomla\CMS\Component\Router\Rules\MenuRules;
use Joomla\CMS\Component\Router\Rules\NomenuRules;
use Joomla\CMS\Component\Router\Rules\StandardRules;
use Joomla\CMS\Factory;
use Joomla\CMS\Menu\AbstractMenu;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Router for the site part: /<menu-item> or /component/kurso/query/<alias>.
 *
 * @since  1.0.0
 */
class Router extends RouterView
{
    /**
     * @var  DatabaseInterface
     */
    private DatabaseInterface $db;

    /**
     * @param   SiteApplication                $app              The application object
     * @param   AbstractMenu                   $menu             The menu object to work with
     * @param   CategoryFactoryInterface|null  $categoryFactory  Not used (KURSO queries have no categories)
     * @param   DatabaseInterface|null         $db               The database
     *
     * @since   1.0.0
     */
    public function __construct(SiteApplication $app, AbstractMenu $menu, ?CategoryFactoryInterface $categoryFactory = null, ?DatabaseInterface $db = null)
    {
        $this->db = $db ?? Factory::getContainer()->get(DatabaseInterface::class);

        $query = new RouterViewConfiguration('query');
        $query->setKey('id');
        $this->registerView($query);

        parent::__construct($app, $menu);

        $this->attachRule(new MenuRules($this));
        $this->attachRule(new StandardRules($this));
        $this->attachRule(new NomenuRules($this));
    }

    /**
     * @param   string  $id     The id of the query
     * @param   array   $query  The request that is built right now
     *
     * @return  array  The segments of this item
     *
     * @since   1.0.0
     */
    public function getQuerySegment($id, $query)
    {
        $id     = (int) $id;
        $dbq    = $this->db->createQuery()
            ->select($this->db->quoteName('alias'))
            ->from($this->db->quoteName('#__kurso_queries'))
            ->where($this->db->quoteName('id') . ' = :id')
            ->bind(':id', $id, ParameterType::INTEGER);
        $alias  = (string) $this->db->setQuery($dbq)->loadResult();

        return [$id => $alias !== '' ? $alias : (string) $id];
    }

    /**
     * @param   string  $segment  Segment of the query to retrieve the id for
     * @param   array   $query    The request that is parsed right now
     *
     * @return  int|false  The id of this item or false
     *
     * @since   1.0.0
     */
    public function getQueryId($segment, $query)
    {
        $dbq = $this->db->createQuery()
            ->select($this->db->quoteName('id'))
            ->from($this->db->quoteName('#__kurso_queries'))
            ->where($this->db->quoteName('alias') . ' = :alias')
            ->bind(':alias', $segment);

        $id = (int) $this->db->setQuery($dbq)->loadResult();

        if ($id === 0 && ctype_digit((string) $segment)) {
            $id = (int) $segment;
        }

        return $id ?: false;
    }
}
