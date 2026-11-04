<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var \Kurso\Component\Kurso\Administrator\View\Select\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_kurso');
$wa->useScript('modal-content-select')
    ->useStyle('com_kurso.admin')
    ->useScript('com_kurso.admin-select');
?>
<div class="container-popup kurso-select">
    <?php if (empty($this->items)) : ?>
        <div class="alert alert-info"><?php echo Text::_('COM_KURSO_SELECT_EMPTY'); ?></div>
    <?php else : ?>
        <p><?php echo Text::_('COM_KURSO_SELECT_HELP'); ?></p>

        <div class="mb-3">
            <label class="visually-hidden" for="kurso-select-filter"><?php echo Text::_('JSEARCH_FILTER'); ?></label>
            <input type="search" class="form-control" id="kurso-select-filter" placeholder="<?php echo $this->escape(Text::_('JSEARCH_FILTER')); ?>" autocomplete="off">
        </div>

        <table class="table table-hover" id="kurso-select-list">
            <caption class="visually-hidden"><?php echo Text::_('COM_KURSO_QUERIES_TABLE_CAPTION'); ?></caption>
            <thead>
                <tr>
                    <th scope="col"><?php echo Text::_('JGLOBAL_TITLE'); ?></th>
                    <th scope="col"><?php echo Text::_('COM_KURSO_HEADING_EMBED'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($this->items as $item) :
                    $tag = '{kurso query="' . $item->alias . '"}';
                    ?>
                    <tr data-kurso-search="<?php echo $this->escape(mb_strtolower($item->title . ' ' . $item->alias)); ?>">
                        <th scope="row">
                            <button type="button" class="btn btn-sm btn-success w-100 text-start"
                                data-content-select
                                data-content-type="com_kurso.query"
                                data-id="<?php echo (int) $item->id; ?>"
                                data-title="<?php echo $this->escape($item->title); ?>"
                                data-html="<?php echo $this->escape($tag); ?>">
                                <?php echo $this->escape($item->title); ?>
                            </button>
                        </th>
                        <td><code><?php echo $this->escape($tag); ?></code></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
