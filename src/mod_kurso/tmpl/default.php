<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  mod_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

\defined('_JEXEC') or die;

/**
 * @var  \Joomla\Registry\Registry  $params
 * @var  string                     $kursoHtml  Rendered by the sandboxed Twig template and filtered (see OutputRenderer)
 */
?>
<div class="mod-kurso<?php echo htmlspecialchars((string) $params->get('moduleclass_sfx', ''), ENT_QUOTES, 'UTF-8'); ?>">
    <?php echo $kursoHtml; ?>
</div>
