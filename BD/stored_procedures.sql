-- =============================================================================
-- PROYECTO: Gestor de Música (MSK) - Fase 1
-- BASE DE DATOS: c_msk
-- DESCRIPCIÓN: Stored Procedures (SP) con BORRADO LÓGICO (Soft Delete)
-- =============================================================================

USE `c_msk`;

DELIMITER $$

-- =============================================================================
-- SECCIÓN 1: TABLA `songs` (Catálogo Maestro)
-- =============================================================================

DROP PROCEDURE IF EXISTS `sp_song_create`$$
CREATE PROCEDURE `sp_song_create`(
    IN p_title VARCHAR(255),
    IN p_artist VARCHAR(255),
    IN p_album VARCHAR(255),
    IN p_genre VARCHAR(100),
    IN p_duration_seconds INT UNSIGNED
)
BEGIN
    INSERT INTO `songs` (`title`, `artist`, `album`, `genre`, `duration_seconds`, `is_active`, `created_at`, `deleted_at`)
    VALUES (TRIM(p_title), TRIM(p_artist), TRIM(p_album), TRIM(p_genre), COALESCE(p_duration_seconds, 0), 1, NOW(), NULL);
    
    SELECT * FROM `songs` WHERE `id` = LAST_INSERT_ID();
END$$

DROP PROCEDURE IF EXISTS `sp_song_get_by_id`$$
CREATE PROCEDURE `sp_song_get_by_id`(
    IN p_id INT UNSIGNED
)
BEGIN
    SELECT 
        s.*,
        (SELECT COUNT(*) FROM `playlist_songs` ps 
         JOIN `playlists` p ON ps.`playlist_id` = p.`id`
         WHERE ps.`song_id` = s.`id` AND ps.`is_active` = 1 AND p.`is_active` = 1) AS `playlists_count`
    FROM `songs` s
    WHERE s.`id` = p_id AND s.`is_active` = 1;
END$$

DROP PROCEDURE IF EXISTS `sp_song_list`$$
CREATE PROCEDURE `sp_song_list`(
    IN p_search VARCHAR(255),
    IN p_genre VARCHAR(100),
    IN p_limit INT UNSIGNED,
    IN p_offset INT UNSIGNED
)
BEGIN
    SET p_limit = COALESCE(p_limit, 25);
    SET p_offset = COALESCE(p_offset, 0);

    SELECT COUNT(*) AS `total_records`
    FROM `songs` s
    WHERE s.`is_active` = 1
      AND (p_search IS NULL OR CONVERT(p_search USING utf8mb4) = _utf8mb4'' OR s.`title` LIKE CONCAT('%', CONVERT(p_search USING utf8mb4), '%') COLLATE utf8mb4_unicode_ci OR s.`artist` LIKE CONCAT('%', CONVERT(p_search USING utf8mb4), '%') COLLATE utf8mb4_unicode_ci OR s.`album` LIKE CONCAT('%', CONVERT(p_search USING utf8mb4), '%') COLLATE utf8mb4_unicode_ci)
      AND (p_genre IS NULL OR CONVERT(p_genre USING utf8mb4) = _utf8mb4'' OR s.`genre` = CONVERT(p_genre USING utf8mb4) COLLATE utf8mb4_unicode_ci);

    SELECT 
        s.`id`,
        s.`title`,
        s.`artist`,
        s.`album`,
        s.`genre`,
        s.`duration_seconds`,
        s.`created_at`
    FROM `songs` s
    WHERE s.`is_active` = 1
      AND (p_search IS NULL OR CONVERT(p_search USING utf8mb4) = _utf8mb4'' OR s.`title` LIKE CONCAT('%', CONVERT(p_search USING utf8mb4), '%') COLLATE utf8mb4_unicode_ci OR s.`artist` LIKE CONCAT('%', CONVERT(p_search USING utf8mb4), '%') COLLATE utf8mb4_unicode_ci OR s.`album` LIKE CONCAT('%', CONVERT(p_search USING utf8mb4), '%') COLLATE utf8mb4_unicode_ci)
      AND (p_genre IS NULL OR CONVERT(p_genre USING utf8mb4) = _utf8mb4'' OR s.`genre` = CONVERT(p_genre USING utf8mb4) COLLATE utf8mb4_unicode_ci)
    ORDER BY s.`id` DESC
    LIMIT p_limit OFFSET p_offset;
