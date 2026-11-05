<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';

/**
 * Controlador de Listas de Reproducción: mapeo 1 a 1 con sp_playlist_*
 */
class PlaylistController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * POST /api/playlists -> CALL sp_playlist_create
     */
    public function create(): void
    {
        $body = Response::getJsonBody();

        $name = trim($body['name'] ?? '');
        $description = isset($body['description']) ? trim((string)$body['description']) : null;

        if ($name === '') {
            Response::error('El nombre de la lista de reproducción es obligatorio.', 422);
        }

        try {
            $stmt = $this->db->prepare('CALL sp_playlist_create(:name, :description)');
            $stmt->execute([
                ':name'        => $name,
                ':description' => $description
            ]);

            $playlist = $stmt->fetch();
            $stmt->closeCursor();

            Response::json($playlist, 201, null, 'Lista creada exitosamente.');
        } catch (PDOException $e) {
            Response::error('Error al crear lista: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/playlists/{id} -> CALL sp_playlist_get_by_id
     */
    public function getById(int $id): void
    {
        if ($id <= 0) {
            Response::error('ID de lista inválido.', 400);
        }

        try {
            $stmt = $this->db->prepare('CALL sp_playlist_get_by_id(:id)');
            $stmt->execute([':id' => $id]);
            $playlist = $stmt->fetch();
            $stmt->closeCursor();

            if (!$playlist) {
                Response::error('Lista no encontrada o inactiva.', 404);
            }

            Response::json($playlist);
        } catch (PDOException $e) {
            Response::error('Error al obtener lista: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/playlists -> CALL sp_playlist_list
     * Query params: search, limit (default 50), offset (default 0)
     */
    public function list(): void
    {
        $search = isset($_GET['search']) ? trim((string)$_GET['search']) : null;
        $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 50;
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : (($page - 1) * $limit);

        try {
            $stmt = $this->db->prepare('CALL sp_playlist_list(:search, :limit, :offset)');
            $stmt->execute([
                ':search' => $search,
                ':limit'  => $limit,
                ':offset' => $offset
            ]);

            $playlists = $stmt->fetchAll();
            $stmt->closeCursor();

            Response::json($playlists);
        } catch (PDOException $e) {
            Response::error('Error al listar playlists: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/playlists/{id} -> CALL sp_playlist_update
     */
    public function update(int $id): void
    {
        if ($id <= 0) {
            Response::error('ID de lista inválido.', 400);
        }

        $body = Response::getJsonBody();
        $name = trim($body['name'] ?? '');
        $description = isset($body['description']) ? trim((string)$body['description']) : null;

        if ($name === '') {
            Response::error('El nombre de la lista no puede estar vacío.', 422);
        }

        try {
            $stmt = $this->db->prepare('CALL sp_playlist_update(:id, :name, :description)');
            $stmt->execute([
                ':id'          => $id,
                ':name'        => $name,
                ':description' => $description
            ]);

            $updated = $stmt->fetch();
            $stmt->closeCursor();

            if (!$updated) {
                Response::error('Lista no encontrada para actualizar.', 404);
            }

            Response::json($updated, 200, null, 'Lista actualizada exitosamente.');
        } catch (PDOException $e) {
            Response::error('Error al actualizar lista: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/playlists/{id} -> CALL sp_playlist_delete (Soft Delete)
     */
    public function delete(int $id): void
    {
        if ($id <= 0) {
            Response::error('ID de lista inválido.', 400);
        }

        try {
            $stmt = $this->db->prepare('CALL sp_playlist_delete(:id)');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            $stmt->closeCursor();

            $affected = (int)($row['affected_rows'] ?? 0);
            if ($affected === 0) {
                Response::error('Lista no encontrada o ya inactiva.', 404);
            }

            Response::json(['id' => $id, 'deleted' => true], 200, null, 'Lista desactivada lógicamente.');
        } catch (PDOException $e) {
            Response::error('Error al eliminar lista: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/playlists/{id}/restore -> CALL sp_playlist_restore
     */
    public function restore(int $id): void
    {
        if ($id <= 0) {
            Response::error('ID de lista inválido.', 400);
        }

        try {
            $stmt = $this->db->prepare('CALL sp_playlist_restore(:id)');
            $stmt->execute([':id' => $id]);
            $playlist = $stmt->fetch();
            $stmt->closeCursor();

            if (!$playlist) {
                Response::error('Lista no encontrada.', 404);
            }

            Response::json($playlist, 200, null, 'Lista restaurada exitosamente.');
        } catch (PDOException $e) {
            Response::error('Error al restaurar lista: ' . $e->getMessage(), 500);
        }
    }
}
