<?php

namespace Kanboard\Plugin\Subtaskdescription\Api\Procedure;

use Kanboard\Api\Authorization\TaskAuthorization;
use Kanboard\Api\Procedure\BaseProcedure;

/**
 * Subtask API procedures with description support
 *
 * The core createSubtask/updateSubtask methods only accept their own parameters,
 * so due_description can only be set through these methods.
 */
class SubtaskDescriptionProcedure extends BaseProcedure
{
    public function createSubtaskWithDescription($task_id, $title, $due_description = '', $user_id = 0, $time_estimated = 0, $time_spent = 0, $status = 0)
    {
        TaskAuthorization::getInstance($this->container)->check($this->getClassName(), 'createSubtaskWithDescription', $task_id);

        $values = array(
            'title' => $title,
            'task_id' => $task_id,
            'user_id' => $user_id,
            'time_estimated' => $time_estimated,
            'time_spent' => $time_spent,
            'status' => $status,
            'due_description' => (string) $due_description,
        );

        list($valid, ) = $this->subtaskValidator->validateCreation($values);
        return $valid ? $this->subtaskModel->create($values) : false;
    }

    public function updateSubtaskDescription($id, $task_id, $due_description)
    {
        TaskAuthorization::getInstance($this->container)->check($this->getClassName(), 'updateSubtaskDescription', $task_id);

        $subtask = $this->subtaskModel->getById($id);
        if (empty($subtask) || (int) $subtask['task_id'] !== (int) $task_id) {
            return false;
        }

        $values = array(
            'id' => $id,
            'task_id' => $task_id,
            'due_description' => (string) $due_description,
        );

        list($valid, ) = $this->subtaskValidator->validateApiModification($values);
        return $valid && $this->subtaskModel->update($values);
    }
}