END$$

DROP PROCEDURE IF EXISTS `sp_song_update`$$
CREATE PROCEDURE `sp_song_update`(
    IN p_id INT UNSIGNED,
    IN p_title VARCHAR(255),
    IN p_artist VARCHAR(255),
    IN p_album VARCHAR(255),
    IN p_genre VARCHAR(100),
    IN p_duration_seconds INT UNSIGNED
)
BEGIN
    UPDATE `songs`
    SET `title` = TRIM(p_title),
        `artist` = TRIM(p_artist),
        `album` = TRIM(p_album),
        `genre` = TRIM(p_genre),
        `duration_seconds` = COALESCE(p_duration_seconds, `duration_seconds`)
    WHERE `id` = p_id AND `is_active` = 1;
    
    SELECT * FROM `songs` WHERE `id` = p_id;
END$$

DROP PROCEDURE IF EXISTS `sp_song_delete`$$
CREATE PROCEDURE `sp_song_delete`(
    IN p_id INT UNSIGNED
)
BEGIN
    UPDATE `songs`
    SET `is_active` = 0,
        `deleted_at` = NOW()
    WHERE `id` = p_id;

    UPDATE `playlist_songs`
    SET `is_active` = 0,
        `deleted_at` = NOW()
    WHERE `song_id` = p_id AND `is_active` = 1;

    SELECT ROW_COUNT() AS `affected_rows`;
END$$

DROP PROCEDURE IF EXISTS `sp_song_restore`$$
CREATE PROCEDURE `sp_song_restore`(
    IN p_id INT UNSIGNED
)
BEGIN
    UPDATE `songs`
    SET `is_active` = 1,
        `deleted_at` = NULL
    WHERE `id` = p_id;

    SELECT * FROM `songs` WHERE `id` = p_id;
END$$


-- =============================================================================
-- SECCIÓN 2: TABLA `playlists` (Listas de Reproducción)
-- =============================================================================

DROP PROCEDURE IF EXISTS `sp_playlist_create`$$
CREATE PROCEDURE `sp_playlist_create`(
    IN p_name VARCHAR(150),
    IN p_description TEXT
)
BEGIN
    INSERT INTO `playlists` (`name`, `description`, `is_active`, `created_at`, `updated_at`, `deleted_at`)
    VALUES (TRIM(p_name), TRIM(p_description), 1, NOW(), NOW(), NULL);
    
    SELECT * FROM `playlists` WHERE `id` = LAST_INSERT_ID();
END$$

DROP PROCEDURE IF EXISTS `sp_playlist_get_by_id`$$
CREATE PROCEDURE `sp_playlist_get_by_id`(
    IN p_id INT UNSIGNED
)
BEGIN
    SELECT 
        p.`id`,
        p.`name`,
        p.`description`,
        p.`created_at`,
        p.`updated_at`,
        COUNT(ps.`song_id`) AS `songs_count`,
        COALESCE(SUM(s.`duration_seconds`), 0) AS `total_duration_seconds`
    FROM `playlists` p
    LEFT JOIN `playlist_songs` ps ON p.`id` = ps.`playlist_id` AND ps.`is_active` = 1
    LEFT JOIN `songs` s ON ps.`song_id` = s.`id` AND s.`is_active` = 1
    WHERE p.`id` = p_id AND p.`is_active` = 1
    GROUP BY p.`id`;
END$$

