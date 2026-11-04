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
use Joomla\CMS\Router\Route;

/** @var \Kurso\Component\Kurso\Administrator\View\Connection\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_kurso');
$wa->useScript('keepalive')
    ->useScript('form.validate')
    ->useStyle('com_kurso.admin')
    ->useScript('com_kurso.admin-connection');

$this->getDocument()->addScriptOptions('com_kurso.connection', [
    'testUrl' => Route::_('index.php?option=com_kurso&task=connection.test&format=json', false),
]);

Text::script('COM_KURSO_CONNECTION_TESTING');
Text::script('COM_KURSO_REQUEST_FAILED');
?>
<form action="<?php echo Route::_('index.php?option=com_kurso&view=connection'); ?>" method="post" name="adminForm" id="connection-form" class="form-validate" autocomplete="off">
    <div class="row">
        <div class="col-lg-8">
            <div class="main-card p-4">
                <?php if ($this->status['unreadable']) : ?>
                    <div class="alert alert-danger"><?php echo Text::_('COM_KURSO_ERROR_PASSWORD_UNREADABLE'); ?></div>
                <?php elseif (!$this->status['configured']) : ?>
                    <div class="alert alert-info"><?php echo Text::_('COM_KURSO_CONNECTION_INTRO'); ?></div>
                <?php endif; ?>

                <fieldset>
                    <legend><?php echo Text::_('COM_KURSO_CONNECTION_LEGEND'); ?></legend>
                    <?php echo $this->form->renderFieldset('connection'); ?>
                </fieldset>

                <div class="kurso-actions">
                    <button type="button" class="btn btn-outline-primary" id="kurso-test-connection">
                        <span class="icon-plug" aria-hidden="true"></span> <?php echo Text::_('COM_KURSO_CONNECTION_TEST'); ?>
                    </button>
                    <span class="kurso-status" id="kurso-test-status" role="status" aria-live="polite"></span>
                </div>
                <div class="alert mt-3" id="kurso-test-result" hidden></div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card kurso-info">
                <div class="card-body">
                    <h3 class="h6"><?php echo Text::_('COM_KURSO_CONNECTION_HELP_TITLE'); ?></h3>
                    <p class="small"><?php echo Text::_('COM_KURSO_CONNECTION_HELP'); ?></p>
                    <h3 class="h6"><?php echo Text::_('COM_KURSO_CONNECTION_DEMO_TITLE'); ?></h3>
                    <p class="small mb-0"><?php echo Text::_('COM_KURSO_CONNECTION_DEMO'); ?></p>
                </div>
            </div>
        </div>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
