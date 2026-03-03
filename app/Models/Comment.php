<?php
/**
 * Comment Model
 *
 * Database operations for the task_comments table.
 */

class Comment {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    private function execStmt(\PDOStatement $stmt, string $sql, array $params = []) {
        try {
            return $stmt->execute($params);
        } catch (\PDOException $e) {
            error_log("[DB EXCEPTION] " . $e->getMessage() . " | SQL: " . $sql . " | PARAMS: " . json_encode($params));
            throw $e;
        }
    }

    public function create($task_id, $user_id, $comment_text) {
        $sql = "INSERT INTO task_comments (task_id, user_id, comment) 
                VALUES (:task_id, :user_id, :comment)";
        $stmt = $this->conn->prepare($sql);

        $params = [
            ':task_id' => $task_id,
            ':user_id' => $user_id,
            ':comment' => $comment_text
        ];

        if ($this->execStmt($stmt, $sql, $params)) {
            return $this->conn->lastInsertId();
        }
        return false;
    }

    public function findById($comment_id) {
        $sql = "SELECT tc.*, u.username, u.profile_image 
                FROM task_comments tc
                JOIN users u ON tc.user_id = u.id
                WHERE tc.id = :comment_id";
        $stmt = $this->conn->prepare($sql);
        $params = [':comment_id' => $comment_id];
        $this->execStmt($stmt, $sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByTaskId($task_id) {
        $sql = "SELECT tc.*, u.username, u.profile_image 
                FROM task_comments tc
                JOIN users u ON tc.user_id = u.id
                WHERE tc.task_id = :task_id
                ORDER BY tc.created_at ASC";

        $stmt = $this->conn->prepare($sql);
        $params = [':task_id' => $task_id];
        $this->execStmt($stmt, $sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
