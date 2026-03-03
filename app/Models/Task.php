<?php
/**
 * Task Model
 *
 * Database operations for the tasks and task_shares tables.
 */

class Task {
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

    public function create($data, $user_id) {
        $sql = "INSERT INTO tasks (user_id, parent_id, title, description, category, priority, due_date, tags) 
                VALUES (:user_id, :parent_id, :title, :description, :category, :priority, :due_date, :tags)";
        $stmt = $this->conn->prepare($sql);

        $params = [
            ':user_id'     => $user_id,
            ':parent_id'   => $data['parent_id'] ?? null,
            ':title'       => $data['title'] ?? null,
            ':description' => $data['description'] ?? null,
            ':category'    => $data['category'] ?? null,
            ':priority'    => $data['priority'] ?? null,
            ':due_date'    => $data['due_date'] ?? null,
            ':tags'        => $data['tags'] ?? null
        ];

        if ($this->execStmt($stmt, $sql, $params)) {
            return $this->findById($this->conn->lastInsertId());
        }
        return false;
    }

    public function findSubtasks($parent_id) {
        $sql = "SELECT * FROM tasks WHERE parent_id = :parent_id AND deleted_at IS NULL ORDER BY sort_order ASC, created_at ASC";
        $stmt = $this->conn->prepare($sql);
        $params = [':parent_id' => $parent_id];
        $this->execStmt($stmt, $sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findAllByUserId($user_id) {
        $sql = "
            SELECT DISTINCT 
                t.*, 
                COUNT(DISTINCT tc.id) as comment_count 
            FROM tasks t
            LEFT JOIN task_shares ts ON t.id = ts.task_id
            LEFT JOIN team_members tm ON ts.team_id = tm.team_id
            LEFT JOIN task_comments tc ON tc.task_id = t.id
            WHERE t.deleted_at IS NULL
              AND (t.user_id = :user_id_owner OR tm.user_id = :user_id_member)
            GROUP BY t.id
            ORDER BY t.sort_order ASC, t.created_at DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $params = [
            ':user_id_owner'  => $user_id,
            ':user_id_member' => $user_id
        ];
        $this->execStmt($stmt, $sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function search($user_id, $query) {
        $sql = "
            SELECT DISTINCT t.*, COUNT(DISTINCT tc.id) as comment_count
            FROM tasks t
            LEFT JOIN task_shares ts ON t.id = ts.task_id
            LEFT JOIN team_members tm ON ts.team_id = tm.team_id
            LEFT JOIN task_comments tc ON tc.task_id = t.id
            WHERE t.deleted_at IS NULL
              AND t.parent_id IS NULL
              AND (t.user_id = :user_id_owner OR tm.user_id = :user_id_member)
              AND MATCH(t.title, t.description) AGAINST(:query IN BOOLEAN MODE)
            GROUP BY t.id
            ORDER BY t.sort_order ASC
        ";
        $stmt = $this->conn->prepare($sql);
        $params = [':user_id_owner' => $user_id, ':user_id_member' => $user_id, ':query' => $query . '*'];
        $this->execStmt($stmt, $sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById($task_id) {
        $sql = "SELECT * FROM tasks WHERE id = :task_id AND deleted_at IS NULL";
        $stmt = $this->conn->prepare($sql);
        $params = [':task_id' => $task_id];
        $this->execStmt($stmt, $sql, $params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function update($task_id, $data, $user_id) {
        if (empty($data) || !is_array($data)) return false;

        $fields = [];
        $params = [];
        foreach ($data as $key => $value) {
            $fields[] = "`$key` = :$key";
            $params[":$key"] = $value;
        }
        $set_clause = implode(', ', $fields);

        $sql = "UPDATE tasks SET $set_clause WHERE id = :task_id AND user_id = :user_id";
        $stmt = $this->conn->prepare($sql);

        $params[':task_id'] = $task_id;
        $params[':user_id'] = $user_id;

        if ($this->execStmt($stmt, $sql, $params)) {
            return $this->findById($task_id);
        }
        return false;
    }

    public function delete($task_id, $user_id) {
        $sql = "UPDATE tasks SET deleted_at = CURRENT_TIMESTAMP WHERE id = :task_id AND user_id = :user_id";
        $stmt = $this->conn->prepare($sql);
        $params = [':task_id' => $task_id, ':user_id' => $user_id];
        return $this->execStmt($stmt, $sql, $params);
    }

    public function updateSortOrder($ordered_ids, $user_id) {
        if (empty($ordered_ids)) { return true; }

        $case_sql = "SET sort_order = CASE id \n";
        $params = [];
        $i = 0;

        foreach ($ordered_ids as $id) {
            $case_sql .= "WHEN ? THEN ? \n";
            $params[] = $id;
            $params[] = $i;
            $i++;
        }
        $case_sql .= "END";

        $in_placeholders = implode(',', array_fill(0, count($ordered_ids), '?'));
        foreach ($ordered_ids as $id) { $params[] = $id; }
        $params[] = $user_id;

        $sql = "UPDATE tasks $case_sql WHERE id IN ($in_placeholders) AND user_id = ?";
        $stmt = $this->conn->prepare($sql);

        try {
            return $stmt->execute($params);
        } catch (\PDOException $e) {
            error_log("[DB EXCEPTION] updateSortOrder: " . $e->getMessage());
            return false;
        }
    }

    public function getSharedTeams($task_id) {
        $sql = "SELECT t.id, t.team_name, ts.permission 
                FROM task_shares ts
                JOIN teams t ON ts.team_id = t.id
                WHERE ts.task_id = :task_id";
        $stmt = $this->conn->prepare($sql);
        $params = [':task_id' => $task_id];
        $this->execStmt($stmt, $sql, $params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function shareWithTeam($task_id, $team_id, $permission, $shared_by_user_id) {
        $sql = "INSERT INTO task_shares (task_id, team_id, permission, shared_by_user_id)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE permission = ?";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            $task_id,
            $team_id,
            $permission,
            $shared_by_user_id,
            $permission
        ]);
    }

    public function unshareFromTeam($task_id, $team_id) {
        $sql = "DELETE FROM task_shares WHERE task_id = :task_id AND team_id = :team_id";
        $stmt = $this->conn->prepare($sql);
        $params = [':task_id' => $task_id, ':team_id' => $team_id];
        return $this->execStmt($stmt, $sql, $params);
    }
}
