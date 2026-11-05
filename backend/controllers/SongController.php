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
     * Query params: search, genre, limit (default 25), offset (default 0)
     */
    public function list(): void
    {
        $search = isset($_GET['search']) ? trim((string)$_GET['search']) : null;
        $genre = isset($_GET['genre']) ? trim((string)$_GET['genre']) : null;
        $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 25;
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : (($page - 1) * $limit);

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
                Response::error('Canción no encontrada o ya inactiva.', 404);
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
