<?php
/**
 * Notification Service
 *
 * Retrieves and manages user notifications.
 */

class NotificationService {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getNotifications($user_id) {
        $sql = "
            SELECT 
                n.id as notification_id,
                n.is_read,
                n.created_at,
                a.action,
                u.username as actor_username,
                t.title as task_title
            FROM notifications n
            JOIN activity_logs a ON n.activity_log_id = a.id
            JOIN users u ON a.user_id = u.id
            LEFT JOIN tasks t ON a.task_id = t.id
            WHERE n.user_id = :user_id
            ORDER BY n.created_at DESC
            LIMIT 20
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $count_sql  = "SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0";
        $count_stmt = $this->db->prepare($count_sql);
        $count_stmt->execute([':user_id' => $user_id]);
        $unread_count = $count_stmt->fetchColumn();

        return [
            'notifications' => $notifications,
            'unread_count'  => (int) $unread_count
        ];
    }

    public function markAsRead($user_id) {
        $sql = "UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':user_id' => $user_id]);
        return $stmt->rowCount();
    }
}
