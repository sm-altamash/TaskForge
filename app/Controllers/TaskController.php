<?php
/**
 * Task Controller
 *
 * Handles all task CRUD, sharing, and reorder endpoints.
 */

require_once __DIR__ . '/../Services/TaskService.php';

class TaskController {
    private $taskService;

    public function __construct($db) {
        $this->taskService = new TaskService($db);
    }

    private function sendError($e) {
        $status_code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        http_response_code($status_code);
        echo json_encode(['error' => $e->getMessage()]);
    }

    // ──────────────────────────────
    //  CRUD
    // ──────────────────────────────

    /** GET /api/v1/tasks */
    public function getAll($user_id) {
        try {
            $tasks = $this->taskService->getTasksForUser($user_id);
            http_response_code(200);
            echo json_encode($tasks);
        } catch (Exception $e) {
            $this->sendError($e);
        }
    }

    /** GET /api/v1/tasks/{id} */
    public function getOne($task_id, $user_id) {
        try {
            $task = $this->taskService->getTaskById($task_id, $user_id);
            http_response_code(200);
            echo json_encode($task);
        } catch (Exception $e) {
            $this->sendError($e);
        }
    }

    /** POST /api/v1/tasks */
    public function create($user_id) {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data.']);
            return;
        }

        try {
            $newTask = $this->taskService->createTask($data, $user_id);
            http_response_code(201);
            echo json_encode($newTask);
        } catch (Exception $e) {
            $this->sendError($e);
        }
    }

    /** PUT|PATCH /api/v1/tasks/{id} */
    public function update($task_id, $user_id) {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data.']);
            return;
        }

        try {
            $updatedTask = $this->taskService->updateTask($task_id, $data, $user_id);
            http_response_code(200);
            echo json_encode($updatedTask);
        } catch (Exception $e) {
            $this->sendError($e);
        }
    }

    /** DELETE /api/v1/tasks/{id} */
    public function delete($task_id, $user_id) {
        try {
            $this->taskService->deleteTask($task_id, $user_id);
            http_response_code(200);
            echo json_encode(['message' => 'Task deleted successfully.']);
        } catch (Exception $e) {
            $this->sendError($e);
        }
    }

    /** POST /api/v1/tasks/reorder */
    public function reorder($user_id) {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['ordered_ids']) || !is_array($data['ordered_ids'])) {
            http_response_code(400);
            echo json_encode(['error' => '"ordered_ids" array is required.']);
            return;
        }

        try {
            $this->taskService->reorderTasks($data['ordered_ids'], $user_id);
            http_response_code(200);
            echo json_encode(['message' => 'Task order updated successfully.']);
        } catch (Exception $e) {
            $this->sendError($e);
        }
    }

    // ──────────────────────────────
    //  Subtasks
    // ──────────────────────────────

    /** GET /api/v1/tasks/{id}/subtasks */
    public function getSubtasks($task_id, $user_id) {
        try {
            $subtasks = $this->taskService->getSubtasks($task_id, $user_id);
            http_response_code(200);
            echo json_encode($subtasks);
        } catch (Exception $e) {
            $this->sendError($e);
        }
    }

    /** POST /api/v1/tasks/{id}/subtasks */
    public function createSubtask($task_id, $user_id) {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data.']);
            return;
        }

        try {
            $newSubtask = $this->taskService->createSubtask($task_id, $data, $user_id);
            http_response_code(201);
            echo json_encode($newSubtask);
        } catch (Exception $e) {
            $this->sendError($e);
        }
    }

    // ──────────────────────────────
    //  Sharing
    // ──────────────────────────────

    /** GET /api/v1/tasks/{id}/shares */
    public function getShares($task_id, $user_id) {
        try {
            $shares = $this->taskService->getTaskShares($task_id, $user_id);
            http_response_code(200);
            echo json_encode($shares);
        } catch (Exception $e) {
            $this->sendError($e);
        }
    }

    /** POST /api/v1/tasks/{id}/shares */
    public function share($task_id, $user_id) {
        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['team_id'])) {
            http_response_code(400);
            echo json_encode(['error' => 'team_id is required.']);
            return;
        }

        try {
            $shares = $this->taskService->shareTask(
                $task_id,
                $data['team_id'],
                $data['permission'] ?? 'View',
                $user_id
            );
            http_response_code(200);
            echo json_encode($shares);
        } catch (Exception $e) {
            $this->sendError($e);
        }
    }

    /** DELETE /api/v1/tasks/{id}/shares/{teamId} */
    public function unshare($task_id, $team_id, $user_id) {
        try {
            $shares = $this->taskService->unshareTask($task_id, $team_id, $user_id);
            http_response_code(200);
            echo json_encode($shares);
        } catch (Exception $e) {
            $this->sendError($e);
        }
    }
}
