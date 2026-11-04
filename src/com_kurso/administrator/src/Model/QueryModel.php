<?php

/**
 * @package     KURSO for Joomla
 * @subpackage  com_kurso
 *
 * @copyright   (C) 2026 KURSO <https://www.kurso.de>
 * @license     MIT; see LICENSE
 */

namespace Kurso\Component\Kurso\Administrator\Model;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Form;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;
use Joomla\String\StringHelper;
use Kurso\Component\Kurso\Administrator\Exception\KursoException;
use Kurso\Component\Kurso\Administrator\Extension\KursoComponent;
use Kurso\Component\Kurso\Administrator\Service\CacheEntry;
use Kurso\Component\Kurso\Administrator\Service\Examples;
use Kurso\Component\Kurso\Administrator\Service\GraphQLClient;
use Kurso\Component\Kurso\Administrator\Table\QueryTable;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Item model for a single query.
 *
 * @since  1.0.0
 */
class QueryModel extends AdminModel
{
    /**
     * The type alias for this content type.
     *
     * @var    string
     * @since  1.0.0
     */
    public $typeAlias = 'com_kurso.query';

    /**
     * The prefix to use with controller messages.
     *
     * @var    string
     * @since  1.0.0
     */
    protected $text_prefix = 'COM_KURSO';

    /**
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data (default case), false if not.
     *
     * @return  Form|boolean
     *
     * @since   1.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm('com_kurso.query', 'query', ['control' => 'jform', 'load_data' => $loadData]);

        if (empty($form)) {
            return false;
        }

        $id = (int) ($data['id'] ?? $form->getValue('id'));

        if (!$this->canEditState((object) ['id' => $id])) {
            $form->setFieldAttribute('state', 'disabled', 'true');
            $form->setFieldAttribute('state', 'filter', 'unset');
        }

        return $form;
    }

    /**
     * @return  mixed  The data for the form.
     *
     * @since   1.0.0
     */
    protected function loadFormData()
    {
        $data = Factory::getApplication()->getUserState('com_kurso.edit.query.data', []);

        if (empty($data)) {
            $data = $this->getItem();

            // Pre-fill new queries with a working example.
            if ($data && empty($data->id)) {
                foreach (Examples::defaults() as $field => $value) {
                    if (empty($data->$field)) {
                        $data->$field = $value;
                    }
                }
            }
        }

        $this->preprocessData('com_kurso.query', $data);

        return $data;
    }

    /**
     * @param   string  $name     The table name. Optional.
     * @param   string  $prefix   The class prefix. Optional.
     * @param   array   $options  Configuration array for model. Optional.
     *
     * @return  QueryTable
     *
     * @since   1.0.0
     */
    public function getTable($name = 'Query', $prefix = 'Administrator', $options = [])
    {
        return parent::getTable($name, $prefix, $options);
    }

    /**
     * Validates the form data, including the Twig template and the variables.
     *
     * @param   Form    $form   The form to validate against.
     * @param   array   $data   The data to validate.
     * @param   string  $group  The name of the field group to validate.
     *
     * @return  array|boolean  Array of filtered data if valid, false otherwise.
     *
     * @since   1.0.0
     */
    public function validate($form, $data, $group = null)
    {
        $validData = parent::validate($form, $data, $group);

        if ($validData === false) {
            return false;
        }

        $kurso = $this->getKurso();

        try {
            GraphQLClient::assertReadOnly((string) ($validData['graphql'] ?? ''));
        } catch (KursoException $e) {
            $this->setError(htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));

            return false;
        }

        try {
            $kurso->getClient()->evaluateVariables((string) ($validData['variables'] ?? ''));
        } catch (KursoException $e) {
            // Messages are rendered as HTML by the form controller; they may quote parts of the user's input.
            $this->setError(Text::sprintf('COM_KURSO_ERROR_VARIABLES', htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')));

            return false;
        }

        if (trim((string) ($validData['template'] ?? '')) !== '') {
            try {
                $kurso->getTwig()->validateTemplate((string) $validData['template']);
            } catch (KursoException $e) {
                $this->setError(Text::sprintf('COM_KURSO_ERROR_TEMPLATE_INVALID', htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')));

                return false;
            }
        }

        return $validData;
    }

    /**
     * Saves the query and creates its cache row.
     *
     * @param   array  $data  The form data.
     *
     * @return  boolean
     *
     * @since   1.0.0
     */
    public function save($data)
    {
        $input = Factory::getApplication()->getInput();

        // "Save as Copy": make title and alias unique.
        if ($input->get('task') === 'save2copy') {
            $original = $this->getTable();

            if ($original->load((int) $input->getInt('id')) && $data['title'] === $original->title) {
                $data['title'] = StringHelper::increment($data['title']);
            }

            $alias = QueryTable::normaliseAlias((string) ($data['alias'] ?: $data['title']));
            $table = $this->getTable();

            while ($table->load(['alias' => $alias])) {
                $alias = StringHelper::increment($alias, 'dash');
            }

            $data['alias'] = $alias;
            $data['state'] = 0;
        }

        $kurso      = $this->getKurso();
        $repository = $kurso->getRepository();
        $previous   = !empty($data['id']) ? $repository->findById((int) $data['id']) : null;

        if (!parent::save($data)) {
            return false;
        }

        $id = (int) $this->getState($this->getName() . '.id');

        if ($id <= 0) {
            return true;
        }

        $repository->ensureCacheRow($id);

        // New or changed query: fetch right away, so the site and the preview show data that matches the query.
        $changed = $previous === null
            || (string) $previous->graphql !== (string) ($data['graphql'] ?? '')
            || (string) $previous->variables !== (string) ($data['variables'] ?? '');

        if ($changed) {
            try {
                $kurso->getRefresher()->refreshById($id);
            } catch (KursoException $e) {
                Factory::getApplication()->enqueueMessage(
                    Text::sprintf('COM_KURSO_QUERY_SAVED_FETCH_FAILED', htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')),
                    'warning'
                );
            }
        }

        return true;
    }

    /**
     * Fetches the data of the given queries from the KURSO API now.
     *
     * @param   int[]  $pks  The query ids
     *
     * @return  array<int, string|null>  Error message per id (null on success)
     *
     * @since   1.0.0
     */
    public function refresh(array $pks): array
    {
        $results    = [];
        $kurso      = $this->getKurso();
        $repository = $kurso->getRepository();
        $refresher  = $kurso->getRefresher();

        foreach ($pks as $pk) {
            $pk    = (int) $pk;
            $query = $repository->findById($pk);

            if ($query === null) {
                $results[$pk] = Text::sprintf('COM_KURSO_ERROR_QUERY_ID_NOT_FOUND', $pk);

                continue;
            }

            try {
                $refresher->refresh($query);
                $results[$pk] = null;
            } catch (KursoException $e) {
                $results[$pk] = $query->title . ': ' . $e->getMessage();
            }
        }

        return $results;
    }

    /**
     * @param   int  $id  The query id
     *
     * @return  CacheEntry  The cached data and fetch status
     *
     * @since   1.0.0
     */
    public function getCacheEntry(int $id): CacheEntry
    {
        return $this->getKurso()->getRepository()->getCache($id);
    }

    /**
     * @return  KursoComponent
     *
     * @since   1.0.0
     */
    private function getKurso(): KursoComponent
    {
        return Factory::getApplication()->bootComponent('com_kurso');
    }
}