DROP PROCEDURE IF EXISTS `sp_playlist_list`$$
CREATE PROCEDURE `sp_playlist_list`(
    IN p_search VARCHAR(150),
    IN p_limit INT UNSIGNED,
    IN p_offset INT UNSIGNED
)
BEGIN
    SET p_limit = COALESCE(p_limit, 50);
    SET p_offset = COALESCE(p_offset, 0);

    SELECT 
        p.`id`,
        p.`name`,
        p.`description`,
        p.`created_at`,
        p.`updated_at`,
        COUNT(ps.`song_id`) AS `songs_count`
    FROM `playlists` p
    LEFT JOIN `playlist_songs` ps ON p.`id` = ps.`playlist_id` AND ps.`is_active` = 1
    LEFT JOIN `songs` s ON ps.`song_id` = s.`id` AND s.`is_active` = 1
    WHERE p.`is_active` = 1
      AND (p_search IS NULL OR CONVERT(p_search USING utf8mb4) = _utf8mb4'' OR p.`name` LIKE CONCAT('%', CONVERT(p_search USING utf8mb4), '%') COLLATE utf8mb4_unicode_ci)
    GROUP BY p.`id`
    ORDER BY p.`updated_at` DESC
    LIMIT p_limit OFFSET p_offset;
END$$

DROP PROCEDURE IF EXISTS `sp_playlist_update`$$
CREATE PROCEDURE `sp_playlist_update`(
    IN p_id INT UNSIGNED,
    IN p_name VARCHAR(150),
    IN p_description TEXT
)
BEGIN
    UPDATE `playlists`
    SET `name` = TRIM(p_name),
        `description` = TRIM(p_description),
        `updated_at` = NOW()
    WHERE `id` = p_id AND `is_active` = 1;
    
    SELECT * FROM `playlists` WHERE `id` = p_id;
END$$

DROP PROCEDURE IF EXISTS `sp_playlist_delete`$$
CREATE PROCEDURE `sp_playlist_delete`(
    IN p_id INT UNSIGNED
)
BEGIN
    UPDATE `playlists`
    SET `is_active` = 0,
        `deleted_at` = NOW()
    WHERE `id` = p_id;

    UPDATE `playlist_songs`
    SET `is_active` = 0,
        `deleted_at` = NOW()
    WHERE `playlist_id` = p_id AND `is_active` = 1;

    UPDATE `group_playlists`
    SET `is_active` = 0,
        `deleted_at` = NOW()
    WHERE `playlist_id` = p_id AND `is_active` = 1;

    SELECT ROW_COUNT() AS `affected_rows`;
END$$

DROP PROCEDURE IF EXISTS `sp_playlist_restore`$$
CREATE PROCEDURE `sp_playlist_restore`(
    IN p_id INT UNSIGNED
)
BEGIN
    UPDATE `playlists`
    SET `is_active` = 1,
        `deleted_at` = NULL
    WHERE `id` = p_id;

    SELECT * FROM `playlists` WHERE `id` = p_id;
END$$


-- =============================================================================
-- SECCIÓN 3: TABLA `music_groups` (Grupos Organizativos)
-- =============================================================================

DROP PROCEDURE IF EXISTS `sp_music_group_create`$$
CREATE PROCEDURE `sp_music_group_create`(
    IN p_name VARCHAR(150),
    IN p_color_hex VARCHAR(7),
    IN p_description TEXT
)
BEGIN
    INSERT INTO `music_groups` (`name`, `color_hex`, `description`, `is_active`, `created_at`, `updated_at`, `deleted_at`)
    VALUES (
        TRIM(p_name), 
        COALESCE(NULLIF(TRIM(p_color_hex), ''), '#2563EB'), 
        TRIM(p_description), 
        1, 
        NOW(), 
        NOW(), 
        NULL
    );
    
    SELECT * FROM `music_groups` WHERE `id` = LAST_INSERT_ID();
