-- =============================================================================
-- PROYECTO: Gestor de Música (MSK) - Fase 1
-- BASE DE DATOS: c_msk
-- DESCRIPCIÓN: Esquema Relacional con soporte de BORRADO LÓGICO (Soft Delete)
-- MOTOR: InnoDB | COTEJAMIENTO: utf8mb4_unicode_ci
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `c_msk`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `c_msk`;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `group_playlists`;
DROP TABLE IF EXISTS `playlist_songs`;
DROP TABLE IF EXISTS `music_groups`;
DROP TABLE IF EXISTS `playlists`;
DROP TABLE IF EXISTS `songs`;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `songs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `title` VARCHAR(255) NOT NULL,
    `artist` VARCHAR(255) NOT NULL,
    `album` VARCHAR(255) NULL,
    `genre` VARCHAR(100) NULL,
    `duration_seconds` INT UNSIGNED NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    
    INDEX `idx_songs_active` (`is_active`),
    INDEX `idx_songs_artist` (`artist`),
    INDEX `idx_songs_title` (`title`),
    INDEX `idx_songs_genre` (`genre`),
    FULLTEXT INDEX `idx_songs_search` (`title`, `artist`, `album`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `playlists` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    
    INDEX `idx_playlists_active` (`is_active`),
    INDEX `idx_playlists_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `music_groups` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `color_hex` VARCHAR(7) NULL DEFAULT '#2563EB',
    `description` TEXT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    
    INDEX `idx_groups_active` (`is_active`),
    INDEX `idx_groups_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `playlist_songs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `playlist_id` INT UNSIGNED NOT NULL,
    `song_id` INT UNSIGNED NOT NULL,
    `position` INT UNSIGNED NOT NULL DEFAULT 1,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `added_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    
    CONSTRAINT `fk_ps_playlist` FOREIGN KEY (`playlist_id`) 
        REFERENCES `playlists` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_ps_song` FOREIGN KEY (`song_id`) 
        REFERENCES `songs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        
    UNIQUE KEY `uq_playlist_song` (`playlist_id`, `song_id`),
    INDEX `idx_ps_active_order` (`playlist_id`, `is_active`, `position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `group_playlists` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `group_id` INT UNSIGNED NOT NULL,
    `playlist_id` INT UNSIGNED NOT NULL,
    `position` INT UNSIGNED NOT NULL DEFAULT 1,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL DEFAULT NULL,
    
    CONSTRAINT `fk_gp_group` FOREIGN KEY (`group_id`) 
        REFERENCES `music_groups` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_gp_playlist` FOREIGN KEY (`playlist_id`) 
        REFERENCES `playlists` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
        
    UNIQUE KEY `uq_group_playlist` (`group_id`, `playlist_id`),
    INDEX `idx_gp_active_order` (`group_id`, `is_active`, `position`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `songs` (`title`, `artist`, `album`, `genre`, `duration_seconds`) VALUES
('California Love', '2Pac ft. Dr. Dre', 'All Eyez on Me', 'Hip-Hop', 285),
('Juicy', 'The Notorious B.I.G.', 'Ready to Die', 'Hip-Hop', 303),
('Bohemian Rhapsody', 'Queen', 'A Night at the Opera', 'Rock', 354),
('Comfortably Numb', 'Pink Floyd', 'The Wall', 'Rock', 382),
('Take Five', 'Dave Brubeck Quartet', 'Time Out', 'Jazz', 324);

INSERT INTO `playlists` (`name`, `description`) VALUES
('Rap Clásico 90s', 'Lo mejor del rap clásico de los 90s'),
('Rock Legendario', 'Himnos indispensables del rock'),
('Sesión de Concentración', 'Música para estudio y trabajo');

INSERT INTO `music_groups` (`name`, `color_hex`, `description`) VALUES
('Por Géneros Musicales', '#2563EB', 'Agrupación basada en estilos musicales'),
('Momentos y Moods', '#10B981', 'Listas ordenadas por contexto o estado de ánimo');

INSERT INTO `playlist_songs` (`playlist_id`, `song_id`, `position`) VALUES
(1, 1, 1),
(1, 2, 2),
(2, 3, 1),
(2, 4, 2),
(3, 5, 1);

INSERT INTO `group_playlists` (`group_id`, `playlist_id`, `position`) VALUES
(1, 1, 1),
(1, 2, 2),
(2, 3, 1);
