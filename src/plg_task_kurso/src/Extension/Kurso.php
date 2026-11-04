<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  plg_task_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Plugin\Task\Kurso\Extension;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Component\Scheduler\Administrator\Event\ExecuteTaskEvent;
use Joomla\Component\Scheduler\Administrator\Task\Status as TaskStatus;
use Joomla\Component\Scheduler\Administrator\Traits\TaskPluginTrait;
use Joomla\Event\SubscriberInterface;
use Kurso\Component\Kurso\Administrator\Extension\KursoComponent;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Scheduled task "KURSO – Refresh course data": fetches all published queries whose refresh interval
 * has elapsed. The task is created on installation and runs every five minutes by default.
 *
 * @since  1.0.0
 */
final class Kurso extends CMSPlugin implements SubscriberInterface
{
    use TaskPluginTrait;

    /**
     * The task routine of this plugin.
     */
    public const ROUTINE = 'kurso.refresh';

    /**
     * @var  array<string, array{langConstPrefix: string, method: string}>
     *
     * @since  1.0.0
     */
    protected const TASKS_MAP = [
        self::ROUTINE => [
            'langConstPrefix' => 'PLG_TASK_KURSO_REFRESH',
            'method'          => 'refreshQueries',
        ],
    ];

    /**
     * Load the language file on instantiation.
     *
     * @var    boolean
     * @since  1.0.0
     */
    protected $autoloadLanguage = true;

    /**
     * @return  array
     *
     * @since   1.0.0
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onTaskOptionsList'    => 'advertiseRoutines',
            'onExecuteTask'        => 'standardRoutineHandler',
            'onContentPrepareForm' => 'enhanceTaskItemForm',
        ];
    }

    /**
     * Refreshes the queries that are due.
     *
     * @param   ExecuteTaskEvent  $event  The task event
     *
     * @return  int  The routine exit code
     *
     * @since   1.0.0
     */
    protected function refreshQueries(ExecuteTaskEvent $event): int
    {
        if (!ComponentHelper::isEnabled('com_kurso')) {
            $this->logTask('com_kurso is not enabled, nothing to do.', 'warning');

            return TaskStatus::OK;
        }

        /** @var KursoComponent $kurso */
        $kurso  = $this->getApplication()->bootComponent('com_kurso');
        $report = $kurso->getRefresher()->refreshDue();

        $this->logTask(sprintf(
            'KURSO: %d refreshed (%s), %d not due, %d failed.',
            \count($report['refreshed']),
            implode(', ', $report['refreshed']) ?: '-',
            $report['skipped'],
            \count($report['failed'])
        ));

        foreach ($report['failed'] as $alias => $message) {
            $this->logTask(sprintf('KURSO: refreshing "%s" failed: %s', $alias, $message), 'warning');
        }

        return $report['failed'] === [] ? TaskStatus::OK : TaskStatus::INVALID_EXIT;
    }
}
