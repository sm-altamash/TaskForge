<?php
/**
 * Notification Controller
 *
 * Handles notification retrieval and mark-as-read.
 */

require_once __DIR__ . '/../Services/NotificationService.php';

class NotificationController {
    private $notificationService;

    public function __construct($db) {
        $this->notificationService = new NotificationService($db);
    }

    private function sendError($e) {
        $status_code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        http_response_code($status_code);
        echo json_encode(['error' => $e->getMessage()]);
    }

    /**
     * GET /api/v1/notifications
     */
    public function get($user_id) {
        try {
            $data = $this->notificationService->getNotifications($user_id);
            http_response_code(200);
            echo json_encode($data);
        } catch (Exception $e) {
            $this->sendError($e);
        }
    }

    /**
     * POST /api/v1/notifications/mark-read
     */
    public function markRead($user_id) {
        try {
            $rows_affected = $this->notificationService->markAsRead($user_id);
            http_response_code(200);
            echo json_encode(['message' => 'Notifications marked as read.', 'cleared_count' => $rows_affected]);
        } catch (Exception $e) {
            $this->sendError($e);
        }
    }
}
