USE `c_msk`;

DELIMITER $$

DROP PROCEDURE IF EXISTS `sp_song_bulk_import`$$
CREATE PROCEDURE `sp_song_bulk_import`(
    IN p_rows JSON
)
SQL SECURITY INVOKER
BEGIN
    DECLARE v_row_count INT DEFAULT 0;
    DECLARE v_invalid_row INT DEFAULT NULL;
    DECLARE v_inserted_count INT DEFAULT 0;
    DECLARE v_index INT DEFAULT 0;
    DECLARE v_title VARCHAR(255);
    DECLARE v_artist VARCHAR(255);
    DECLARE v_album VARCHAR(255);
    DECLARE v_genre VARCHAR(100);
    DECLARE v_duration_raw VARCHAR(32);
    DECLARE v_duration_seconds INT UNSIGNED DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        DROP TEMPORARY TABLE IF EXISTS `tmp_song_bulk_import`;
        RESIGNAL;
    END;

    IF p_rows IS NULL OR JSON_TYPE(p_rows) <> 'ARRAY' THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El lote de canciones debe ser un arreglo JSON.';
    END IF;

    SET v_row_count = JSON_LENGTH(p_rows);

    IF v_row_count = 0 OR v_row_count > 2500 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El lote debe contener entre 1 y 2500 canciones.';
    END IF;

    CREATE TEMPORARY TABLE `tmp_song_bulk_import` (
        `row_number` INT UNSIGNED NOT NULL,
        `title` VARCHAR(255) NULL,
        `artist` VARCHAR(255) NULL,
        `album` VARCHAR(255) NULL,
        `genre` VARCHAR(100) NULL,
        `duration_raw` VARCHAR(32) NULL,
        `duration_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
        `dedupe_key` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        PRIMARY KEY (`row_number`),
        KEY `idx_tmp_song_dedupe_key` (`dedupe_key`)
    ) ENGINE = MEMORY;

    WHILE v_index < v_row_count DO
        SET v_title = JSON_UNQUOTE(JSON_EXTRACT(p_rows, CONCAT('$[', v_index, '].title')));
        SET v_artist = JSON_UNQUOTE(JSON_EXTRACT(p_rows, CONCAT('$[', v_index, '].artist')));
        SET v_album = JSON_UNQUOTE(JSON_EXTRACT(p_rows, CONCAT('$[', v_index, '].album')));
        SET v_genre = JSON_UNQUOTE(JSON_EXTRACT(p_rows, CONCAT('$[', v_index, '].genre')));
        SET v_duration_raw = JSON_UNQUOTE(JSON_EXTRACT(p_rows, CONCAT('$[', v_index, '].duration_seconds')));
        SET v_duration_seconds = CASE
            WHEN NULLIF(TRIM(COALESCE(v_duration_raw, '')), '') IS NULL THEN 0
            WHEN TRIM(v_duration_raw) REGEXP '^[0-9]+$'
                THEN CAST(TRIM(v_duration_raw) AS UNSIGNED)
            ELSE 0
        END;

        INSERT INTO `tmp_song_bulk_import`
            (`row_number`, `title`, `artist`, `album`, `genre`, `duration_raw`, `duration_seconds`, `dedupe_key`)
        VALUES (
            v_index + 1,
            NULLIF(TRIM(v_title), ''),
            NULLIF(TRIM(v_artist), ''),
            NULLIF(TRIM(COALESCE(v_album, '')), ''),
            NULLIF(TRIM(COALESCE(v_genre, '')), ''),
            NULLIF(TRIM(COALESCE(v_duration_raw, '')), ''),
            v_duration_seconds,
                CONVERT(SHA2(CONCAT(
                LOWER(TRIM(COALESCE(v_title, ''))), CHAR(31),
                LOWER(TRIM(COALESCE(v_artist, ''))), CHAR(31),
                LOWER(TRIM(COALESCE(v_album, '')))
                ), 256) USING ascii) COLLATE ascii_bin
        );

        SET v_index = v_index + 1;
    END WHILE;

    SELECT `row_number`
    INTO v_invalid_row
    FROM `tmp_song_bulk_import`
    WHERE `title` IS NULL
       OR `artist` IS NULL
       OR `title` = ''
       OR `artist` = ''
       OR CHAR_LENGTH(`title`) > 255
       OR CHAR_LENGTH(`artist`) > 255
       OR CHAR_LENGTH(COALESCE(`album`, '')) > 255
       OR CHAR_LENGTH(COALESCE(`genre`, '')) > 100
       OR (`duration_raw` IS NOT NULL AND `duration_raw` NOT REGEXP '^[0-9]+$')
       OR `duration_seconds` > 86399
    ORDER BY `row_number`
    LIMIT 1;

    IF v_invalid_row IS NOT NULL THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El lote contiene una fila con datos inválidos.';
    END IF;

    START TRANSACTION;

    INSERT INTO `songs`
        (`title`, `artist`, `album`, `genre`, `duration_seconds`, `is_active`, `created_at`, `deleted_at`)
    SELECT
        `title`,
        `artist`,
        `album`,
        `genre`,
        `duration_seconds`,
        1,
        NOW(),
        NULL
    FROM `tmp_song_bulk_import`
    ORDER BY `row_number`;

    SET v_inserted_count = ROW_COUNT();

    COMMIT;

    DROP TEMPORARY TABLE IF EXISTS `tmp_song_bulk_import`;

    SELECT
        'success' AS `status`,
        v_inserted_count AS `inserted_count`;
END$$

DELIMITER ;
