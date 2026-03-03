<?php
/**
 * Team Service
 *
 * Business logic for team CRUD and member management.
 */

require_once __DIR__ . '/../Models/Team.php';
require_once __DIR__ . '/../Models/User.php';
require_once __DIR__ . '/ActivityLogger.php';

class TeamService {
    private $db;
    private $teamModel;
    private $userModel;

    public function __construct($db) {
        $this->db        = $db;
        $this->teamModel = new Team($db);
        $this->userModel = new User($db);
    }

    public function createTeam($team_name, $owner_user_id) {
        if (empty($team_name)) {
            throw new Exception("Team name is required.", 400);
        }

        $this->db->beginTransaction();
        try {
            $team_id = $this->teamModel->create($team_name, $owner_user_id);
            if (!$team_id) {
                throw new Exception("Failed to create team in database.", 500);
            }

            if (!$this->teamModel->addMember($team_id, $owner_user_id, 'Owner')) {
                throw new Exception("Failed to add owner to team.", 500);
            }

            $this->db->commit();

            try {
                ActivityLogger::log($this->db, $owner_user_id, 'created_team', null, $team_id);
            } catch (\Throwable $e) {
                error_log("ActivityLogger::log failed on createTeam: " . $e->getMessage());
            }

            return $this->teamModel->findById($team_id);

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function addMemberByEmail($team_id, $email, $role, $current_user_id) {
        if (!$this->teamModel->isUserAdminOrOwner($team_id, $current_user_id)) {
            throw new Exception("You do not have permission to add members to this team.", 403);
        }

        $user_to_add = $this->userModel->findByEmail($email);
        if (!$user_to_add) {
            throw new Exception("User with email '$email' not found.", 404);
        }
        $user_to_add_id = $user_to_add['id'];

        if ($this->teamModel->isUserMember($team_id, $user_to_add_id)) {
            throw new Exception("This user is already a member of the team.", 400);
        }

        if (!$this->teamModel->addMember($team_id, $user_to_add_id, $role)) {
            throw new Exception("Failed to add member to team.", 500);
        }

        try {
            $details = json_encode([
                'added_user_id' => $user_to_add_id,
                'added_email'   => $email,
                'role'          => $role
            ]);
            ActivityLogger::log($this->db, $current_user_id, 'added_member', null, $team_id, $details);
        } catch (\Throwable $e) {
            error_log("ActivityLogger::log failed on addMemberByEmail: " . $e->getMessage());
        }

        return $this->teamModel->findMembersByTeamId($team_id);
    }

    public function removeMember($team_id, $user_to_remove_id, $current_user_id) {
        if (!$this->teamModel->isUserAdminOrOwner($team_id, $current_user_id)) {
            throw new Exception("You do not have permission to remove members from this team.", 403);
        }

        $team = $this->teamModel->findById($team_id);
        if (!$team) {
            throw new Exception("Team not found.", 404);
        }

        if ((int)$team['owner_user_id'] === (int)$user_to_remove_id) {
            throw new Exception("Cannot remove the team owner.", 400);
        }

        if (!$this->teamModel->removeMember($team_id, $user_to_remove_id)) {
            throw new Exception("Failed to remove member.", 500);
        }

        try {
            $details = json_encode(['removed_user_id' => $user_to_remove_id]);
            ActivityLogger::log($this->db, $current_user_id, 'removed_member', null, $team_id, $details);
        } catch (\Throwable $e) {
            error_log("ActivityLogger::log failed on removeMember: " . $e->getMessage());
        }

        return $this->teamModel->findMembersByTeamId($team_id);
    }

    public function getTeamsForUser($user_id) {
        return $this->teamModel->findTeamsByUserId($user_id);
    }

    public function getTeamDetails($team_id, $user_id) {
        if (!$this->teamModel->isUserMember($team_id, $user_id)) {
            throw new Exception("You are not a member of this team.", 403);
        }

        $team = $this->teamModel->findById($team_id);
        if (!$team) {
            throw new Exception("Team not found.", 404);
        }
        $team['members'] = $this->teamModel->findMembersByTeamId($team_id);

        return $team;
    }
}
