<?php
/**
 * Comment Service
 *
 * Business logic for task comments with access control and activity logging.
 */

require_once __DIR__ . '/../Models/Comment.php';
require_once __DIR__ . '/TaskService.php';
require_once __DIR__ . '/ActivityLogger.php';

class CommentService {
    private $commentModel;
    private $taskService;
    private $db;

    public function __construct($db, $taskService) {
        $this->db = $db;
        $this->commentModel = new Comment($db);
        $this->taskService  = $taskService;
    }

    public function getComments($task_id, $user_id) {
        $this->taskService->getTaskById($task_id, $user_id);
        return $this->commentModel->findByTaskId($task_id);
    }

    public function postComment($task_id, $user_id, $comment_text) {
        $this->taskService->getTaskById($task_id, $user_id);

        if (empty(trim($comment_text))) {
            throw new Exception("Comment text cannot be empty.", 400);
        }

        $newCommentId = $this->commentModel->create($task_id, $user_id, $comment_text);
        if (!$newCommentId) {
            throw new Exception("Failed to save comment.", 500);
        }

        try {
            $details = json_encode(['comment_id' => $newCommentId]);
            ActivityLogger::log($this->db, $user_id, 'new_comment', $task_id, null, $details);
        } catch (\Throwable $e) {
            error_log("ActivityLogger::log failed on postComment: " . $e->getMessage());
        }

        return $this->commentModel->findById($newCommentId);
    }
}
