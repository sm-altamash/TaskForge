<?php
/**
 * Auth Service
 *
 * Handles user registration, login, and logout business logic.
 */

require_once __DIR__ . '/../Models/User.php';

class AuthService {
    private $db;
    private $userModel;

    public function __construct($db) {
        $this->db = $db;
        $this->userModel = new User($this->db);
    }

    public function register($data) {
        if (empty($data['username']) || empty($data['email']) || empty($data['password'])) {
            throw new Exception("Username, email, and password are required.");
        }
        if (strlen($data['password']) < 8) {
            throw new Exception("Password must be at least 8 characters long.");
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }
        if ($this->userModel->findByEmail($data['email'])) {
            throw new Exception("Email is already in use.");
        }
        if ($this->userModel->findByUsername($data['username'])) {
            throw new Exception("Username is already in use.");
        }

        $password_hash = password_hash($data['password'], PASSWORD_BCRYPT);
        if ($password_hash === false) {
            throw new Exception("Failed to hash password.");
        }

        $this->userModel->username      = $data['username'];
        $this->userModel->email         = $data['email'];
        $this->userModel->password_hash = $password_hash;

        if ($this->userModel->create()) {
            return [
                'id'       => $this->userModel->id,
                'username' => $this->userModel->username,
                'email'    => $this->userModel->email
            ];
        } else {
            throw new Exception("Failed to register user in database.");
        }
    }

    public function login($data) {
        if (empty($data['email']) || empty($data['password'])) {
            throw new Exception("Invalid email or password.");
        }

        $user = $this->userModel->findByEmail($data['email']);
        if (!$user) {
            throw new Exception("Invalid email or password.");
        }

        if (password_verify($data['password'], $user['password_hash'])) {
            session_regenerate_id(true);

            $_SESSION['user_id']    = $user['id'];
            $_SESSION['username']   = $user['username'];
            $_SESSION['login_time'] = time();

            unset($user['password_hash']);
            return $user;
        } else {
            throw new Exception("Invalid email or password.");
        }
    }

    public function logout() {
        $_SESSION = array();

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        session_destroy();
    }
}
