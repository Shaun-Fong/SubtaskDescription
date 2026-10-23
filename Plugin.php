<?php

namespace Kanboard\Plugin\Subtaskdescription;

use Kanboard\Core\Plugin\Base;
use Kanboard\Core\Security\Role;
use Kanboard\Plugin\Subtaskdescription\Api\Procedure\SubtaskDescriptionProcedure;
use Kanboard\Core\Translator;
use Kanboard\Model\TaskModel;
use Kanboard\Model\SubtaskModel;
use PicoDb\Table;
use PicoDb\Database;
use JsonRPC\Server;


class Plugin extends Base
{
    public function initialize()
    {

        //Model
        $this->hook->on('model:subtask:creation:prepare', array($this, 'beforeCreate'));
        $this->hook->on('model:subtask:modification:prepare', array($this, 'beforeSave'));

        //API - same project role as the core createSubtask/updateSubtask
        $this->api->getProcedureHandler()->withObject(new SubtaskDescriptionProcedure($this->container));
        $this->apiProjectAccessMap->add('SubtaskDescriptionProcedure', array('createSubtaskWithDescription', 'updateSubtaskDescription'), Role::PROJECT_MEMBER);

        //Forms
        $this->template->hook->attach('template:subtask:form:create', 'Subtaskdescription:subtask/form');
        $this->template->hook->attach('template:subtask:form:edit', 'Subtaskdescription:subtask/form');

        //Task Details
        $this->template->hook->attach('template:subtask:table:header:before-timetracking', 'Subtaskdescription:subtask/table_header');
        $this->template->hook->attach('template:subtask:table:rows', 'Subtaskdescription:subtask/table_rows');

        //Dashboard - Removed after 1.0.41
        $wasmaster = APP_VERSION;
        
        if (strpos(APP_VERSION, 'master') !== false && file_exists('ChangeLog')) { $wasmaster = trim(file_get_contents('ChangeLog', false, null, 8, 6), ' '); }
        
        if (version_compare($wasmaster, '1.0.40') <= 0) { 
          $this->template->hook->attach('template:dashboard:subtasks:header:before-timetracking', 'Subtaskdescription:subtask/table_header');
          $this->template->hook->attach('template:dashboard:subtasks:rows', 'Subtaskdescription:subtask/table_rows');
        }

        //Board Tooltip
        $this->template->hook->attach('template:board:tooltip:subtasks:header:before-assignee', 'Subtaskdescription:subtask/table_header');
        $this->template->hook->attach('template:board:tooltip:subtasks:rows', 'Subtaskdescription:subtask/table_rows');
        
    }
    public function onStartup()
    {
        Translator::load($this->languageModel->getCurrentLanguage(), __DIR__.'/Locale');
    }

    public function beforeSave(array &$values)
    {
        // Store empty descriptions as empty text (the old resetFields() call turned them into "0")
        if (array_key_exists('due_description', $values) && $values['due_description'] === null) {
            $values['due_description'] = '';
        }
    }

    public function beforeCreate(array &$values)
    {
        // The API (createSubtask) never passes due_description, so always provide a value on insert
        if (! isset($values['due_description'])) {
            $values['due_description'] = '';
        }
    }

    public function getPluginName()
    {
        return 'SubtaskDescription';
    }

    public function getPluginDescription()
    {
        return t('Add a description field to subtasks');
    }

    public function getPluginAuthor()
    {
        return 'Shaun Fong';
    }

    public function getPluginVersion()
    {
        return '1.1.2';
    }

    public function getPluginHomepage()
    {
        return 'https://github.com/shaun-fong/Subtaskdescription';
    }
}
