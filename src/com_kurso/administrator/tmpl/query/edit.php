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
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Kurso\Component\Kurso\Administrator\Service\Examples;

/** @var \Kurso\Component\Kurso\Administrator\View\Query\HtmlView $this */

$wa = $this->getDocument()->getWebAssetManager();
$wa->getRegistry()->addExtensionRegistryFile('com_kurso');

if (is_file(JPATH_ROOT . '/media/plg_editors_codemirror/joomla.asset.json')) {
    // Syntax modes for GraphQL and Twig, loaded by the CodeMirror editor through the import map.
    $wa->getRegistry()->addExtensionRegistryFile('plg_editors_codemirror');
    $wa->useScript('@codemirror/lang-kursographql')
        ->useScript('@codemirror/lang-kursotwig');
}

$wa->useScript('keepalive')
    ->useScript('form.validate')
    ->useStyle('com_kurso.admin')
    ->useScript('com_kurso.admin-query-edit');

$item     = $this->item;
$isNew    = empty($item->id);
$examples = Examples::all();
$token    = Session::getFormToken();

$this->getDocument()->addScriptOptions('com_kurso.queryEdit', [
    'id'         => (int) ($item->id ?? 0),
    'token'      => $token,
    'graphqlUrl' => $this->graphqlUrl,
    'cssUrl'     => Uri::root() . 'media/com_kurso/css/kurso-frontend.css',
    'urls'       => [
        'evaluate' => Route::_('index.php?option=com_kurso&task=tools.evaluate&format=json', false),
        'run'      => Route::_('index.php?option=com_kurso&task=tools.run&format=json', false),
        'preview'  => Route::_('index.php?option=com_kurso&task=tools.preview&format=json', false),
    ],
    'examples'   => $examples,
]);

Text::script('COM_KURSO_COPIED');
Text::script('COM_KURSO_EXAMPLE_CONFIRM');
Text::script('COM_KURSO_GRAPHIQL_NO_URL');
Text::script('COM_KURSO_PREVIEW_LOADING');
Text::script('COM_KURSO_PREVIEW_EMPTY');
Text::script('COM_KURSO_PREVIEW_RENDER_ERROR');
Text::script('COM_KURSO_REQUEST_FAILED');
Text::script('COM_KURSO_VARIABLES_RESULT');

$tag = $isNew ? '' : '{kurso query="' . $item->alias . '"}';
?>

