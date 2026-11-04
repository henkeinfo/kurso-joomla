<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  pkg_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    /**
     * Registers the installer script.
     *
     * @param   Container  $container  The DI container.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function register(Container $container): void
    {
        $container->set(
            InstallerScriptInterface::class,
            new class ($container->get(DatabaseInterface::class)) implements InstallerScriptInterface {
                private const MIN_PHP    = '8.1.0';
                private const MIN_JOOMLA = '5.4.0';
                private const TASK_TYPE  = 'kurso.refresh';

                /**
                 * @param   DatabaseInterface  $db  The database
                 *
                 * @since   1.0.0
                 */
                public function __construct(private readonly DatabaseInterface $db)
                {
                }

                public function install(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function update(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                /**
                 * Removes the scheduled task, it would be orphaned without the task plugin.
                 */
                public function uninstall(InstallerAdapter $adapter): bool
                {
                    try {
                        $type  = self::TASK_TYPE;
                        $query = $this->db->createQuery()
                            ->delete($this->db->quoteName('#__scheduler_tasks'))
                            ->where($this->db->quoteName('type') . ' = :type')
                            ->bind(':type', $type);

                        $this->db->setQuery($query)->execute();
                    } catch (\Throwable) {
                        // Not critical.
                    }

                    return true;
                }

                /**
                 * Checks the requirements before installing or updating.
                 */
                public function preflight(string $type, InstallerAdapter $adapter): bool
                {
                    if ($type === 'uninstall') {
                        return true;
                    }

                    $app = Factory::getApplication();

                    if (version_compare(PHP_VERSION, self::MIN_PHP, '<')) {
                        $app->enqueueMessage(sprintf('KURSO for Joomla requires PHP %s or newer, this server runs PHP %s.', self::MIN_PHP, PHP_VERSION), 'error');

                        return false;
                    }

                    if (version_compare(JVERSION, self::MIN_JOOMLA, '<')) {
                        $app->enqueueMessage(sprintf('KURSO for Joomla requires Joomla %s or newer, this site runs Joomla %s.', self::MIN_JOOMLA, JVERSION), 'error');

                        return false;
                    }

                    foreach (['json', 'mbstring'] as $extension) {
                        if (!\extension_loaded($extension) && !\function_exists($extension === 'json' ? 'json_encode' : 'mb_strlen')) {
                            $app->enqueueMessage(sprintf('KURSO for Joomla requires the PHP extension "%s".', $extension), 'error');

                            return false;
                        }
                    }

                    return true;
                }

                /**
                 * On a new installation: enables the plugins and creates the scheduled task.
                 */
                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    if (!\in_array($type, ['install', 'discover_install'], true)) {
                        return true;
                    }

                    $this->loadLanguage($adapter);
                    $this->enablePlugins();
                    $this->createTask();

                    Factory::getApplication()->enqueueMessage(Text::_('PKG_KURSO_INSTALLED'), 'message');

                    return true;
                }

                private function loadLanguage(InstallerAdapter $adapter): void
                {
                    $language = Factory::getApplication()->getLanguage();
                    $source   = $adapter->getParent()->getPath('source');

                    $language->load('pkg_kurso.sys', JPATH_SITE)
                        || ($source && $language->load('pkg_kurso.sys', $source));
                }

                private function enablePlugins(): void
                {
                    $query = $this->db->createQuery()
                        ->update($this->db->quoteName('#__extensions'))
                        ->set($this->db->quoteName('enabled') . ' = 1')
                        ->where($this->db->quoteName('type') . ' = ' . $this->db->quote('plugin'))
                        ->where($this->db->quoteName('element') . ' = ' . $this->db->quote('kurso'))
                        ->whereIn($this->db->quoteName('folder'), ['content', 'task', 'editors-xtd'], ParameterType::STRING);

                    $this->db->setQuery($query)->execute();
                }

                /**
                 * Creates the task "KURSO – Refresh course data" (every 5 minutes), unless it exists.
                 */
                private function createTask(): void
                {
                    $type  = self::TASK_TYPE;
                    $query = $this->db->createQuery()
                        ->select('COUNT(*)')
                        ->from($this->db->quoteName('#__scheduler_tasks'))
                        ->where($this->db->quoteName('type') . ' = :type')
                        ->bind(':type', $type);

                    if ((int) $this->db->setQuery($query)->loadResult() > 0) {
                        return;
                    }

                    $app = Factory::getApplication();

                    try {
                        /** @var \Joomla\Component\Scheduler\Administrator\Model\TaskModel $model */
                        $model = $app->bootComponent('com_scheduler')->getMVCFactory()
                            ->createModel('Task', 'Administrator', ['ignore_request' => true]);

                        $saved = $model->save([
                            'id'              => 0,
                            'title'           => Text::_('PKG_KURSO_TASK_TITLE'),
                            'type'            => self::TASK_TYPE,
                            'state'           => 1,
                            'note'            => Text::_('PKG_KURSO_TASK_NOTE'),
                            'execution_rules' => [
                                'rule-type'        => 'interval-minutes',
                                'interval-minutes' => '5',
                                'exec-day'         => '',
                                'exec-time'        => '',
                            ],
                            'params'          => [
                                'individual_log' => false,
                                'log_file'       => '',
                                'notifications'  => [
                                    'success_mail'       => '0',
                                    'failure_mail'       => '0',
                                    'fatal_failure_mail' => '1',
                                    'orphan_mail'        => '1',
                                ],
                            ],
                        ]);
                    } catch (\Throwable $e) {
                        $saved = false;
                    }

                    if (!$saved) {
                        $app->enqueueMessage(Text::_('PKG_KURSO_TASK_CREATE_FAILED'), 'warning');
                    }
                }
            }
        );
    }
};
