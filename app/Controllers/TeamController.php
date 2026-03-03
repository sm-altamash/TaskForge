<?php
/**
 * Team Controller
 *
 * Handles team CRUD and member management endpoints.
 */

require_once __DIR__ . '/../Services/TeamService.php';

class TeamController {
    private $teamService;

    public function __construct($db) {
        $this->teamService = new TeamService($db);
    }

    private function sendError($e) {
        $status_code = ($e->getCode() >= 400 && $e->getCode() < 600) ? $e->getCode() : 500;
        http_response_code($status_code);
        echo json_encode(['error' => $e->getMessage()]);
    }

    /** GET /api/v1/teams */
    public function getMyTeams($user_id) {
        try {
            $teams = $this->teamService->getTeamsForUser($user_id);
            http_response_code(200);
            echo json_encode($teams);
        } catch (Exception $e) {
            $this->sendError($e);
        }
    }

    /** POST /api/v1/teams */
    public function createTeam($user_id) {
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $newTeam = $this->teamService->createTeam($data['team_name'], $user_id);
            http_response_code(201);
            echo json_encode($newTeam);
        } catch (Exception $e) {
            $this->sendError($e);
        }
    }

    /** GET /api/v1/teams/{id} */
    public function getTeamDetails($team_id, $user_id) {
        try {
            $teamDetails = $this->teamService->getTeamDetails($team_id, $user_id);
            http_response_code(200);
            echo json_encode($teamDetails);
        } catch (Exception $e) {
            $this->sendError($e);
        }
    }

    /** POST /api/v1/teams/{id}/members */
    public function addMember($team_id, $user_id) {
        $data = json_decode(file_get_contents('php://input'), true);
        try {
            $members = $this->teamService->addMemberByEmail(
                $team_id,
                $data['email'],
                $data['role'] ?? 'Member',
                $user_id
            );
            http_response_code(200);
            echo json_encode($members);
        } catch (Exception $e) {
            $this->sendError($e);
        }
    }

    /** DELETE /api/v1/teams/{id}/members/{userId} */
    public function removeMember($team_id, $member_to_remove_id, $user_id) {
        try {
            $members = $this->teamService->removeMember(
                $team_id,
                $member_to_remove_id,
                $user_id
            );
            http_response_code(200);
            echo json_encode($members);
        } catch (Exception $e) {
            $this->sendError($e);
        }
    }
}
