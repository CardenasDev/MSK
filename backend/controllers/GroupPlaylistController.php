<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';

/**
 * Controlador para la tabla pivote group_playlists: mapeo 1 a 1 con sp_group_playlist_*
 */
class GroupPlaylistController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * GET /api/groups/{id}/playlists -> CALL sp_group_playlist_list
     */
    public function list(int $groupId): void
    {
        if ($groupId <= 0) {
            Response::error('ID de grupo inválido.', 400);
        }

        try {
            $stmt = $this->db->prepare('CALL sp_group_playlist_list(:group_id)');
            $stmt->execute([':group_id' => $groupId]);
            $playlists = $stmt->fetchAll();
            $stmt->closeCursor();

            Response::json($playlists);
        } catch (PDOException $e) {
            Response::error('Error al listar playlists del grupo: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/groups/{id}/playlists -> CALL sp_group_playlist_add
     * Body: { "playlist_id": 5, "position": 0 }
     */
    public function add(int $groupId): void
    {
        if ($groupId <= 0) {
            Response::error('ID de grupo inválido.', 400);
        }

        $body = Response::getJsonBody();
        $playlistId = isset($body['playlist_id']) ? (int)$body['playlist_id'] : 0;
        $position = isset($body['position']) ? (int)$body['position'] : 0;

        if ($playlistId <= 0) {
            Response::error('El playlist_id es obligatorio y debe ser mayor a 0.', 422);
        }

        try {
            $stmt = $this->db->prepare('CALL sp_group_playlist_add(:group_id, :playlist_id, :position)');
            $stmt->execute([
                ':group_id'    => $groupId,
                ':playlist_id' => $playlistId,
                ':position'    => $position
            ]);

            $relation = $stmt->fetch();
            $stmt->closeCursor();

            Response::json($relation, 201, null, 'Playlist asignada al grupo.');
        } catch (PDOException $e) {
            Response::error('Error al asignar playlist a grupo: ' . $e->getMessage(), 500);
        }
    }

        /**
         * POST /api/playlists/{id}/groups -> Añade/reactiva una playlist en varios grupos.
         */
        public function addToGroups(int $playlistId): void
        {
            if ($playlistId <= 0) {
                Response::error('ID de playlist inválido.', 400);
            }

            $body = Response::getJsonBody();
            $groupIds = $body['group_ids'] ?? null;
            if (!is_array($groupIds) || $groupIds === []) {
                Response::error('group_ids debe ser un arreglo no vacío.', 422);
            }

            $groupIds = array_values(array_unique(array_filter(
                array_map(static fn (mixed $id): int => (int)$id, $groupIds),
                static fn (int $id): bool => $id > 0
            )));
            if ($groupIds === []) {
                Response::error('group_ids debe contener IDs válidos.', 422);
            }

            try {
                $this->db->beginTransaction();
                $playlistStmt = $this->db->prepare('SELECT id FROM playlists WHERE id = :playlist_id AND is_active = 1 FOR UPDATE');
                $playlistStmt->execute([':playlist_id' => $playlistId]);
                if (!$playlistStmt->fetch()) {
                    $this->db->rollBack();
                    Response::error('Playlist no encontrada o inactiva.', 404);
                }

                $placeholders = implode(',', array_fill(0, count($groupIds), '?'));
                $groupStmt = $this->db->prepare("SELECT id FROM music_groups WHERE id IN ({$placeholders}) AND is_active = 1 FOR UPDATE");
                $groupStmt->execute($groupIds);
                $availableIds = array_map('intval', $groupStmt->fetchAll(PDO::FETCH_COLUMN));
                $missingIds = array_values(array_diff($groupIds, $availableIds));
                if ($missingIds !== []) {
                    $this->db->rollBack();
                    Response::error('Uno o más grupos no existen o están inactivos.', 404, [
                        ['field' => 'group_ids', 'invalid_ids' => $missingIds]
                    ]);
                }

                $relationStmt = $this->db->prepare(
                    'SELECT id, is_active FROM group_playlists
                     WHERE group_id = :group_id AND playlist_id = :playlist_id FOR UPDATE'
                );
                $positionStmt = $this->db->prepare(
                    'SELECT COALESCE(MAX(position), 0) + 1 FROM group_playlists
                     WHERE group_id = :group_id AND is_active = 1'
                );
                $insertStmt = $this->db->prepare(
                    'INSERT INTO group_playlists
                        (group_id, playlist_id, position, is_active, assigned_at, deleted_at)
                     VALUES (:group_id, :playlist_id, :position, 1, NOW(), NULL)'
                );
                $reactivateStmt = $this->db->prepare(
                    'UPDATE group_playlists
                     SET is_active = 1, deleted_at = NULL, assigned_at = NOW(), position = :position
                     WHERE id = :id'
                );

                $added = 0;
                $reactivated = 0;
                foreach ($availableIds as $groupId) {
                    $relationStmt->execute([':group_id' => $groupId, ':playlist_id' => $playlistId]);
                    $relation = $relationStmt->fetch();
                    if ($relation && (int)$relation['is_active'] === 1) {
                        continue;
                    }

                    $positionStmt->execute([':group_id' => $groupId]);
                    $position = (int)$positionStmt->fetchColumn();
                    if ($relation) {
                        $reactivateStmt->execute([':id' => (int)$relation['id'], ':position' => $position]);
                        $reactivated++;
                    } else {
                        $insertStmt->execute([
                            ':group_id' => $groupId,
                            ':playlist_id' => $playlistId,
                            ':position' => $position
                        ]);
                        $added++;
                    }
                }

                $this->db->commit();
                Response::json([
                    'playlist_id' => $playlistId,
                    'group_ids' => $availableIds,
                    'added' => $added,
                    'reactivated' => $reactivated,
                    'skipped' => count($availableIds) - $added - $reactivated
                ], 201, null, 'Playlist añadida a los grupos seleccionados.');
            } catch (PDOException $e) {
                if ($this->db->inTransaction()) {
                    $this->db->rollBack();
                }
                Response::error('Error al añadir playlist a los grupos: ' . $e->getMessage(), 500);
            }
        }

    /**
     * DELETE /api/groups/{groupId}/playlists/{playlistId} -> CALL sp_group_playlist_remove
     */
    public function remove(int $groupId, int $playlistId): void
    {
        if ($groupId <= 0 || $playlistId <= 0) {
            Response::error('IDs de grupo y playlist deben ser válidos.', 400);
        }

        try {
            $stmt = $this->db->prepare('CALL sp_group_playlist_remove(:group_id, :playlist_id)');
            $stmt->execute([
                ':group_id'    => $groupId,
                ':playlist_id' => $playlistId
            ]);

            $row = $stmt->fetch();
            $stmt->closeCursor();

            $affected = (int)($row['affected_rows'] ?? 0);
            if ($affected === 0) {
                Response::error('Relación no encontrada o ya inactiva.', 404);
            }

            Response::json([
                'group_id'    => $groupId,
                'playlist_id' => $playlistId,
                'removed'     => true
            ], 200, null, 'Playlist desvinculada lógicamente del grupo.');
        } catch (PDOException $e) {
            Response::error('Error al remover playlist de grupo: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/groups/{groupId}/playlists/{playlistId}/reorder -> CALL sp_group_playlist_reorder
     * Body: { "position": 1 }
     */
    public function reorder(int $groupId, int $playlistId): void
    {
        if ($groupId <= 0 || $playlistId <= 0) {
            Response::error('IDs de grupo y playlist deben ser válidos.', 400);
        }

        $body = Response::getJsonBody();
        $newPosition = isset($body['position']) ? (int)$body['position'] : 0;

        if ($newPosition <= 0) {
            Response::error('La nueva posición debe ser un número entero mayor a 0.', 422);
        }

        try {
            $stmt = $this->db->prepare('CALL sp_group_playlist_reorder(:group_id, :playlist_id, :new_position)');
            $stmt->execute([
                ':group_id'      => $groupId,
                ':playlist_id'   => $playlistId,
                ':new_position'  => $newPosition
            ]);

            $row = $stmt->fetch();
            $stmt->closeCursor();

            Response::json([
                'group_id'     => $groupId,
                'playlist_id'  => $playlistId,
                'new_position' => $newPosition
            ], 200, null, 'Posición de playlist en grupo actualizada.');
        } catch (PDOException $e) {
            Response::error('Error al reordenar playlist en grupo: ' . $e->getMessage(), 500);
        }
    }
}
