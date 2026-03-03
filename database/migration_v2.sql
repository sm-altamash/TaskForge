-- =============================================
-- TaskForge — Database Migration v2
-- =============================================
-- Run this AFTER importing schema.sql
-- =============================================

USE `taskforge`;

-- ─────────────────────────────────
-- Subtasks: parent-child task relationships
-- ─────────────────────────────────
ALTER TABLE `tasks` ADD COLUMN `parent_id` INT UNSIGNED DEFAULT NULL AFTER `user_id`;
ALTER TABLE `tasks` ADD INDEX `idx_tasks_parent` (`parent_id`);
ALTER TABLE `tasks` ADD CONSTRAINT `fk_tasks_parent`
    FOREIGN KEY (`parent_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE;

-- ─────────────────────────────────
-- Threaded comments
-- ─────────────────────────────────
ALTER TABLE `task_comments` ADD COLUMN `parent_comment_id` INT UNSIGNED DEFAULT NULL AFTER `comment`;
ALTER TABLE `task_comments` ADD COLUMN `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;
ALTER TABLE `task_comments` ADD COLUMN `deleted_at` DATETIME DEFAULT NULL;
ALTER TABLE `task_comments` ADD CONSTRAINT `fk_comment_parent`
    FOREIGN KEY (`parent_comment_id`) REFERENCES `task_comments` (`id`) ON DELETE CASCADE;

-- ─────────────────────────────────
-- Comment Reactions
-- ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `comment_reactions` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `comment_id` INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `emoji`      VARCHAR(10) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uk_reaction` (`comment_id`, `user_id`, `emoji`),

    CONSTRAINT `fk_reaction_comment`
        FOREIGN KEY (`comment_id`) REFERENCES `task_comments` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_reaction_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────
-- Team Invitations
-- ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `team_invitations` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `team_id`    INT UNSIGNED NOT NULL,
    `email`      VARCHAR(255) NOT NULL,
    `token`      VARCHAR(255) NOT NULL UNIQUE,
    `role`       ENUM('Admin','Manager','Member','Viewer') DEFAULT 'Member',
    `invited_by` INT UNSIGNED NOT NULL,
    `status`     ENUM('pending','accepted','declined','expired') DEFAULT 'pending',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `expires_at` DATETIME NOT NULL,

    CONSTRAINT `fk_invite_team`
        FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_invite_user`
        FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────
-- Team Resources (shared files)
-- ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `team_resources` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `team_id`    INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `filename`   VARCHAR(255) NOT NULL,
    `filepath`   VARCHAR(500) NOT NULL,
    `filetype`   VARCHAR(50)  DEFAULT NULL,
    `filesize`   INT UNSIGNED DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT `fk_resource_team`
        FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_resource_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────
-- Extend team_members role ENUM
-- ─────────────────────────────────
ALTER TABLE `team_members` MODIFY `role` ENUM('Owner','Admin','Manager','Member','Viewer') DEFAULT 'Member';
