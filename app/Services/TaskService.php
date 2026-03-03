<?php
/**
 * Task Service
 *
 * Business logic for task CRUD, sharing, and reordering.
 */

require_once __DIR__ . '/../Models/Task.php';
require_once __DIR__ . '/../Models/Team.php';
require_once __DIR__ . '/ActivityLogger.php';

class TaskService {
    private $taskModel;
    private $teamModel;
    private $db;

    public function __construct($db) {
        $this->db        = $db;
        $this->taskModel = new Task($db);
        $this->teamModel = new Team($db);
    }

    private function canUserAccessTask($task_id, $user_id) {
        $all_my_tasks = $this->taskModel->findAllByUserId($user_id);
        foreach ($all_my_tasks as $task) {
            if ((int)$task['id'] === (int)$task_id) {
                return $task;
            }
        }
        return false;
    }

    public function getTasksForUser($user_id) {
        return $this->taskModel->findAllByUserId($user_id);
    }

    public function getSubtasks($parent_task_id, $user_id) {
        $this->getTaskById($parent_task_id, $user_id);
        return $this->taskModel->findSubtasks($parent_task_id);
    }

    public function createSubtask($parent_task_id, $data, $user_id) {
        $parent = $this->getTaskById($parent_task_id, $user_id);
        if ((int)$parent['user_id'] !== (int)$user_id) {
            throw new Exception("You do not own this task.", 403);
        }
        if (empty($data['title'])) {
            throw new Exception("Subtask title is required.", 400);
        }
        $subtaskData = [
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'category'    => $parent['category'] ?? 'Uncompleted',
            'priority'    => $data['priority'] ?? $parent['priority'] ?? 'Medium',
            'due_date'    => $data['due_date'] ?? null,
            'tags'        => isset($data['tags']) ? json_encode($data['tags']) : null,
            'parent_id'   => $parent_task_id
        ];
        $newSubtask = $this->taskModel->create($subtaskData, $user_id);
        if (!$newSubtask) {
            throw new Exception("Failed to create subtask.", 500);
        }
        return $newSubtask;
    }

    public function getTaskById($task_id, $user_id) {
        $task = $this->canUserAccessTask($task_id, $user_id);
        if (!$task) {
            throw new Exception("Task not found or you do not have permission.", 404);
        }
        return $task;
    }

    public function createTask($data, $user_id) {
        if (empty($data['title'])) {
            throw new Exception("Task title is required.", 400);
        }

        $taskData = [
            'title'       => $data['title'],
            'description' => $data['description'] ?? null,
            'category'    => $data['category'] ?? 'Uncompleted',
            'priority'    => $data['priority'] ?? 'Medium',
            'due_date'    => !empty($data['due_date']) ? $data['due_date'] : null,
            'tags'        => isset($data['tags']) ? json_encode($data['tags']) : null
        ];

        $newTask = $this->taskModel->create($taskData, $user_id);
        if (!$newTask) {
            throw new Exception("Failed to create task in database.", 500);
        }

        ActivityLogger::log($this->db, $user_id, 'created_task', $newTask['id']);
        return $newTask;
    }

    public function updateTask($task_id, $data, $user_id) {
        $task = $this->getTaskById($task_id, $user_id);

        if ((int)$task['user_id'] !== (int)$user_id) {
            throw new Exception("You do not have permission to edit this task.", 403);
        }

        $cleanData = [];
        foreach ($data as $key => $value) {
            if (in_array($key, ['title', 'description', 'category', 'priority', 'due_date', 'tags', 'is_completed'])) {
                $cleanData[$key] = $value;
            }
        }

        if (isset($cleanData['is_completed'])) {
            $cleanData['completed_at'] = $cleanData['is_completed'] ? date('Y-m-d H:i:s') : null;
            if (!isset($data['category'])) {
                $cleanData['category'] = $cleanData['is_completed'] ? 'Completed' : 'Uncompleted';
            }
            if ($cleanData['is_completed'] && !$task['is_completed']) {
                ActivityLogger::log($this->db, $user_id, 'completed_task', $task_id);
            }
        }

        if (isset($cleanData['tags'])) {
            $cleanData['tags'] = json_encode($cleanData['tags']);
        }

        if (empty($cleanData)) {
            return $task;
        }

        $updatedTask = $this->taskModel->update($task_id, $cleanData, $user_id);
        if (!$updatedTask) {
            throw new Exception("Failed to update task.", 500);
        }

        return $updatedTask;
    }

    public function deleteTask($task_id, $user_id) {
        $task = $this->getTaskById($task_id, $user_id);
        if ((int)$task['user_id'] !== (int)$user_id) {
            throw new Exception("You do not have permission to delete this task.", 403);
        }
        if (!$this->taskModel->delete($task_id, $user_id)) {
            throw new Exception("Failed to delete task.", 500);
        }
        return true;
    }

    public function reorderTasks($ordered_ids, $user_id) {
        if (empty($ordered_ids)) {
            return true;
        }
        $sanitized_ids = array_map('intval', $ordered_ids);
        if (!$this->taskModel->updateSortOrder($sanitized_ids, $user_id)) {
            throw new Exception("Failed to update task order.", 500);
        }
        return true;
    }

    public function getTaskShares($task_id, $user_id) {
        $this->getTaskById($task_id, $user_id);
        return $this->taskModel->getSharedTeams($task_id);
    }

    public function shareTask($task_id, $team_id, $permission, $user_id) {
        $task = $this->getTaskById($task_id, $user_id);
        if ((int)$task['user_id'] !== (int)$user_id) {
            throw new Exception("You do not own this task, so you cannot share it.", 403);
        }
        if (!$this->teamModel->isUserAdminOrOwner($team_id, $user_id)) {
            throw new Exception("You are not an admin of the team you are trying to share with.", 403);
        }
        if (!$this->taskModel->shareWithTeam($task_id, $team_id, $permission, $user_id)) {
            throw new Exception("Failed to share task.", 500);
        }

        ActivityLogger::log($this->db, $user_id, 'shared_task', $task_id, $team_id, json_encode(['permission' => $permission]));
        return $this->getTaskShares($task_id, $user_id);
    }

    public function unshareTask($task_id, $team_id, $user_id) {
        $task = $this->getTaskById($task_id, $user_id);
        if ((int)$task['user_id'] !== (int)$user_id) {
            throw new Exception("You do not own this task, so you cannot unshare it.", 403);
        }
        if (!$this->taskModel->unshareFromTeam($task_id, $team_id)) {
            throw new Exception("Failed to un-share task.", 500);
        }
        return $this->getTaskShares($task_id, $user_id);
    }
}
