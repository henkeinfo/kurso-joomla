<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Administrator\Controller;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;
use Kurso\Component\Kurso\Administrator\Exception\KursoException;
use Kurso\Component\Kurso\Administrator\Extension\KursoComponent;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * AJAX endpoints of the query editor: evaluate variables, run a query, preview a template.
 *
 * All tasks require a POST request with a valid form token and the permission to create or edit queries.
 *
 * @since  1.0.0
 */
class ToolsController extends BaseController
{
    use JsonResponseTrait;

    /**
     * Evaluates the Twig expressions in the variables JSON.
     *
     * POST: variables
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function evaluate(): void
    {
        $this->guard();

        try {
            $result = $this->getKurso()->getClient()->evaluateVariables($this->input->post->getRaw('variables', ''));
        } catch (KursoException $e) {
            $this->sendJson(new JsonResponse(null, $e->getMessage(), true));

            return;
        }

        $this->sendJson(new JsonResponse(['variables' => (object) $result]));
    }

    /**
     * Executes the query as currently entered in the form (without saving it) and renders the template.
     *
     * POST: graphql, variables, template
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function run(): void
    {
        $this->guard();

        $kurso = $this->getKurso();

        try {
            $data = $kurso->getClient()->execute(
                $this->input->post->getRaw('graphql', ''),
                $this->input->post->getRaw('variables', '')
            );
        } catch (KursoException $e) {
            $this->sendJson(new JsonResponse(null, $e->getMessage(), true));

            return;
        }

        $this->sendJson(new JsonResponse($this->buildPreview($data, $this->input->post->getRaw('template', ''), null)));
    }

    /**
     * Renders the template as currently entered in the form with the cached data of a saved query.
     *
     * POST: id, template
     *
     * @return  void
     *
     * @since   1.0.0
     */
    public function preview(): void
    {
        $this->guard();

        $id    = $this->input->post->getInt('id', 0);
        $cache = $id > 0 ? $this->getKurso()->getRepository()->getCache($id) : null;

        if ($cache === null || !$cache->hasData()) {
            $this->sendJson(new JsonResponse(null, Text::_('COM_KURSO_PREVIEW_NO_CACHE'), true));

            return;
        }

        $this->sendJson(new JsonResponse($this->buildPreview($cache->data, $this->input->post->getRaw('template', ''), $cache->fetchedAt)));
    }

    /**
     * Renders the template and builds the response payload.
     *
     * @param   array     $data       The template data
     * @param   string    $template   The template
     * @param   int|null  $fetchedAt  Time the data was fetched (null = just now)
     *
     * @return  array
     *
     * @since   1.0.0
     */
    private function buildPreview(array $data, string $template, ?int $fetchedAt): array
    {
        $html  = '';
        $error = null;

        if (trim($template) !== '') {
            try {
                $html = $this->getKurso()->getRenderer()->renderTemplate($template, $data);
            } catch (KursoException $e) {
                $error = $e->getMessage();
            }
        }

        return [
            'data'        => (object) $data,
            'html'        => $html,
            'renderError' => $error,
            'source'      => $fetchedAt === null
                ? Text::_('COM_KURSO_PREVIEW_SOURCE_LIVE')
                : Text::sprintf('COM_KURSO_PREVIEW_SOURCE_CACHE', HTMLHelper::_('date', '@' . $fetchedAt, Text::_('DATE_FORMAT_LC6'))),
        ];
    }

    /**
     * Checks the request method, token and permissions; ends the request with an error response otherwise.
     *
     * @return  void
     *
     * @since   1.0.0
     */
    private function guard(): void
    {
        if (strtoupper($this->input->getMethod()) !== 'POST' || !$this->checkToken('post', false)) {
            $this->sendJson(new JsonResponse(null, Text::_('JINVALID_TOKEN_NOTICE'), true), 403);
        }

        $user = $this->app->getIdentity();

        if (!$user->authorise('core.edit', 'com_kurso') && !$user->authorise('core.create', 'com_kurso')) {
            $this->sendJson(new JsonResponse(null, Text::_('JERROR_ALERTNOAUTHOR'), true), 403);
        }
    }

    /**
     * @return  KursoComponent
     *
     * @since   1.0.0
     */
    private function getKurso(): KursoComponent
    {
        return $this->app->bootComponent('com_kurso');
    }
}