END$$

DROP PROCEDURE IF EXISTS `sp_music_group_get_by_id`$$
CREATE PROCEDURE `sp_music_group_get_by_id`(
    IN p_id INT UNSIGNED
)
BEGIN
    SELECT 
        g.`id`,
        g.`name`,
        g.`color_hex`,
        g.`description`,
        g.`created_at`,
        g.`updated_at`,
        COUNT(gp.`playlist_id`) AS `playlists_count`
    FROM `music_groups` g
    LEFT JOIN `group_playlists` gp ON g.`id` = gp.`group_id` AND gp.`is_active` = 1
    LEFT JOIN `playlists` p ON gp.`playlist_id` = p.`id` AND p.`is_active` = 1
    WHERE g.`id` = p_id AND g.`is_active` = 1
    GROUP BY g.`id`;
END$$

DROP PROCEDURE IF EXISTS `sp_music_group_list`$$
CREATE PROCEDURE `sp_music_group_list`(
    IN p_search VARCHAR(150),
    IN p_limit INT UNSIGNED,
    IN p_offset INT UNSIGNED
)
BEGIN
    SET p_limit = COALESCE(p_limit, 50);
    SET p_offset = COALESCE(p_offset, 0);

    SELECT 
        g.`id`,
        g.`name`,
        g.`color_hex`,
        g.`description`,
        g.`created_at`,
        g.`updated_at`,
        COUNT(gp.`playlist_id`) AS `playlists_count`
    FROM `music_groups` g
    LEFT JOIN `group_playlists` gp ON g.`id` = gp.`group_id` AND gp.`is_active` = 1
    LEFT JOIN `playlists` p ON gp.`playlist_id` = p.`id` AND p.`is_active` = 1
    WHERE g.`is_active` = 1
      AND (p_search IS NULL OR CONVERT(p_search USING utf8mb4) = _utf8mb4'' OR g.`name` LIKE CONCAT('%', CONVERT(p_search USING utf8mb4), '%') COLLATE utf8mb4_unicode_ci)
    GROUP BY g.`id`
    ORDER BY g.`name` ASC
    LIMIT p_limit OFFSET p_offset;
END$$

DROP PROCEDURE IF EXISTS `sp_music_group_update`$$
CREATE PROCEDURE `sp_music_group_update`(
    IN p_id INT UNSIGNED,
    IN p_name VARCHAR(150),
    IN p_color_hex VARCHAR(7),
    IN p_description TEXT
)
BEGIN
    UPDATE `music_groups`
    SET `name` = TRIM(p_name),
        `color_hex` = COALESCE(NULLIF(TRIM(p_color_hex), ''), `color_hex`),
        `description` = TRIM(p_description),
        `updated_at` = NOW()
    WHERE `id` = p_id AND `is_active` = 1;
    
    SELECT * FROM `music_groups` WHERE `id` = p_id;
END$$

DROP PROCEDURE IF EXISTS `sp_music_group_delete`$$
CREATE PROCEDURE `sp_music_group_delete`(
    IN p_id INT UNSIGNED
)
BEGIN
    UPDATE `music_groups`
    SET `is_active` = 0,
        `deleted_at` = NOW()
    WHERE `id` = p_id;

    UPDATE `group_playlists`
    SET `is_active` = 0,
        `deleted_at` = NOW()
    WHERE `group_id` = p_id AND `is_active` = 1;

    SELECT ROW_COUNT() AS `affected_rows`;
END$$

DROP PROCEDURE IF EXISTS `sp_music_group_restore`$$
CREATE PROCEDURE `sp_music_group_restore`(
    IN p_id INT UNSIGNED
)
BEGIN
    UPDATE `music_groups`
    SET `is_active` = 1,
        `deleted_at` = NULL
    WHERE `id` = p_id;

    SELECT * FROM `music_groups` WHERE `id` = p_id;
END$$


