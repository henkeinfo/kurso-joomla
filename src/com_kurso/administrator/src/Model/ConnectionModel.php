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
use Joomla\CMS\MVC\Model\FormModel;
use Kurso\Component\Kurso\Administrator\Exception\KursoException;
use Kurso\Component\Kurso\Administrator\Extension\KursoComponent;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Model for the KURSO API connection settings.
 *
 * @since  1.0.0
 */
class ConnectionModel extends FormModel
{
    /**
     * @param   array    $data      Data for the form.
     * @param   boolean  $loadData  True if the form is to load its own data.
     *
     * @return  Form|boolean
     *
     * @since   1.0.0
     */
    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm('com_kurso.connection', 'connection', ['control' => 'jform', 'load_data' => $loadData]);

        if (empty($form)) {
            return false;
        }

        $settings = $this->getKurso()->getSettings();

        if ($settings->isPasswordUnreadable()) {
            $form->setFieldAttribute('password', 'hint', 'COM_KURSO_CONNECTION_PASSWORD_HINT_UNREADABLE');
        } elseif ($settings->hasPassword()) {
            $form->setFieldAttribute('password', 'hint', 'COM_KURSO_CONNECTION_PASSWORD_HINT_SAVED');
        } else {
            $form->setFieldAttribute('password', 'required', 'true');
        }

        return $form;
    }

    /**
     * @return  array  The data for the form (never contains the password).
     *
     * @since   1.0.0
     */
    protected function loadFormData()
    {
        $data = (array) Factory::getApplication()->getUserState('com_kurso.edit.connection.data', []);

        if (empty($data)) {
            $settings = $this->getKurso()->getSettings();
            $data     = [
                'graphql_url' => $settings->getUrl(),
                'username'    => $settings->getUsername(),
            ];
        }

        $data['password'] = '';

        $this->preprocessData('com_kurso.connection', $data);

        return $data;
    }

    /**
     * Stores the connection settings.
     *
     * @param   array  $data  The validated form data
     *
     * @return  boolean
     *
     * @since   1.0.0
     */
    public function save(array $data): bool
    {
        try {
            $this->getKurso()->getSettings()->save(
                (string) ($data['graphql_url'] ?? ''),
                (string) ($data['username'] ?? ''),
                ($data['password'] ?? '') !== '' ? (string) $data['password'] : null
            );
        } catch (KursoException $e) {
            $this->setError($e->getMessage());

            return false;
        }

        return true;
    }

    /**
     * Tests the connection with the given values. An empty password means "use the stored password".
     *
     * @param   array  $data  The validated form data
     *
     * @return  void
     *
     * @throws  KursoException  If the connection fails
     *
     * @since   1.0.0
     */
    public function test(array $data): void
    {
        $kurso    = $this->getKurso();
        $password = (string) ($data['password'] ?? '');

        if ($password === '') {
            if ($kurso->getSettings()->isPasswordUnreadable()) {
                throw new KursoException(Text::_('COM_KURSO_ERROR_PASSWORD_UNREADABLE'));
            }

            $password = $kurso->getSettings()->getPassword();
        }

        $kurso->getClient()->testConnection(
            trim((string) ($data['graphql_url'] ?? '')),
            trim((string) ($data['username'] ?? '')),
            $password
        );
    }

    /**
     * @return  array{configured: bool, hasPassword: bool, unreadable: bool}  The status of the stored settings
     *
     * @since   1.0.0
     */
    public function getStatus(): array
    {
        $settings = $this->getKurso()->getSettings();

        return [
            'configured'  => $settings->isConfigured(),
            'hasPassword' => $settings->hasPassword(),
            'unreadable'  => $settings->isPasswordUnreadable(),
        ];
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
