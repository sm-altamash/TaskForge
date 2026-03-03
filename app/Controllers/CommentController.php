<?php
/**
 * Comment Controller
 *
 * Handles task comment endpoints (GET and POST).
 */

require_once __DIR__ . '/../Services/CommentService.php';
require_once __DIR__ . '/../Services/TaskService.php';

class CommentController {
    private $commentService;

    public function __construct($db) {
        $taskService = new TaskService($db);
        $this->commentService = new CommentService($db, $taskService);
    }

    private function sendError($e) {
        $status_code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        http_response_code($status_code);
        echo json_encode(['error' => $e->getMessage()]);
    }

    /**
     * GET /api/v1/tasks/{id}/comments
     */
    public function getTaskComments($task_id, $user_id) {
        try {
            $comments = $this->commentService->getComments($task_id, $user_id);
            http_response_code(200);
            echo json_encode($comments);
        } catch (Exception $e) {
            $this->sendError($e);
        }
    }

    /**
     * POST /api/v1/tasks/{id}/comments
     */
    public function postTaskComment($task_id, $user_id) {
        $data = json_decode(file_get_contents('php://input'), true);

        try {
            $newComment = $this->commentService->postComment(
                $task_id,
                $user_id,
                $data['comment'] ?? ''
            );
            http_response_code(201);
            echo json_encode($newComment);
        } catch (Exception $e) {
            $this->sendError($e);
        }
    }
}