-- =============================================================================
-- SECCIÓN 4: TABLA PIVOTE `playlist_songs` (Canciones <-> Playlists)
-- =============================================================================

DROP PROCEDURE IF EXISTS `sp_playlist_song_add`$$
CREATE PROCEDURE `sp_playlist_song_add`(
    IN p_playlist_id INT UNSIGNED,
    IN p_song_id INT UNSIGNED,
    IN p_position INT UNSIGNED
)
BEGIN
    DECLARE v_next_pos INT UNSIGNED;

    IF p_position IS NULL OR p_position = 0 THEN
        SELECT COALESCE(MAX(`position`), 0) + 1 INTO v_next_pos
        FROM `playlist_songs`
        WHERE `playlist_id` = p_playlist_id AND `is_active` = 1;
    ELSE
        SET v_next_pos = p_position;
    END IF;

    INSERT INTO `playlist_songs` (`playlist_id`, `song_id`, `position`, `is_active`, `added_at`, `deleted_at`)
    VALUES (p_playlist_id, p_song_id, v_next_pos, 1, NOW(), NULL)
    ON DUPLICATE KEY UPDATE 
        `position` = v_next_pos,
        `is_active` = 1,
        `deleted_at` = NULL,
        `added_at` = NOW();

    UPDATE `playlists` SET `updated_at` = NOW() WHERE `id` = p_playlist_id;

    SELECT ps.*, s.`title`, s.`artist`
    FROM `playlist_songs` ps
    JOIN `songs` s ON ps.`song_id` = s.`id`
    WHERE ps.`playlist_id` = p_playlist_id AND ps.`song_id` = p_song_id;
END$$

DROP PROCEDURE IF EXISTS `sp_playlist_song_remove`$$
CREATE PROCEDURE `sp_playlist_song_remove`(
    IN p_playlist_id INT UNSIGNED,
    IN p_song_id INT UNSIGNED
)
BEGIN
    UPDATE `playlist_songs` 
    SET `is_active` = 0,
        `deleted_at` = NOW()
    WHERE `playlist_id` = p_playlist_id AND `song_id` = p_song_id;

    UPDATE `playlists` SET `updated_at` = NOW() WHERE `id` = p_playlist_id;

    SELECT ROW_COUNT() AS `affected_rows`;
END$$

DROP PROCEDURE IF EXISTS `sp_playlist_song_list`$$
CREATE PROCEDURE `sp_playlist_song_list`(
    IN p_playlist_id INT UNSIGNED
)
BEGIN
    SELECT 
        ps.`id` AS `relation_id`,
        ps.`playlist_id`,
        ps.`song_id`,
        ps.`position`,
        ps.`added_at`,
        s.`title`,
        s.`artist`,
        s.`album`,
        s.`genre`,
        s.`duration_seconds`
    FROM `playlist_songs` ps
    JOIN `songs` s ON ps.`song_id` = s.`id`
    WHERE ps.`playlist_id` = p_playlist_id 
      AND ps.`is_active` = 1 
      AND s.`is_active` = 1
    ORDER BY ps.`position` ASC, ps.`added_at` ASC;
END$$

DROP PROCEDURE IF EXISTS `sp_playlist_song_reorder`$$
CREATE PROCEDURE `sp_playlist_song_reorder`(
    IN p_playlist_id INT UNSIGNED,
    IN p_song_id INT UNSIGNED,
    IN p_new_position INT UNSIGNED
)
BEGIN
    UPDATE `playlist_songs`
    SET `position` = p_new_position
    WHERE `playlist_id` = p_playlist_id AND `song_id` = p_song_id AND `is_active` = 1;

    UPDATE `playlists` SET `updated_at` = NOW() WHERE `id` = p_playlist_id;

    SELECT ROW_COUNT() AS `affected_rows`;
END$$


-- =============================================================================
-- SECCIÓN 5: TABLA PIVOTE `group_playlists` (Playlists <-> Groups)
-- =============================================================================

