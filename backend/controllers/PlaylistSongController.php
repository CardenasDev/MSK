<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';

/**
 * Controlador para la tabla pivote playlist_songs: mapeo 1 a 1 con sp_playlist_song_*
 */
class PlaylistSongController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * GET /api/playlists/{id}/songs -> CALL sp_playlist_song_list
     */
    public function list(int $playlistId): void
    {
        if ($playlistId <= 0) {
            Response::error('ID de lista inválido.', 400);
        }

        try {
            $stmt = $this->db->prepare('CALL sp_playlist_song_list(:playlist_id)');
            $stmt->execute([':playlist_id' => $playlistId]);
            $songs = $stmt->fetchAll();
            $stmt->closeCursor();

            Response::json($songs);
        } catch (PDOException $e) {
            Response::error('Error al listar canciones de la playlist: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/playlists/{id}/songs -> CALL sp_playlist_song_add
     * Body: { "song_id": 45, "position": 0 } (position 0 = al final)
     */
    public function add(int $playlistId): void
    {
        if ($playlistId <= 0) {
            Response::error('ID de lista inválido.', 400);
        }

        $body = Response::getJsonBody();
        $songId = isset($body['song_id']) ? (int)$body['song_id'] : 0;
        $position = isset($body['position']) ? (int)$body['position'] : 0;

        if ($songId <= 0) {
            Response::error('El song_id es obligatorio y debe ser mayor a 0.', 422);
        }

        try {
            $stmt = $this->db->prepare('CALL sp_playlist_song_add(:playlist_id, :song_id, :position)');
            $stmt->execute([
                ':playlist_id' => $playlistId,
                ':song_id'     => $songId,
                ':position'    => $position
            ]);

            $relation = $stmt->fetch();
            $stmt->closeCursor();

            Response::json($relation, 201, null, 'Canción añadida a la lista.');
        } catch (PDOException $e) {
            Response::error('Error al añadir canción a playlist: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/playlists/{playlistId}/songs/{songId} -> CALL sp_playlist_song_remove
     */
    public function remove(int $playlistId, int $songId): void
    {
        if ($playlistId <= 0 || $songId <= 0) {
            Response::error('IDs de playlist y canción deben ser válidos.', 400);
        }

        try {
            $stmt = $this->db->prepare('CALL sp_playlist_song_remove(:playlist_id, :song_id)');
            $stmt->execute([
                ':playlist_id' => $playlistId,
                ':song_id'     => $songId
            ]);

            $row = $stmt->fetch();
            $stmt->closeCursor();

            $affected = (int)($row['affected_rows'] ?? 0);
            if ($affected === 0) {
                Response::error('Relación no encontrada o ya inactiva.', 404);
            }

            Response::json([
                'playlist_id' => $playlistId,
                'song_id'     => $songId,
                'removed'     => true
            ], 200, null, 'Canción desvinculada lógicamente de la lista.');
        } catch (PDOException $e) {
            Response::error('Error al remover canción de playlist: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/playlists/{playlistId}/songs/{songId}/reorder -> CALL sp_playlist_song_reorder
     * Body: { "position": 2 }
     */
    public function reorder(int $playlistId, int $songId): void
    {
        if ($playlistId <= 0 || $songId <= 0) {
            Response::error('IDs de playlist y canción deben ser válidos.', 400);
        }

        $body = Response::getJsonBody();
        $newPosition = isset($body['position']) ? (int)$body['position'] : 0;

        if ($newPosition <= 0) {
            Response::error('La nueva posición debe ser un número entero mayor a 0.', 422);
        }

        try {
            $stmt = $this->db->prepare('CALL sp_playlist_song_reorder(:playlist_id, :song_id, :new_position)');
            $stmt->execute([
                ':playlist_id'   => $playlistId,
                ':song_id'       => $songId,
                ':new_position'  => $newPosition
            ]);

            $row = $stmt->fetch();
            $stmt->closeCursor();

            Response::json([
                'playlist_id'  => $playlistId,
                'song_id'      => $songId,
                'new_position' => $newPosition
            ], 200, null, 'Posición de canción actualizada.');
        } catch (PDOException $e) {
            Response::error('Error al reordenar canción: ' . $e->getMessage(), 500);
        }
    }
}
