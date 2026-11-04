<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Administrator\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Component\Router\RouterServiceInterface;
use Joomla\CMS\Component\Router\RouterServiceTrait;
use Joomla\CMS\Extension\MVCComponent;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Registry\Registry;
use Kurso\Component\Kurso\Administrator\Service\ConnectionSettings;
use Kurso\Component\Kurso\Administrator\Service\DataRefresher;
use Kurso\Component\Kurso\Administrator\Service\GraphQLClient;
use Kurso\Component\Kurso\Administrator\Service\OutputRenderer;
use Kurso\Component\Kurso\Administrator\Service\QueryRepository;
use Kurso\Component\Kurso\Administrator\Service\Secret;
use Kurso\Component\Kurso\Administrator\Service\TwigFactory;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * The KURSO component.
 *
 * Besides the MVC parts it provides the KURSO services to the module and the plugins:
 *
 *     $kurso = Factory::getApplication()->bootComponent('com_kurso');
 *     echo $kurso->getRenderer()->renderByAlias('current-courses');
 *
 * @since  1.0.0
 */
class KursoComponent extends MVCComponent implements RouterServiceInterface, DatabaseAwareInterface
{
    use RouterServiceTrait;
    use DatabaseAwareTrait;

    /**
     * The extension version, used for the HTTP User-Agent.
     */
    public const VERSION = '1.0.0';

    private ?Registry $params                  = null;
    private ?ConnectionSettings $settings      = null;
    private ?QueryRepository $repository       = null;
    private ?TwigFactory $twig                 = null;
    private ?GraphQLClient $client             = null;
    private ?DataRefresher $refresher          = null;
    private ?OutputRenderer $renderer          = null;
    private bool $languageLoaded               = false;

    /**
     * @return  Registry  The component options
     *
     * @since   1.0.0
     */
    public function getParams(): Registry
    {
        return $this->params ??= ComponentHelper::getParams('com_kurso');
    }

    /**
     * @return  ConnectionSettings
     *
     * @since   1.0.0
     */
    public function getSettings(): ConnectionSettings
    {
        $this->loadLanguage();

        return $this->settings ??= new ConnectionSettings(
            $this->getDatabase(),
            new Secret((string) Factory::getApplication()->get('secret', ''))
        );
    }

    /**
     * @return  QueryRepository
     *
     * @since   1.0.0
     */
    public function getRepository(): QueryRepository
    {
        $this->loadLanguage();

        return $this->repository ??= new QueryRepository($this->getDatabase());
    }

    /**
     * @return  TwigFactory
     *
     * @since   1.0.0
     */
    public function getTwig(): TwigFactory
    {
        $this->loadLanguage();

        return $this->twig ??= new TwigFactory((string) Factory::getApplication()->get('offset', 'UTC'));
    }

    /**
     * @return  GraphQLClient
     *
     * @since   1.0.0
     */
    public function getClient(): GraphQLClient
    {
        $this->loadLanguage();

        $timeout = (int) $this->getParams()->get('http_timeout', 15);

        return $this->client ??= new GraphQLClient($this->getSettings(), $this->getTwig(), min(120, max(3, $timeout)), self::VERSION);
    }

    /**
     * @return  DataRefresher
     *
     * @since   1.0.0
     */
    public function getRefresher(): DataRefresher
    {
        return $this->refresher ??= new DataRefresher($this->getRepository(), $this->getClient());
    }

    /**
     * @return  OutputRenderer
     *
     * @since   1.0.0
     */
    public function getRenderer(): OutputRenderer
    {
        $this->loadLanguage();

        return $this->renderer ??= new OutputRenderer(
            $this->getRepository(),
            $this->getRefresher(),
            $this->getTwig(),
            $this->getParams(),
            Factory::getApplication()
        );
    }

    /**
     * Loads the component language (it contains the messages of the services), also when the services are
     * used outside of the component: in the module, the plugins, the scheduler or on the command line.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    private function loadLanguage(): void
    {
        if ($this->languageLoaded) {
            return;
        }

        $this->languageLoaded = true;

        try {
            $language = Factory::getApplication()->getLanguage();
        } catch (\Throwable) {
            return;
        }

        $language->load('com_kurso', JPATH_ADMINISTRATOR)
            || $language->load('com_kurso', JPATH_ADMINISTRATOR . '/components/com_kurso');
    }
}
