<?php
/**
 * AI Controller
 *
 * Handles AI-powered task suggestion endpoints.
 */

require_once __DIR__ . '/../Services/GeminiService.php';

class AiController {

    /**
     * POST /api/v1/ai/suggest
     */
    public function getSuggestion($user_id = null) {
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true);
        $title = isset($input['title']) ? trim($input['title']) : '';

        if (!$title) {
            http_response_code(400);
            echo json_encode(['error' => 'Missing title in request.']);
            return;
        }

        try {
            $svc = new GeminiService();
            $suggestion = $svc->getTaskSuggestion($title);

            http_response_code(200);
            echo json_encode(['suggestion' => $suggestion]);
            return;
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
            return;
        }
    }
}
