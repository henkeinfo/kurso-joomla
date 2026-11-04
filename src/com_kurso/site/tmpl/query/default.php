<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

\defined('_JEXEC') or die;

/** @var \Kurso\Component\Kurso\Site\View\Query\HtmlView $this */
?>
<div class="com-kurso-query<?php echo $this->pageclass_sfx; ?>">
    <?php if ($this->params->get('show_page_heading')) : ?>
        <div class="page-header">
            <h1><?php echo $this->escape($this->params->get('page_heading')); ?></h1>
        </div>
    <?php endif; ?>

    <?php // Rendered by the sandboxed Twig template of the query and filtered (see OutputRenderer). ?>
    <?php echo $this->html; ?>
</div>
