<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';

/**
 * Controlador de Canciones: mapeo 1 a 1 con sp_song_*
 */
class SongController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * POST /api/songs -> CALL sp_song_create
     */
    public function create(): void
    {
        $body = Response::getJsonBody();

        $title = trim($body['title'] ?? '');
        $artist = trim($body['artist'] ?? '');
        $album = !empty($body['album']) ? trim((string)$body['album']) : null;
        $genre = !empty($body['genre']) ? trim((string)$body['genre']) : null;
        $duration = isset($body['duration_seconds']) ? (int)$body['duration_seconds'] : 0;

        if ($title === '' || $artist === '') {
            Response::error('El título y el artista son obligatorios.', 422, [
                ['field' => 'title', 'message' => 'Título no puede estar vacío.'],
                ['field' => 'artist', 'message' => 'Artista no puede estar vacío.']
            ]);
        }

        try {
            $stmt = $this->db->prepare('CALL sp_song_create(:title, :artist, :album, :genre, :duration)');
            $stmt->execute([
                ':title'    => $title,
                ':artist'   => $artist,
                ':album'    => $album,
                ':genre'    => $genre,
                ':duration' => $duration
            ]);

            $song = $stmt->fetch();
            $stmt->closeCursor();

            Response::json($song, 201, null, 'Canción creada exitosamente.');
        } catch (PDOException $e) {
            Response::error('Error al crear canción: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/songs/{id} -> CALL sp_song_get_by_id
     */
    public function getById(int $id): void
    {
        if ($id <= 0) {
            Response::error('ID de canción inválido.', 400);
        }

        try {
            $stmt = $this->db->prepare('CALL sp_song_get_by_id(:id)');
            $stmt->execute([':id' => $id]);
            $song = $stmt->fetch();
            $stmt->closeCursor();

            if (!$song) {
                Response::error('Canción no encontrada o inactiva.', 404);
            }

            Response::json($song);
        } catch (PDOException $e) {
            Response::error('Error al consultar canción: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/songs -> CALL sp_song_list
     * Query params: search, genre, page (default 1), limit (default 25), offset (default derived)
     */
    public function list(): void
    {
        $defaultLimit = 25;
        $maxLimit = 100;
        $maxPage = 100000;
        $maxOffset = 10000000;
        $search = isset($_GET['search']) && is_string($_GET['search']) ? trim($_GET['search']) : null;
        $genre = isset($_GET['genre']) && is_string($_GET['genre']) ? trim($_GET['genre']) : null;
        $requestedLimit = isset($_GET['limit']) && is_string($_GET['limit'])
            ? filter_var($_GET['limit'], FILTER_VALIDATE_INT)
            : null;
        $limit = $requestedLimit === false || $requestedLimit === null || $requestedLimit < 1
            ? $defaultLimit
            : min($requestedLimit, $maxLimit);
        $hasPage = isset($_GET['page']) && is_string($_GET['page']);
        $requestedPage = $hasPage ? filter_var($_GET['page'], FILTER_VALIDATE_INT) : null;
        $page = $requestedPage === false || $requestedPage === null || $requestedPage < 1
            ? 1
            : min($requestedPage, $maxPage);
        $derivedOffset = ($page - 1) * $limit;
        $requestedOffset = !$hasPage && isset($_GET['offset']) && is_string($_GET['offset'])
            ? filter_var($_GET['offset'], FILTER_VALIDATE_INT)
            : null;
        $offset = $requestedOffset === false || $requestedOffset === null || $requestedOffset < 0
            ? $derivedOffset
            : min($requestedOffset, $maxOffset);
        if (!$hasPage && $requestedOffset !== false && $requestedOffset !== null && $requestedOffset >= 0) {
            $page = (int)floor($offset / $limit) + 1;
        }

        try {
            $stmt = $this->db->prepare('CALL sp_song_list(:search, :genre, :limit, :offset)');
            $stmt->execute([
                ':search' => $search,
                ':genre'  => $genre,
                ':limit'  => $limit,
                ':offset' => $offset
            ]);

            // 1. Primer Resultset: Conteo total de registros coincidentes
            $countRow = $stmt->fetch();
            $totalRecords = (int)($countRow['total_records'] ?? 0);

            // 2. Segundo Resultset: Registros paginados
            $stmt->nextRowset();
            $songs = $stmt->fetchAll();
            $stmt->closeCursor();

            if ($songs !== []) {
                $songIds = array_map(static fn (array $song): int => (int)$song['id'], $songs);
                $placeholders = implode(',', array_fill(0, count($songIds), '?'));
                $countStmt = $this->db->prepare(
                    "SELECT ps.song_id, COUNT(*) AS playlists_count
                     FROM playlist_songs ps
                     INNER JOIN playlists p ON p.id = ps.playlist_id AND p.is_active = 1
                     WHERE ps.song_id IN ({$placeholders}) AND ps.is_active = 1
                     GROUP BY ps.song_id"
                );
                $countStmt->execute($songIds);
                $playlistCounts = [];
                foreach ($countStmt->fetchAll() as $row) {
                    $playlistCounts[(int)$row['song_id']] = (int)$row['playlists_count'];
                }
                foreach ($songs as &$song) {
                    $song['playlists_count'] = $playlistCounts[(int)$song['id']] ?? 0;
                }
                unset($song);
            }

            $totalPages = $limit > 0 ? (int)ceil($totalRecords / $limit) : 1;

            $meta = [
                'total'      => $totalRecords,
                'page'       => $page,
                'limit'      => $limit,
                'offset'     => $offset,
                'totalPages' => $totalPages
            ];

            Response::json($songs, 200, $meta);
        } catch (PDOException $e) {
            Response::error('Error al listar canciones: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/songs/{id}/playlists -> Añade/reactiva una canción en varias playlists.
     */
    public function addToPlaylists(int $songId): void
    {
            if ($songId <= 0) {
                Response::error('ID de canción inválido.', 400);
            }

            $body = Response::getJsonBody();
            $playlistIds = $body['playlist_ids'] ?? null;

            if (!is_array($playlistIds) || $playlistIds === []) {
                Response::error('playlist_ids debe ser un arreglo no vacío.', 422);
            }

            $playlistIds = array_values(array_unique(array_filter(
                array_map(static fn (mixed $id): int => (int)$id, $playlistIds),
                static fn (int $id): bool => $id > 0
            )));

            if ($playlistIds === []) {
                Response::error('playlist_ids debe contener IDs válidos.', 422);
            }

            try {
                $this->db->beginTransaction();

                $songStmt = $this->db->prepare(
                    'SELECT id FROM songs WHERE id = :song_id AND is_active = 1 FOR UPDATE'
                );
                $songStmt->execute([':song_id' => $songId]);
                if (!$songStmt->fetch()) {
                    $this->db->rollBack();
                    Response::error('Canción no encontrada o inactiva.', 404);
                }

                $placeholders = implode(',', array_fill(0, count($playlistIds), '?'));
                $playlistStmt = $this->db->prepare(
                    "SELECT id FROM playlists WHERE id IN ({$placeholders}) AND is_active = 1 FOR UPDATE"
                );
                $playlistStmt->execute($playlistIds);
                $availableIds = array_map('intval', $playlistStmt->fetchAll(PDO::FETCH_COLUMN));
                $missingIds = array_values(array_diff($playlistIds, $availableIds));

                if ($missingIds !== []) {
                    $this->db->rollBack();
                    Response::error('Una o más playlists no existen o están inactivas.', 404, [
                        ['field' => 'playlist_ids', 'invalid_ids' => $missingIds]
                    ]);
                }

                $relationStmt = $this->db->prepare(
                    'SELECT id, is_active FROM playlist_songs
                     WHERE playlist_id = :playlist_id AND song_id = :song_id
                     FOR UPDATE'
                );
                $positionStmt = $this->db->prepare(
                    'SELECT COALESCE(MAX(position), 0) + 1
                     FROM playlist_songs
                     WHERE playlist_id = :playlist_id AND is_active = 1'
                );
                $insertStmt = $this->db->prepare(
                    'INSERT INTO playlist_songs
                        (playlist_id, song_id, position, is_active, added_at, deleted_at)
                     VALUES (:playlist_id, :song_id, :position, 1, NOW(), NULL)'
                );
                $reactivateStmt = $this->db->prepare(
                    'UPDATE playlist_songs
                     SET is_active = 1, deleted_at = NULL, added_at = NOW(), position = :position
                     WHERE id = :id'
                );

                $added = 0;
                $reactivated = 0;
                foreach ($availableIds as $playlistId) {
                    $relationStmt->execute([
                        ':playlist_id' => $playlistId,
                        ':song_id' => $songId
                    ]);
                    $relation = $relationStmt->fetch();

                    if ($relation && (int)$relation['is_active'] === 1) {
                        continue;
                    }

                    $positionStmt->execute([':playlist_id' => $playlistId]);
                    $position = (int)$positionStmt->fetchColumn();

                    if ($relation) {
                        $reactivateStmt->execute([
                            ':id' => (int)$relation['id'],
                            ':position' => $position
                        ]);
                        $reactivated++;
                    } else {
                        $insertStmt->execute([
                            ':playlist_id' => $playlistId,
                            ':song_id' => $songId,
                            ':position' => $position
                        ]);
                        $added++;
                    }
                }

                $this->db->commit();
                Response::json([
                    'song_id' => $songId,
                    'playlist_ids' => $availableIds,
                    'added' => $added,
                    'reactivated' => $reactivated,
                    'skipped' => count($availableIds) - $added - $reactivated
                ], 201, null, 'Canción añadida a las playlists seleccionadas.');
            } catch (PDOException $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                Response::error('Error al añadir canción a las playlists: ' . $e->getMessage(), 500);
            }
    }

    /**
     * GET /api/songs/{id}/playlists -> Lista las playlists activas de una canción.
     */
    public function listPlaylists(int $songId): void
    {
        if ($songId <= 0) {
            Response::error('ID de canción inválido.', 400);
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT p.id, p.name, p.description, p.created_at, p.updated_at
                 FROM playlist_songs ps
                 INNER JOIN playlists p ON p.id = ps.playlist_id AND p.is_active = 1
                 WHERE ps.song_id = :song_id AND ps.is_active = 1
                 ORDER BY p.name ASC'
            );
            $stmt->execute([':song_id' => $songId]);
            $playlists = $stmt->fetchAll();
            $stmt->closeCursor();

            Response::json($playlists);
        } catch (PDOException $e) {
            Response::error('Error al listar playlists de la canción: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/songs/{id} -> CALL sp_song_update
     */
    public function update(int $id): void
    {
        if ($id <= 0) {
            Response::error('ID de canción inválido.', 400);
        }

        $body = Response::getJsonBody();

        $title = trim($body['title'] ?? '');
        $artist = trim($body['artist'] ?? '');
        $album = isset($body['album']) ? trim((string)$body['album']) : null;
        $genre = isset($body['genre']) ? trim((string)$body['genre']) : null;
        $duration = isset($body['duration_seconds']) ? (int)$body['duration_seconds'] : 0;

        if ($title === '' || $artist === '') {
            Response::error('El título y el artista no pueden estar vacíos.', 422);
        }

        try {
            $stmt = $this->db->prepare('CALL sp_song_update(:id, :title, :artist, :album, :genre, :duration)');
            $stmt->execute([
                ':id'       => $id,
                ':title'    => $title,
                ':artist'   => $artist,
                ':album'    => $album,
                ':genre'    => $genre,
                ':duration' => $duration
            ]);

            $updated = $stmt->fetch();
            $stmt->closeCursor();

            if (!$updated) {
                Response::error('Canción no encontrada para actualizar.', 404);
            }

            Response::json($updated, 200, null, 'Canción actualizada correctamente.');
        } catch (PDOException $e) {
            Response::error('Error al actualizar canción: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/songs/{id} -> CALL sp_song_delete (Soft Delete)
     */
    public function delete(int $id): void
    {
        if ($id <= 0) {
            Response::error('ID de canción inválido.', 400);
        }

        try {
            $stmt = $this->db->prepare('CALL sp_song_delete(:id)');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            $stmt->closeCursor();

            $affected = (int)($row['affected_rows'] ?? 0);
            if ($affected === 0) {
                $verifyStmt = $this->db->prepare(
                    'SELECT id FROM songs WHERE id = :id AND is_active = 0'
                );
                $verifyStmt->execute([':id' => $id]);
                if (!$verifyStmt->fetch()) {
                    Response::error('Canción no encontrada o ya inactiva.', 404);
                }
            }

            Response::json(['id' => $id, 'deleted' => true], 200, null, 'Canción desactivada lógicamente.');
        } catch (PDOException $e) {
            Response::error('Error al eliminar canción: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/songs/{id}/restore -> CALL sp_song_restore
     */
    public function restore(int $id): void
    {
        if ($id <= 0) {
            Response::error('ID de canción inválido.', 400);
        }

        try {
            $stmt = $this->db->prepare('CALL sp_song_restore(:id)');
            $stmt->execute([':id' => $id]);
            $song = $stmt->fetch();
            $stmt->closeCursor();

            if (!$song) {
                Response::error('Canción no encontrada.', 404);
            }

            Response::json($song, 200, null, 'Canción restaurada exitosamente.');
        } catch (PDOException $e) {
            Response::error('Error al restaurar canción: ' . $e->getMessage(), 500);
        }
    }
}
