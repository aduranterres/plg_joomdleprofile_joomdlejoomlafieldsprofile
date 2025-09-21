<?php

/**
 * @package    plg_joomdleprofile_joomdlejoomlafieldsprofile
 * @author     Antonio Duran <antonio@joomdle.com>
 * @copyright  2025 Antonio Duran
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\Plugin\Joomdleprofile\Joomdlejoomlafieldsprofile\Extension;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Event\Privacy\ExportRequestEvent;
use Joomla\CMS\Event\Privacy\RemoveDataEvent;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\User\UserHelper;
use Joomla\Event\Event;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\ParameterType;
use Joomdle\Component\Joomdle\Administrator\Helper\MappingsHelper;

final class Joomdlejoomlafieldsprofile extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    /**
     * Load plugin language files.
     */
    protected $autoloadLanguage = true;

    private $additional_data_source = 'joomlafields';

    /**
     * @return  array
     *
     * @since   4.1.3
     */
    public static function getSubscribedEvents(): array
    {
        return [
            'onJoomdleGetAdditionalDataSource' => 'onJoomdleGetAdditionalDataSource',
            'onJoomdleGetFields' => 'onJoomdleGetFields',
            'onJoomdleGetFieldName' => 'onJoomdleGetFieldName',
            'onJoomdleGetUserInfo' => 'onJoomdleGetUserInfo',
            'onJoomdleCreateAdditionalProfile' => 'onJoomdleCreateAdditionalProfile',
            'onJoomdleSaveUserInfo' => 'onJoomdleSaveUserInfo',
            'onJoomdleGetLoginUrl' => 'onJoomdleGetLoginUrl',
        ];
    }

    private function integrationEnabled()
    {
        // Don't run if not configured in Joomdle
        $params = ComponentHelper::getParams('com_joomdle');
        $additional_data_source = $params->get('additional_data_source');
        return  ($additional_data_source == $this->additional_data_source);
    }

    private function isSecondaryDataSource()
    {
        // Don't run if not configured in Joomdle
        $isSecondaryDataSource = $this->params->get('isSecondaryDataSource');
        return  ($isSecondaryDataSource);
    }

    // Joomdle events
    public function onJoomdleGetAdditionalDataSource(Event $event)
    {
        // Add to results instead of setting the value, so we can see the data returned by several plugins
        $results = $event->getArgument('results') ?? [];

        $results[] = [$this->additional_data_source => "Joomla Fields"];

        $event->setArgument('results', $results);
    }

    public function onJoomdleGetFields(Event $event)
    {
        if (!$this->integrationEnabled()) 
            return array ();

        $fields = array ();

        $db = $this->getDatabase();

        $query = $db->createQuery();
        $query->select('*')
            ->from($db->quoteName('#__fields'))
            ->where($db->quoteName('context') . ' = :context');

        $context = 'com_users.user';
        $query->bind(':context', $context, ParameterType::STRING);

        $db->setQuery($query);
        $field_objects = $db->loadObjectList();

        $fields = array ();
        $i = 0;
        foreach ($field_objects as $fo) {
            $fields[$i] = new \stdClass();
            $fields[$i]->name =  $fo->name;
            $fields[$i]->id =  $fo->id;
            $i++;
        }

        $event->setArgument('results', [$fields]);
    }

    public function onJoomdleGetFieldName(Event $event)
    {
        if (!$this->integrationEnabled()) {
            return false;
        }

        $field = $event->getArgument('field');

        $db = $this->getDatabase();

        $query = $db->createQuery();

        $query->select($db->quoteName('name'))
            ->from($db->quoteName('#__fields'))
            ->where($db->quoteName('context') . ' = "com_users.user"')
            ->where($db->quoteName('id') . ' = :field');

        $query->bind(':field', $field);

        $db->setQuery($query);
        $field_name = $db->loadResult();

        $event->setArgument('results', [$field_name]);
    }

    public function onJoomdleGetUserInfo(Event $event)
    {
        if ((!$this->integrationEnabled()) && (!$this->isSecondaryDataSource())) {
            return array ();
        }

        $db = $this->getDatabase();

        $username = $event->getArgument('username');
        $id = UserHelper::getUserId($username);
        $user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($id);

        if (!$this->isSecondaryDataSource()) {
            $user_info['firstname'] = MappingsHelper::getFirstname($user->name);
            $user_info['lastname'] = MappingsHelper::getLastname($user->name);
        }

        $mappings = MappingsHelper::getAppMappings($this->additional_data_source);

        if (is_array($mappings)) {
            foreach ($mappings as $mapping) {
                $value = $this->getFieldValue($mapping->joomla_field, $user->id);
                $user_info[$mapping->moodle_field] = $value;
            }
        }

        $event->setArgument('results', [$user_info]);
    }

    public function getFieldValue($field, $user_id)
    {
        $db = $this->getDatabase();

        $query = $db->createQuery();
        $query->select($db->quoteName('value'))
            ->from($db->quoteName('#__fields_values'))
            ->where($db->quoteName('field_id') . ' = :field_id')
            ->where($db->quoteName('item_id') . ' = :item_id');

        $query->bind(':field_id', $field, ParameterType::INTEGER);
        $query->bind(':item_id', $user_id, ParameterType::INTEGER);

        $db->setQuery($query);
        $value = $db->loadResult();

        $query = $db->createQuery();
        $query->select($db->quoteName('type'))
            ->from($db->quoteName('#__fields'))
            ->where($db->quoteName('id') . ' = :field_id');

        $query->bind(':field_id', $field, ParameterType::INTEGER);
        $db->setQuery($query);
        $type = $db->loadResult();

        switch ($type) {
            case 'calendar':
                $value = strtotime($value);
                break;
            default:
                break;
        }

        return $value;
    }


    public function onJoomdleCreateAdditionalProfile($user_info)
    {
        if (!$this->integrationEnabled()) {
            return false;
        }

        // Nothing to do
        return true;
    }

    public function onJoomdleSaveUserInfo(Event $event)
    {
        if ((!$this->integrationEnabled()) && (!$this->isSecondaryDataSource())) {
            return array ();
        }

        $db = $this->getDatabase();

        $user_info = $event->getArgument('user_info');

        $username = $user_info['username'];
        $id = UserHelper::getUserId($username);
        $user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($id);

        $mappings = MappingsHelper::getAppMappings($this->additional_data_source);

        foreach ($mappings as $mapping) {
            $additional_info[$mapping->joomla_field] = $user_info[$mapping->moodle_field];
            // Custom moodle fields
            if (strncmp($mapping->moodle_field, 'cf_', 3) == 0) {
                $data = MappingsHelper::getMoodleCustomFieldValue($user_info, $mapping->moodle_field);
                $this->setFieldValue($mapping->joomla_field, $data, $id);
            } else {
                $this->setFieldValue($mapping->joomla_field, $user_info[$mapping->moodle_field], $id);
            }
        }
    }

    public function setFieldValue($field, $value, $user_id)
    {
        $db = $this->getDatabase();

        $query = $db->createQuery()
            ->select('*')
            ->from($db->quoteName('#__fields_values'))
            ->where($db->quoteName('field_id') . ' = :field_id')
            ->where($db->quoteName('item_id') . ' = :item_id');

        $query->bind(':field_id', $field, ParameterType::INTEGER);
        $query->bind(':item_id', $user_id, ParameterType::INTEGER);

        $db->setQuery($query);
        $vals = $db->loadAssocList();

        $query = $db->createQuery()
            ->select($db->quoteName('type'))
            ->from($db->quoteName('#__fields'))
            ->where($db->quoteName('id') . ' = :field_id');

        $query->bind(':field_id', $field, ParameterType::INTEGER);

        $db->setQuery($query);
        $type = $db->loadResult();

        switch ($type) {
            case 'calendar':
                $value = date('Y-m-d', $value);
                break;
            default:
                break;
        }

        if (count($vals) > 0) {
            // Update entry
            $query = $db->createQuery()
                ->update($db->quoteName('#__fields_values'))
                ->set($db->quoteName('value') . ' = :value')
                ->where($db->quoteName('field_id') . ' = :field_id')
                ->where($db->quoteName('item_id') . ' = :item_id');

            $query->bind(':value', $value, ParameterType::STRING);
            $query->bind(':field_id', $field, ParameterType::INTEGER);
            $query->bind(':item_id', $user_id, ParameterType::INTEGER);

            $db->setQuery($query);
            $value = $db->execute();
        } else {
            // Add new entry
            $f = new \stdClass();
            $f->field_id = $field;
            $f->item_id = $user_id;
            $f->value = $value;

            $db->insertObject('#__fields_values', $f);
        }

        return true;
    }

    public function onJoomdleGetLoginUrl(Event $event)
    {
        if (!$this->integrationEnabled()) {
            return false;
        }

        $return = $event->getArgument('return');

        $url = "index.php?option=com_users&view=login&return=$return";

        $event->setArgument('results', [$url]);
    }
}
