<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Administrator\Service;

use Joomla\CMS\Application\CMSApplicationInterface;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Language\Text;
use Joomla\Registry\Registry;
use Kurso\Component\Kurso\Administrator\Exception\KursoException;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Renders KURSO queries for the frontend (content plugin, module, menu item).
 *
 * @since  1.0.0
 */
final class OutputRenderer
{
    /**
     * @param   QueryRepository          $repository  The repository
     * @param   DataRefresher            $refresher   The refresher
     * @param   TwigFactory              $twig        The Twig factory
     * @param   Registry                 $params      The component options
     * @param   CMSApplicationInterface  $app         The application
     *
     * @since   1.0.0
     */
    public function __construct(
        private readonly QueryRepository $repository,
        private readonly DataRefresher $refresher,
        private readonly TwigFactory $twig,
        private readonly Registry $params,
        private readonly CMSApplicationInterface $app
    ) {
    }

    /**
     * Renders a published query identified by its alias.
     *
     * @param   string       $alias             The query alias
     * @param   string       $cssClass          Additional CSS classes for the wrapper
     * @param   string|null  $templateOverride  A template replacing the one stored with the query
     *
     * @return  string  The HTML (empty if nothing can be shown to the current user)
     *
     * @since   1.0.0
     */
    public function renderByAlias(string $alias, string $cssClass = '', ?string $templateOverride = null): string
    {
        $alias = trim($alias);

        if ($alias === '') {
            return $this->errorBox(Text::_('COM_KURSO_ERROR_TAG_NO_QUERY'));
        }

        $query = $this->repository->findByAlias($alias);

        if ($query === null) {
            return $this->errorBox(Text::sprintf('COM_KURSO_ERROR_QUERY_NOT_FOUND', $alias));
        }

        return $this->renderQuery($query, $cssClass, $templateOverride);
    }

    /**
     * Renders a published query identified by its id.
     *
     * @param   int          $id                The query id
     * @param   string       $cssClass          Additional CSS classes for the wrapper
     * @param   string|null  $templateOverride  A template replacing the one stored with the query
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public function renderById(int $id, string $cssClass = '', ?string $templateOverride = null): string
    {
        $query = $id > 0 ? $this->repository->findById($id, true) : null;

        if ($query === null) {
            return $this->errorBox(Text::sprintf('COM_KURSO_ERROR_QUERY_ID_NOT_FOUND', $id));
        }

        return $this->renderQuery($query, $cssClass, $templateOverride);
    }

    /**
     * Renders a template with the given data and applies the output filter, exactly as on the frontend.
     *
     * @param   string  $template  The Twig template
     * @param   array   $data      The template variables
     *
     * @return  string
     *
     * @throws  KursoException
     *
     * @since   1.0.0
     */
    public function renderTemplate(string $template, array $data): string
    {
        return $this->filter($this->twig->renderTemplate($template, $data));
    }

    /**
     * Loads the frontend stylesheet, if enabled in the options.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function loadAssets(): void
    {
        if (!$this->params->get('load_css', 1)) {
            return;
        }

        // Only web applications have a document (not the console application of the scheduler).
        $document = method_exists($this->app, 'getDocument') ? $this->app->getDocument() : null;

        if (!$document instanceof HtmlDocument) {
            return;
        }

        $wa = $document->getWebAssetManager();
        $wa->getRegistry()->addExtensionRegistryFile('com_kurso');
        $wa->useStyle('com_kurso.frontend');
    }

    /**
     * Renders a query row.
     *
     * @param   object       $query             The query row
     * @param   string       $cssClass          Additional CSS classes
     * @param   string|null  $templateOverride  Template override
     *
     * @return  string
     *
     * @since   1.0.0
     */
    private function renderQuery(object $query, string $cssClass, ?string $templateOverride): string
    {
        $template = $templateOverride !== null && trim($templateOverride) !== '' ? $templateOverride : (string) $query->template;

        if (trim($template) === '') {
            return $this->errorBox(Text::sprintf('COM_KURSO_ERROR_NO_TEMPLATE', $query->alias));
        }

        try {
            $cache = $this->refresher->getForDisplay($query, (bool) $this->params->get('page_view_refresh', 1));
        } catch (\RuntimeException $e) {
            return $this->errorBox(Text::sprintf('COM_KURSO_ERROR_RENDER', $query->alias, $e->getMessage()));
        }

        if (!$cache->hasData()) {
            $reason = $cache->error ?? Text::_('COM_KURSO_ERROR_NO_DATA_YET');

            return $this->errorBox(Text::sprintf('COM_KURSO_ERROR_NO_DATA', $query->alias, $reason));
        }

        try {
            $html = $this->renderTemplate($template, $cache->data);
        } catch (KursoException $e) {
            return $this->errorBox(Text::sprintf('COM_KURSO_ERROR_TEMPLATE', $query->alias, $e->getMessage()));
        }

        $this->loadAssets();

        $classes = trim('kurso kurso--' . $query->alias . ' ' . self::sanitizeClasses($cssClass));

        return '<div class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '">' . $html . '</div>';
    }

    /**
     * Removes dangerous markup (scripts, event handlers, javascript: URLs, iframes …) from rendered output.
     *
     * Can be disabled in the options for templates that intentionally contain scripts (e.g. JSON-LD).
     *
     * @param   string  $html  The rendered HTML
     *
     * @return  string
     *
     * @since   1.0.0
     */
    private function filter(string $html): string
    {
        if (!$this->params->get('filter_output', 1)) {
            return $html;
        }

        $filter = InputFilter::getInstance([], [], InputFilter::ONLY_BLOCK_DEFINED_TAGS, InputFilter::ONLY_BLOCK_DEFINED_ATTRIBUTES);

        return (string) $filter->clean($html, 'html');
    }

    /**
     * Returns an error message for users allowed to see it, otherwise nothing.
     *
     * @param   string  $message  The message
     *
     * @return  string
     *
     * @since   1.0.0
     */
    private function errorBox(string $message): string
    {
        if (!$this->canSeeErrors()) {
            return '';
        }

        return '<div class="kurso-error alert alert-warning" role="alert"><strong>KURSO:</strong> '
            . htmlspecialchars($message, ENT_QUOTES, 'UTF-8')
            . ' <small>(' . htmlspecialchars(Text::_('COM_KURSO_ERROR_ONLY_VISIBLE_TO_MANAGERS'), ENT_QUOTES, 'UTF-8') . ')</small></div>';
    }

    /**
     * @return  boolean  True if the current user may see error messages
     *
     * @since   1.0.0
     */
    private function canSeeErrors(): bool
    {
        if ($this->params->get('show_errors', 'managers') !== 'managers') {
            return false;
        }

        $user = $this->app->getIdentity();

        return $user !== null && !$user->guest && $user->authorise('core.manage', 'com_kurso');
    }

    /**
     * Keeps only characters that are valid in CSS class names.
     *
     * @param   string  $classes  Space separated class names
     *
     * @return  string
     *
     * @since   1.0.0
     */
    public static function sanitizeClasses(string $classes): string
    {
        $valid = array_filter(
            preg_split('/\s+/', $classes) ?: [],
            static fn (string $class): bool => (bool) preg_match('/^-?[A-Za-z_][A-Za-z0-9_-]*$/', $class)
        );

        return implode(' ', $valid);
    }
}