<form action="<?php echo Route::_('index.php?option=com_kurso&layout=edit&id=' . (int) $item->id); ?>" method="post" name="adminForm" id="query-form" aria-label="<?php echo Text::_($isNew ? 'COM_KURSO_QUERY_NEW' : 'COM_KURSO_QUERY_EDIT', true); ?>" class="form-validate">

    <?php echo LayoutHelper::render('joomla.edit.title_alias', $this); ?>

    <div class="main-card">
        <?php echo HTMLHelper::_('uitab.startTabSet', 'kursoTab', ['active' => 'query', 'recall' => true, 'breakpoint' => 768]); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'kursoTab', 'query', Text::_('COM_KURSO_TAB_QUERY')); ?>
        <div class="row">
            <div class="col-lg-9 form-vertical">
                <?php echo $this->form->renderField('graphql'); ?>
                <div class="kurso-actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="kurso-graphiql">
                        <span class="icon-external-link-alt" aria-hidden="true"></span> <?php echo Text::_('COM_KURSO_OPEN_GRAPHIQL'); ?>
                    </button>
                </div>

                <?php echo $this->form->renderField('variables'); ?>
                <div class="kurso-actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="kurso-evaluate">
                        <span class="icon-play" aria-hidden="true"></span> <?php echo Text::_('COM_KURSO_EVALUATE_VARIABLES'); ?>
                    </button>
                </div>
                <pre class="kurso-result" id="kurso-variables-result" hidden></pre>

                <?php echo $this->form->renderField('template'); ?>
            </div>
            <div class="col-lg-3">
                <fieldset class="form-vertical">
                    <legend class="visually-hidden"><?php echo Text::_('COM_KURSO_FIELDSET_SETTINGS'); ?></legend>
                    <?php echo $this->form->renderFieldset('settings'); ?>
                </fieldset>

                <?php if (!$isNew) : ?>
                    <div class="card kurso-info">
                        <div class="card-body">
                            <h3 class="h6"><?php echo Text::_('COM_KURSO_EMBED_TITLE'); ?></h3>
                            <div class="kurso-embed">
                                <code class="kurso-embed__tag"><?php echo $this->escape($tag); ?></code>
                                <button type="button" class="btn btn-sm btn-outline-secondary kurso-copy" data-kurso-copy="<?php echo $this->escape($tag); ?>">
                                    <span class="icon-copy" aria-hidden="true"></span>
                                    <span class="kurso-copy__label"><?php echo Text::_('COM_KURSO_COPY'); ?></span>
                                </button>
                            </div>
                            <p class="small text-muted mt-2 mb-3"><?php echo Text::_('COM_KURSO_EMBED_HELP'); ?></p>

                            <h3 class="h6"><?php echo Text::_('COM_KURSO_HEADING_FETCHED'); ?></h3>
                            <?php if ($this->cache && $this->cache->fetchedAt !== null) : ?>
                                <p class="mb-1"><span class="badge bg-success"><?php echo HTMLHelper::_('date', '@' . $this->cache->fetchedAt, Text::_('DATE_FORMAT_LC5')); ?></span></p>
                            <?php else : ?>
                                <p class="mb-1"><span class="badge bg-secondary"><?php echo Text::_('COM_KURSO_NEVER_FETCHED'); ?></span></p>
                            <?php endif; ?>
                            <?php if ($this->cache && $this->cache->error !== null) : ?>
                                <p class="small text-danger mb-0">
                                    <?php echo $this->escape(Text::sprintf('COM_KURSO_LAST_ATTEMPT_FAILED', HTMLHelper::_('date', '@' . (int) $this->cache->errorAt, Text::_('DATE_FORMAT_LC5')), $this->cache->error)); ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card kurso-info mb-3">
                    <div class="card-body">
                        <h3 class="h6"><label for="kurso-example"><?php echo Text::_('COM_KURSO_EXAMPLES_TITLE'); ?></label></h3>
                        <p class="small text-muted"><?php echo Text::_('COM_KURSO_EXAMPLES_HELP'); ?></p>
                        <select class="form-select form-select-sm mb-2" id="kurso-example">
                            <?php foreach ($examples as $key => $example) : ?>
                                <option value="<?php echo $this->escape($key); ?>"><?php echo $this->escape($example['title']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="kurso-load-example">
                            <span class="icon-upload" aria-hidden="true"></span> <?php echo Text::_('COM_KURSO_EXAMPLES_LOAD'); ?>
                        </button>
                    </div>
                </div>
                <div class="card kurso-info">
                    <div class="card-body kurso-cheatsheet">
                        <h3 class="h6"><?php echo Text::_('COM_KURSO_CHEATSHEET_TITLE'); ?></h3>
                        <dl class="small">
                            <dt><code>{{ course.name }}</code></dt><dd><?php echo Text::_('COM_KURSO_CHEATSHEET_OUTPUT'); ?></dd>
                            <dt><code>{% for course in allCourses %}…{% endfor %}</code></dt><dd><?php echo Text::_('COM_KURSO_CHEATSHEET_LOOP'); ?></dd>
                            <dt><code>{% if free > 0 %}…{% else %}…{% endif %}</code></dt><dd><?php echo Text::_('COM_KURSO_CHEATSHEET_IF'); ?></dd>
                            <dt><code>{% set free = c.maxPart - c._bookingsMeta.count %}</code></dt><dd><?php echo Text::_('COM_KURSO_CHEATSHEET_SET'); ?></dd>
                            <dt><code>{{ c.startDate|date("d.m.Y") }}</code></dt><dd><?php echo Text::_('COM_KURSO_CHEATSHEET_DATE'); ?></dd>
                            <dt><code>{{ c.startDate|jdate("d. F Y") }}</code></dt><dd><?php echo Text::_('COM_KURSO_CHEATSHEET_JDATE'); ?></dd>
                            <dt><code>{{ c.location.town|default("–") }}</code></dt><dd><?php echo Text::_('COM_KURSO_CHEATSHEET_DEFAULT'); ?></dd>
                        </dl>
                        <p class="small text-muted mb-0"><?php echo Text::_('COM_KURSO_CHEATSHEET_SANDBOX'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.addTab', 'kursoTab', 'preview', Text::_('COM_KURSO_TAB_PREVIEW')); ?>
        <p class="text-muted"><?php echo Text::_('COM_KURSO_PREVIEW_HELP'); ?></p>
        <div class="kurso-actions mb-3">
            <button type="button" class="btn btn-primary" id="kurso-run">
                <span class="icon-play" aria-hidden="true"></span> <?php echo Text::_('COM_KURSO_PREVIEW_RUN'); ?>
            </button>
            <?php if (!$isNew) : ?>
                <button type="button" class="btn btn-outline-secondary" id="kurso-preview-cache">
                    <span class="icon-database" aria-hidden="true"></span> <?php echo Text::_('COM_KURSO_PREVIEW_CACHE'); ?>
                </button>
            <?php endif; ?>
            <span class="kurso-status" id="kurso-preview-status" role="status" aria-live="polite"></span>
        </div>
        <div class="alert alert-danger" id="kurso-preview-error" hidden></div>
        <div class="row">
            <div class="col-xl-7">
                <h3 class="h6"><?php echo Text::_('COM_KURSO_PREVIEW_RENDERED'); ?></h3>
                <iframe class="kurso-preview-frame" id="kurso-preview-frame" sandbox="" title="<?php echo $this->escape(Text::_('COM_KURSO_PREVIEW_RENDERED')); ?>"></iframe>
            </div>
            <div class="col-xl-5">
                <h3 class="h6"><?php echo Text::_('COM_KURSO_PREVIEW_DATA'); ?></h3>
                <pre class="kurso-result kurso-result--data" id="kurso-preview-data"><?php echo Text::_('COM_KURSO_PREVIEW_EMPTY'); ?></pre>
            </div>
        </div>
        <?php echo HTMLHelper::_('uitab.endTab'); ?>

        <?php echo HTMLHelper::_('uitab.endTabSet'); ?>
    </div>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
