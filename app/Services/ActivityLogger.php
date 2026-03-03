<?php
/**
 * Activity Logger Service
 *
 * Creates activity log entries and targeted notifications
 * for team collaboration events.
 */

class ActivityLogger {

    public static function log(\PDO $db, $user_id, $action, $task_id = null, $team_id = null, $details = null) {
        $sql = "INSERT INTO activity_logs (user_id, task_id, team_id, action, details)
                VALUES (:user_id, :task_id, :team_id, :action, :details)";

        try {
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':user_id', $user_id, \PDO::PARAM_INT);
            $stmt->bindParam(':task_id', $task_id, $task_id === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $stmt->bindParam(':team_id', $team_id, $team_id === null ? \PDO::PARAM_NULL : \PDO::PARAM_INT);
            $stmt->bindParam(':action', $action, \PDO::PARAM_STR);
            $stmt->bindParam(':details', $details, $details === null ? \PDO::PARAM_NULL : \PDO::PARAM_STR);

            if ($stmt->execute()) {
                $activity_log_id = $db->lastInsertId();
                self::createNotifications($db, $activity_log_id, $user_id, $action, $task_id, $team_id);
            }
        } catch (\PDOException $e) {
            error_log("ActivityLogger Failed: " . $e->getMessage());
        }
    }

    private static function createNotifications(\PDO $db, $activity_log_id, $actor_user_id, $action, $task_id, $team_id) {
        $users_to_notify = [];

        switch ($action) {
            case 'new_comment':
                $users_to_notify = self::getUsersForTaskNotification($db, $task_id, $actor_user_id);
                break;

            case 'shared_task':
                $users_to_notify = self::getTeamMembers($db, $team_id, $actor_user_id);
                break;

            case 'added_member':
                $details = json_decode(self::getActivityDetails($db, $activity_log_id), true);
                if (isset($details['added_user_id'])) {
                    $users_to_notify[] = $details['added_user_id'];
                }
                break;

            case 'completed_task':
                $users_to_notify = self::getUsersForTaskNotification($db, $task_id, $actor_user_id);
                break;
        }

        if (!empty($users_to_notify)) {
            $sql = "INSERT INTO notifications (user_id, activity_log_id) VALUES (:user_id, :activity_log_id)";
            $stmt = $db->prepare($sql);

            foreach (array_unique($users_to_notify) as $user_id) {
                if ($user_id != $actor_user_id) {
                    $stmt->execute([':user_id' => $user_id, ':activity_log_id' => $activity_log_id]);
                }
            }
        }
    }

    private static function getUsersForTaskNotification(\PDO $db, $task_id, $actor_user_id) {
        $sql = "
            (SELECT user_id FROM tasks WHERE id = :task_id_owner) 
            UNION
            (SELECT tm.user_id FROM task_shares ts
             JOIN team_members tm ON ts.team_id = tm.team_id
             WHERE ts.task_id = :task_id_shared)
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([':task_id_owner' => $task_id, ':task_id_shared' => $task_id]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    private static function getTeamMembers(\PDO $db, $team_id, $actor_user_id) {
        $sql = "SELECT user_id FROM team_members WHERE team_id = :team_id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':team_id' => $team_id]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    private static function getActivityDetails(\PDO $db, $activity_log_id) {
        $sql = "SELECT details FROM activity_logs WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id' => $activity_log_id]);
        return $stmt->fetchColumn();
    }
}
