-- =============================================
-- TaskForge — Database Schema
-- =============================================
-- Import this file into phpMyAdmin to set up
-- the database for the TaskForge application.
-- =============================================

CREATE DATABASE IF NOT EXISTS `taskforge`
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE `taskforge`;

-- ─────────────────────────────────
-- Users
-- ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `username`      VARCHAR(50)  NOT NULL UNIQUE,
    `email`         VARCHAR(255) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `profile_image` VARCHAR(255) DEFAULT NULL,
    `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_users_email` (`email`),
    INDEX `idx_users_username` (`username`)
) ENGINE=InnoDB;

-- ─────────────────────────────────
-- Tasks
-- ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `tasks` (
    `id`            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`       INT UNSIGNED NOT NULL,
    `title`         VARCHAR(255) NOT NULL,
    `description`   TEXT         DEFAULT NULL,
    `category`      VARCHAR(50)  DEFAULT 'Uncompleted',
    `priority`      VARCHAR(20)  DEFAULT 'Medium',
    `due_date`      DATE         DEFAULT NULL,
    `tags`          JSON         DEFAULT NULL,
    `sort_order`    INT          DEFAULT 0,
    `is_completed`  TINYINT(1)   DEFAULT 0,
    `completed_at`  DATETIME     DEFAULT NULL,
    `deleted_at`    DATETIME     DEFAULT NULL,
    `created_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    TIMESTAMP    DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX `idx_tasks_user` (`user_id`),
    INDEX `idx_tasks_category` (`category`),
    INDEX `idx_tasks_deleted` (`deleted_at`),
    FULLTEXT INDEX `ft_tasks_search` (`title`, `description`),

    CONSTRAINT `fk_tasks_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────
-- Teams
-- ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `teams` (
    `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `team_name`      VARCHAR(100) NOT NULL,
    `owner_user_id`  INT UNSIGNED NOT NULL,
    `created_at`     TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_teams_owner` (`owner_user_id`),

    CONSTRAINT `fk_teams_owner`
        FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────
-- Team Members
-- ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `team_members` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `team_id`    INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `role`       ENUM('Owner', 'Admin', 'Member', 'Viewer') DEFAULT 'Member',
    `joined_at`  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uk_team_user` (`team_id`, `user_id`),

    CONSTRAINT `fk_tm_team`
        FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_tm_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────
-- Task Shares (task ↔ team)
-- ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `task_shares` (
    `id`                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `task_id`           INT UNSIGNED NOT NULL,
    `team_id`           INT UNSIGNED NOT NULL,
    `permission`        VARCHAR(20) DEFAULT 'View',
    `shared_by_user_id` INT UNSIGNED NOT NULL,
    `created_at`        TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uk_task_team` (`task_id`, `team_id`),

    CONSTRAINT `fk_ts_task`
        FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_ts_team`
        FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_ts_sharer`
        FOREIGN KEY (`shared_by_user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────
-- Task Comments
-- ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `task_comments` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `task_id`    INT UNSIGNED NOT NULL,
    `user_id`    INT UNSIGNED NOT NULL,
    `comment`    TEXT         NOT NULL,
    `created_at` TIMESTAMP   DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_comments_task` (`task_id`),

    CONSTRAINT `fk_comments_task`
        FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_comments_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;

-- ─────────────────────────────────
-- Activity Logs
-- ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `activity_logs` (
    `id`         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`    INT UNSIGNED NOT NULL,
    `task_id`    INT UNSIGNED DEFAULT NULL,
    `team_id`    INT UNSIGNED DEFAULT NULL,
    `action`     VARCHAR(50)  NOT NULL,
    `details`    JSON         DEFAULT NULL,
    `created_at` TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_activity_user` (`user_id`),
    INDEX `idx_activity_task` (`task_id`),

    CONSTRAINT `fk_activity_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_activity_task`
        FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`)
        ON DELETE SET NULL,
    CONSTRAINT `fk_activity_team`
        FOREIGN KEY (`team_id`) REFERENCES `teams` (`id`)
        ON DELETE SET NULL
) ENGINE=InnoDB;

-- ─────────────────────────────────
-- Notifications
-- ─────────────────────────────────
CREATE TABLE IF NOT EXISTS `notifications` (
    `id`              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `user_id`         INT UNSIGNED NOT NULL,
    `activity_log_id` INT UNSIGNED NOT NULL,
    `is_read`         TINYINT(1)   DEFAULT 0,
    `created_at`      TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_notif_user` (`user_id`, `is_read`),

    CONSTRAINT `fk_notif_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE CASCADE,
    CONSTRAINT `fk_notif_activity`
        FOREIGN KEY (`activity_log_id`) REFERENCES `activity_logs` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB;
