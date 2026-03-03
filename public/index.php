<?php
/**
 * TaskForge — Front Controller
 *
 * All HTTP requests are routed through this file via .htaccess.
 * It bootstraps the application, then routes to the appropriate controller.
 */

// ──────────────────────────────────
// Bootstrap
// ──────────────────────────────────
$request_uri = isset($_GET['url']) ? trim($_GET['url'], '/') : '';
$method      = $_SERVER['REQUEST_METHOD'];

// ──────────────────────────────────
// API Router
// ──────────────────────────────────
if (strpos($request_uri, 'api/v1/') === 0) {

    require_once __DIR__ . '/../core/Bootstrap.php';

    try {
        $db = Database::getInstance()->getConnection();
    } catch (Exception $e) {
        http_response_code(503);
        echo json_encode(['error' => 'Database connection failed.']);
        exit;
    }

    $route = substr($request_uri, strlen('api/v1/'));

    // ── Auth Routes (Public) ──────────
    if (strpos($route, 'auth/') === 0) {
        require_once __DIR__ . '/../app/Controllers/AuthController.php';
        $controller = new AuthController($db);

        if ($route === 'auth/register' && $method === 'POST') {
            $controller->register();
        } else if ($route === 'auth/login' && $method === 'POST') {
            $controller->login();
        } else if ($route === 'auth/logout' && $method === 'POST') {
            $controller->logout();
        } else if ($route === 'auth/me' && $method === 'GET') {
            $controller->checkSession();
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Authentication endpoint not found.']);
        }
        exit;
    }

    // ── Status Route (Public) ─────────
    if ($route === 'status' && $method === 'GET') {
        http_response_code(200);
        echo json_encode([
            'status'    => 'API v1 is running',
            'timestamp' => date('c'),
            'database'  => '✅ Connection established'
        ]);
        exit;
    }

    // ══════════════════════════════════
    //  All routes below require auth
    // ══════════════════════════════════
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized. Please login to access this resource.']);
        exit;
    }
    $user_id = $_SESSION['user_id'];

    // ── Task Routes ───────────────────
    if (strpos($route, 'tasks') === 0) {

        // Comments sub-route
        if (preg_match('#^tasks/(\d+)/comments/?$#', $route, $matches)) {
            require_once __DIR__ . '/../app/Controllers/CommentController.php';
            $controller = new CommentController($db);
            $task_id = (int)$matches[1];

            if ($method === 'GET') {
                $controller->getTaskComments($task_id, $user_id);
            } else if ($method === 'POST') {
                $controller->postTaskComment($task_id, $user_id);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method Not Allowed.']);
            }
            exit;
        }

        require_once __DIR__ . '/../app/Controllers/TaskController.php';
        $controller = new TaskController($db);

        // Subtasks sub-route
        if (preg_match('#^tasks/(\d+)/subtasks/?$#', $route, $matches)) {
            $task_id = (int)$matches[1];
            if ($method === 'GET') {
                $controller->getSubtasks($task_id, $user_id);
            } else if ($method === 'POST') {
                $controller->createSubtask($task_id, $user_id);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method Not Allowed.']);
            }
            exit;
        }

        // Shares sub-routes
        if (preg_match('#^tasks/(\d+)/shares/?$#', $route, $matches)) {
            $task_id = (int)$matches[1];
            if ($method === 'GET') {
                $controller->getShares($task_id, $user_id);
            } else if ($method === 'POST') {
                $controller->share($task_id, $user_id);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method Not Allowed.']);
            }
        } else if (preg_match('#^tasks/(\d+)/shares/(\d+)$#', $route, $matches)) {
            $task_id = (int)$matches[1];
            $team_id = (int)$matches[2];
            if ($method === 'DELETE') {
                $controller->unshare($task_id, $team_id, $user_id);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method Not Allowed.']);
            }
        } else if (preg_match('#^tasks/?$#', $route)) {
            switch ($method) {
                case 'GET':  $controller->getAll($user_id); break;
                case 'POST': $controller->create($user_id); break;
                default:
                    http_response_code(405);
                    echo json_encode(['error' => 'Method Not Allowed.']);
            }
        } else if ($route === 'tasks/reorder' && $method === 'POST') {
            $controller->reorder($user_id);
        } else if (preg_match('#^tasks/(\d+)$#', $route, $matches)) {
            $task_id = (int)$matches[1];
            switch ($method) {
                case 'GET':   $controller->getOne($task_id, $user_id);    break;
                case 'PUT':
                case 'PATCH': $controller->update($task_id, $user_id);    break;
                case 'DELETE': $controller->delete($task_id, $user_id);   break;
                default:
                    http_response_code(405);
                    echo json_encode(['error' => 'Method Not Allowed.']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Task endpoint not found.']);
        }
        exit;
    }

    // ── AI Routes ─────────────────────
    if (strpos($route, 'ai/') === 0) {
        require_once __DIR__ . '/../app/Controllers/AiController.php';
        $controller = new AiController();

        if ($route === 'ai/suggest' && $method === 'POST') {
            $controller->getSuggestion($user_id);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'AI endpoint not found.']);
        }
        exit;
    }

    // ── Upload Routes ─────────────────
    if (strpos($route, 'uploads') === 0) {
        require_once __DIR__ . '/../app/Controllers/UploadController.php';
        $controller = new UploadController();

        if ($route === 'uploads' && $method === 'POST') {
            $controller->handleUpload($user_id);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Upload endpoint not found.']);
        }
        exit;
    }

    // ── Team Routes ───────────────────
    if (strpos($route, 'teams') === 0) {
        require_once __DIR__ . '/../app/Controllers/TeamController.php';
        $controller = new TeamController($db);

        if (preg_match('#^teams/?$#', $route)) {
            if ($method === 'GET') {
                $controller->getMyTeams($user_id);
            } else if ($method === 'POST') {
                $controller->createTeam($user_id);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method Not Allowed.']);
            }
        } else if (preg_match('#^teams/(\d+)$#', $route, $matches)) {
            if ($method === 'GET') {
                $controller->getTeamDetails((int)$matches[1], $user_id);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method Not Allowed.']);
            }
        } else if (preg_match('#^teams/(\d+)/members/?$#', $route, $matches)) {
            if ($method === 'POST') {
                $controller->addMember((int)$matches[1], $user_id);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method Not Allowed.']);
            }
        } else if (preg_match('#^teams/(\d+)/members/(\d+)$#', $route, $matches)) {
            if ($method === 'DELETE') {
                $controller->removeMember((int)$matches[1], (int)$matches[2], $user_id);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method Not Allowed.']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Team endpoint not found.']);
        }
        exit;
    }

    // ── Notification Routes ───────────
    if (strpos($route, 'notifications') === 0) {
        require_once __DIR__ . '/../app/Controllers/NotificationController.php';
        $controller = new NotificationController($db);

        if (preg_match('#^notifications/?$#', $route)) {
            if ($method === 'GET') {
                $controller->get($user_id);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method Not Allowed.']);
            }
        } else if (preg_match('#^notifications/mark-read$#', $route)) {
            if ($method === 'POST') {
                $controller->markRead($user_id);
            } else {
                http_response_code(405);
                echo json_encode(['error' => 'Method Not Allowed.']);
            }
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Notification endpoint not found.']);
        }
        exit;
    }

    // ── Fallback ──────────────────────
    http_response_code(404);
    echo json_encode(['error' => 'API endpoint not found.']);
    exit;
}

// ──────────────────────────────────
// Serve uploaded files from storage
// ──────────────────────────────────
if (strpos($request_uri, 'uploads/') === 0) {
    $storage_file = __DIR__ . '/../storage/' . $request_uri;
    if (file_exists($storage_file) && is_file($storage_file)) {
        $mime_type = mime_content_type($storage_file) ?: 'application/octet-stream';
        header("Content-Type: $mime_type");
        readfile($storage_file);
        exit;
    }
}

// ──────────────────────────────────
// Static Files (css, js, etc.)
// ──────────────────────────────────
$public_file_path = __DIR__ . '/' . $request_uri;
if ($request_uri && file_exists($public_file_path) && is_file($public_file_path)) {
    $mime_type = mime_content_type($public_file_path) ?: 'application/octet-stream';
    header("Content-Type: $mime_type");
    readfile($public_file_path);
    exit;
}

// ──────────────────────────────────
// Fallback → Main SPA HTML
// ──────────────────────────────────
readfile(__DIR__ . '/index.html');
exit;
