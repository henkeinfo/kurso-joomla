<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Kurso\Component\Kurso\Administrator\Service\QueryRepository;

/** @var \Kurso\Component\Kurso\Administrator\View\Queries\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_kurso');
$wa->useScript('table.columns')
    ->useScript('multiselect')
    ->useStyle('com_kurso.admin')
    ->useScript('com_kurso.admin-queries');

$user      = $this->getCurrentUser();
$listOrder = $this->escape($this->state->get('list.ordering'));
$listDirn  = $this->escape($this->state->get('list.direction'));
$now       = time();

Text::script('COM_KURSO_COPIED');
Text::script('COM_KURSO_FETCHING');
?>
<form action="<?php echo Route::_('index.php?option=com_kurso&view=queries'); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php if (!$this->connectionConfigured) : ?>
                    <div class="alert alert-warning">
                        <span class="icon-exclamation-triangle" aria-hidden="true"></span>
                        <?php echo Text::_('COM_KURSO_CONNECTION_NOT_CONFIGURED'); ?>
                        <?php if ($this->canEditConnection) : ?>
                            <a class="alert-link" href="<?php echo Route::_('index.php?option=com_kurso&view=connection'); ?>"><?php echo Text::_('COM_KURSO_CONNECTION_CONFIGURE_NOW'); ?></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php echo LayoutHelper::render('joomla.searchtools.default', ['view' => $this]); ?>

                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span><span class="visually-hidden"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else : ?>
                    <table class="table" id="kursoQueryList">
                        <caption class="visually-hidden">
                            <?php echo Text::_('COM_KURSO_QUERIES_TABLE_CAPTION'); ?>,
                            <span id="orderedBy"><?php echo Text::_('JGLOBAL_SORTED_BY'); ?> </span>,
                            <span id="filteredBy"><?php echo Text::_('JGLOBAL_FILTERED_BY'); ?></span>
                        </caption>
                        <thead>
                            <tr>
                                <td class="w-1 text-center">
                                    <?php echo HTMLHelper::_('grid.checkall'); ?>
                                </td>
                                <th scope="col" class="w-1 text-center">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGLOBAL_TITLE', 'a.title', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="d-none d-md-table-cell">
                                    <?php echo Text::_('COM_KURSO_HEADING_EMBED'); ?>
                                </th>
                                <th scope="col" class="w-15">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'COM_KURSO_HEADING_FETCHED', 'c.fetched_at', $listDirn, $listOrder); ?>
                                </th>
                                <th scope="col" class="w-10">
                                    <?php echo Text::_('COM_KURSO_HEADING_ACTIONS'); ?>
                                </th>
                                <th scope="col" class="w-5 d-none d-md-table-cell">
                                    <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($this->items as $i => $item) :
                            $canEdit    = $user->authorise('core.edit', 'com_kurso');
                            $canDelete  = $user->authorise('core.delete', 'com_kurso');
                            $canCheckin = $user->authorise('core.manage', 'com_checkin') || (int) $item->checked_out === (int) $user->id || !$item->checked_out;
                            $canChange  = $user->authorise('core.edit.state', 'com_kurso') && $canCheckin;
                            $tag        = '{kurso query="' . $item->alias . '"}';
                            $fetchedAt  = QueryRepository::toTimestamp($item->fetched_at);
                            $errorAt    = QueryRepository::toTimestamp($item->error_at);
                            $failing    = $item->error !== null && $item->error !== '' && ($fetchedAt === null || ($errorAt ?? 0) >= $fetchedAt);
                            $outdated   = $fetchedAt !== null && $fetchedAt < $now - 2 * 60 * max(1, (int) $item->refresh_interval);
                            ?>
                            <tr class="row<?php echo $i % 2; ?>">
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('grid.id', $i, $item->id, false, 'cid', 'cb', $item->title); ?>
                                </td>
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('jgrid.published', $item->state, $i, 'queries.', $canChange); ?>
                                </td>
                                <th scope="row">
                                    <?php if ($item->checked_out) : ?>
                                        <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->editor, $item->checked_out_time, 'queries.', $canCheckin); ?>
                                    <?php endif; ?>
                                    <?php if ($canEdit) : ?>
                                        <a href="<?php echo Route::_('index.php?option=com_kurso&task=query.edit&id=' . (int) $item->id); ?>" title="<?php echo Text::_('JACTION_EDIT'); ?> <?php echo $this->escape($item->title); ?>">
                                            <?php echo $this->escape($item->title); ?></a>
                                    <?php else : ?>
                                        <?php echo $this->escape($item->title); ?>
                                    <?php endif; ?>
                                    <div class="small">
                                        <?php echo Text::_('JFIELD_ALIAS_LABEL'); ?>: <?php echo $this->escape($item->alias); ?>
                                        <?php if ($item->note !== '') : ?>
                                            <span class="text-muted">– <?php echo $this->escape($item->note); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </th>
                                <td class="d-none d-md-table-cell">
                                    <div class="kurso-embed">
                                        <code class="kurso-embed__tag"><?php echo $this->escape($tag); ?></code>
                                        <button type="button" class="btn btn-sm btn-outline-secondary kurso-copy" data-kurso-copy="<?php echo $this->escape($tag); ?>">
                                            <span class="icon-copy" aria-hidden="true"></span>
                                            <span class="kurso-copy__label"><?php echo Text::_('COM_KURSO_COPY'); ?></span>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($fetchedAt !== null) : ?>
                                        <span class="badge <?php echo $outdated ? 'bg-warning text-dark' : 'bg-success'; ?>">
                                            <?php echo HTMLHelper::_('date', $item->fetched_at, Text::_('DATE_FORMAT_LC5')); ?>
                                        </span>
                                    <?php else : ?>
                                        <span class="badge bg-secondary"><?php echo Text::_('COM_KURSO_NEVER_FETCHED'); ?></span>
                                    <?php endif; ?>
                                    <div class="small text-muted"><?php echo Text::sprintf('COM_KURSO_EVERY_N_MINUTES', Text::plural('COM_KURSO_N_MINUTES', (int) $item->refresh_interval)); ?></div>
                                    <?php if ($failing) : ?>
                                        <div class="small text-danger kurso-fetch-error">
                                            <span class="icon-exclamation-circle" aria-hidden="true"></span>
                                            <?php echo $this->escape(Text::sprintf('COM_KURSO_LAST_ATTEMPT_FAILED', HTMLHelper::_('date', $item->error_at, Text::_('DATE_FORMAT_LC5')), $item->error)); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="kurso-row-actions">
                                    <?php if ($canEdit) : ?>
                                        <a class="btn btn-sm btn-outline-secondary" href="<?php echo Route::_('index.php?option=com_kurso&task=query.edit&id=' . (int) $item->id); ?>">
                                            <span class="icon-edit" aria-hidden="true"></span> <?php echo Text::_('JACTION_EDIT'); ?>
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-kurso-row-task="queries.refresh" data-kurso-row="cb<?php echo $i; ?>">
                                            <span class="icon-refresh" aria-hidden="true"></span> <?php echo Text::_('COM_KURSO_FETCH_NOW'); ?>
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($canDelete) : ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" data-kurso-row-task="queries.delete" data-kurso-row="cb<?php echo $i; ?>"
                                            data-kurso-confirm="<?php echo $this->escape(Text::sprintf('COM_KURSO_CONFIRM_DELETE_ONE', $item->title)); ?>">
                                            <span class="icon-trash" aria-hidden="true"></span> <?php echo Text::_('JACTION_DELETE'); ?>
                                        </button>
                                    <?php endif; ?>
                                </td>
                                <td class="d-none d-md-table-cell">
                                    <?php echo (int) $item->id; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php echo $this->pagination->getListFooter(); ?>
                <?php endif; ?>

                <input type="hidden" name="task" value="">
                <input type="hidden" name="boxchecked" value="0">
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>
