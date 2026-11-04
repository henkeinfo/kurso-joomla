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
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;

/** @var \Kurso\Component\Kurso\Administrator\View\Queries\HtmlView $this */

$displayData = [
    'textPrefix' => 'COM_KURSO_QUERIES',
    'formURL'    => 'index.php?option=com_kurso&view=queries',
    'helpURL'    => 'https://github.com/henkeinfo/kurso-joomla#readme',
    'icon'       => 'icon-calendar',
];

if ($this->getCurrentUser()->authorise('core.create', 'com_kurso')) {
    $displayData['createURL'] = 'index.php?option=com_kurso&task=query.add';
}
?>
<?php if (!$this->connectionConfigured) : ?>
    <div class="alert alert-warning">
        <span class="icon-exclamation-triangle" aria-hidden="true"></span>
        <?php echo Text::_('COM_KURSO_CONNECTION_NOT_CONFIGURED'); ?>
        <?php if ($this->canEditConnection) : ?>
            <a class="alert-link" href="<?php echo Route::_('index.php?option=com_kurso&view=connection'); ?>"><?php echo Text::_('COM_KURSO_CONNECTION_CONFIGURE_NOW'); ?></a>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php echo LayoutHelper::render('joomla.content.emptystate', $displayData); ?>
