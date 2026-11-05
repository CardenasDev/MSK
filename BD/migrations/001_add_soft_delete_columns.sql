-- Migración 001: habilita borrado lógico en la base c_msk.
-- Ejecutar una sola vez sobre una instalación creada con un esquema anterior.

USE `c_msk`;

ALTER TABLE `songs`
    ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `duration_seconds`,
    ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL AFTER `created_at`,
    ADD INDEX `idx_songs_active` (`is_active`);

ALTER TABLE `playlists`
    ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `description`,
    ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`,
    ADD INDEX `idx_playlists_active` (`is_active`);

ALTER TABLE `music_groups`
    ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `description`,
    ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`,
    ADD INDEX `idx_groups_active` (`is_active`);

ALTER TABLE `playlist_songs`
    ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `position`,
    ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL AFTER `added_at`,
    ADD INDEX `idx_ps_active_order` (`playlist_id`, `is_active`, `position`);

ALTER TABLE `group_playlists`
    ADD COLUMN `is_active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `position`,
    ADD COLUMN `deleted_at` DATETIME NULL DEFAULT NULL AFTER `assigned_at`,
    ADD INDEX `idx_gp_active_order` (`group_id`, `is_active`, `position`);

ALTER TABLE `songs`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `playlists`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `music_groups`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `playlist_songs`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE `group_playlists`
    CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