DROP PROCEDURE IF EXISTS `sp_group_playlist_add`$$
CREATE PROCEDURE `sp_group_playlist_add`(
    IN p_group_id INT UNSIGNED,
    IN p_playlist_id INT UNSIGNED,
    IN p_position INT UNSIGNED
)
BEGIN
    DECLARE v_next_pos INT UNSIGNED;

    IF p_position IS NULL OR p_position = 0 THEN
        SELECT COALESCE(MAX(`position`), 0) + 1 INTO v_next_pos
        FROM `group_playlists`
        WHERE `group_id` = p_group_id AND `is_active` = 1;
    ELSE
        SET v_next_pos = p_position;
    END IF;

    INSERT INTO `group_playlists` (`group_id`, `playlist_id`, `position`, `is_active`, `assigned_at`, `deleted_at`)
    VALUES (p_group_id, p_playlist_id, v_next_pos, 1, NOW(), NULL)
    ON DUPLICATE KEY UPDATE 
        `position` = v_next_pos,
        `is_active` = 1,
        `deleted_at` = NULL,
        `assigned_at` = NOW();

    UPDATE `music_groups` SET `updated_at` = NOW() WHERE `id` = p_group_id;

    SELECT gp.*, p.`name` AS `playlist_name`
    FROM `group_playlists` gp
    JOIN `playlists` p ON gp.`playlist_id` = p.`id`
    WHERE gp.`group_id` = p_group_id AND gp.`playlist_id` = p_playlist_id;
END$$

DROP PROCEDURE IF EXISTS `sp_group_playlist_remove`$$
CREATE PROCEDURE `sp_group_playlist_remove`(
    IN p_group_id INT UNSIGNED,
    IN p_playlist_id INT UNSIGNED
)
BEGIN
    UPDATE `group_playlists`
    SET `is_active` = 0,
        `deleted_at` = NOW()
    WHERE `group_id` = p_group_id AND `playlist_id` = p_playlist_id;

    UPDATE `music_groups` SET `updated_at` = NOW() WHERE `id` = p_group_id;

    SELECT ROW_COUNT() AS `affected_rows`;
END$$

DROP PROCEDURE IF EXISTS `sp_group_playlist_list`$$
CREATE PROCEDURE `sp_group_playlist_list`(
    IN p_group_id INT UNSIGNED
)
BEGIN
    SELECT 
        gp.`id` AS `relation_id`,
        gp.`group_id`,
        gp.`playlist_id`,
        gp.`position`,
        gp.`assigned_at`,
        p.`name` AS `playlist_name`,
        p.`description` AS `playlist_description`,
        (SELECT COUNT(*) FROM `playlist_songs` ps WHERE ps.`playlist_id` = p.`id` AND ps.`is_active` = 1) AS `songs_count`
    FROM `group_playlists` gp
    JOIN `playlists` p ON gp.`playlist_id` = p.`id`
    WHERE gp.`group_id` = p_group_id 
      AND gp.`is_active` = 1 
      AND p.`is_active` = 1
    ORDER BY gp.`position` ASC, gp.`assigned_at` ASC;
END$$

DROP PROCEDURE IF EXISTS `sp_group_playlist_reorder`$$
CREATE PROCEDURE `sp_group_playlist_reorder`(
    IN p_group_id INT UNSIGNED,
    IN p_playlist_id INT UNSIGNED,
    IN p_new_position INT UNSIGNED
)
BEGIN
    UPDATE `group_playlists`
    SET `position` = p_new_position
    WHERE `group_id` = p_group_id AND `playlist_id` = p_playlist_id AND `is_active` = 1;

    UPDATE `music_groups` SET `updated_at` = NOW() WHERE `id` = p_group_id;

    SELECT ROW_COUNT() AS `affected_rows`;
END$$

DELIMITER ;
