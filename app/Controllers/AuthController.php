<?php
/**
 * Auth Controller
 *
 * Handles user registration, login, logout, and session checks.
 */

require_once __DIR__ . '/../Services/AuthService.php';

class AuthController {
    private $db;
    private $authService;

    public function __construct($db) {
        $this->db = $db;
        $this->authService = new AuthService($this->db);
    }

    /**
     * POST /api/v1/auth/register
     */
    public function register() {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data.']);
            return;
        }
        try {
            $newUser = $this->authService->register($data);
            http_response_code(201);
            echo json_encode([
                'message' => 'User registered successfully!',
                'user' => $newUser
            ]);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'error' => 'Registration failed.',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * POST /api/v1/auth/login
     */
    public function login() {
        $data = json_decode(file_get_contents('php://input'), true);
        if ($data === null) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON data.']);
            return;
        }
        try {
            $user = $this->authService->login($data);
            http_response_code(200);
            echo json_encode([
                'message' => 'Login successful!',
                'user' => $user
            ]);
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode([
                'error' => 'Login failed.',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * POST /api/v1/auth/logout
     */
    public function logout() {
        try {
            $this->authService->logout();
            http_response_code(200);
            echo json_encode(['message' => 'Logout successful!']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Logout failed.',
                'message' => $e->getMessage()
            ]);
        }
    }

    /**
     * GET /api/v1/auth/me
     */
    public function checkSession() {
        if (isset($_SESSION['user_id'])) {
            http_response_code(200);
            echo json_encode([
                'isLoggedIn' => true,
                'user' => [
                    'id' => $_SESSION['user_id'],
                    'username' => $_SESSION['username']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode([
                'isLoggedIn' => false,
                'error' => 'No active session.'
            ]);
        }
    }
}
