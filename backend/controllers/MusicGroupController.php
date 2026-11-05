<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';

/**
 * Controlador de Grupos: mapeo 1 a 1 con sp_music_group_*
 */
class MusicGroupController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getConnection();
    }

    /**
     * POST /api/groups -> CALL sp_music_group_create
     */
    public function create(): void
    {
        $body = Response::getJsonBody();

        $name = trim($body['name'] ?? '');
        $colorHex = !empty($body['color_hex']) ? trim((string)$body['color_hex']) : '#2563EB';
        $description = isset($body['description']) ? trim((string)$body['description']) : null;

        if ($name === '') {
            Response::error('El nombre del grupo es obligatorio.', 422);
        }

        try {
            $stmt = $this->db->prepare('CALL sp_music_group_create(:name, :color_hex, :description)');
            $stmt->execute([
                ':name'        => $name,
                ':color_hex'   => $colorHex,
                ':description' => $description
            ]);

            $group = $stmt->fetch();
            $stmt->closeCursor();

            Response::json($group, 201, null, 'Grupo creado exitosamente.');
        } catch (PDOException $e) {
            Response::error('Error al crear grupo: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/groups/{id} -> CALL sp_music_group_get_by_id
     */
    public function getById(int $id): void
    {
        if ($id <= 0) {
            Response::error('ID de grupo inválido.', 400);
        }

        try {
            $stmt = $this->db->prepare('CALL sp_music_group_get_by_id(:id)');
            $stmt->execute([':id' => $id]);
            $group = $stmt->fetch();
            $stmt->closeCursor();

            if (!$group) {
                Response::error('Grupo no encontrado o inactivo.', 404);
            }

            Response::json($group);
        } catch (PDOException $e) {
            Response::error('Error al consultar grupo: ' . $e->getMessage(), 500);
        }
    }

    /**
     * GET /api/groups -> CALL sp_music_group_list
     */
    public function list(): void
    {
        $search = isset($_GET['search']) ? trim((string)$_GET['search']) : null;
        $limit = isset($_GET['limit']) ? max(1, (int)$_GET['limit']) : 50;
        $page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
        $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : (($page - 1) * $limit);

        try {
            $stmt = $this->db->prepare('CALL sp_music_group_list(:search, :limit, :offset)');
            $stmt->execute([
                ':search' => $search,
                ':limit'  => $limit,
                ':offset' => $offset
            ]);

            $groups = $stmt->fetchAll();
            $stmt->closeCursor();

            Response::json($groups);
        } catch (PDOException $e) {
            Response::error('Error al listar grupos: ' . $e->getMessage(), 500);
        }
    }

    /**
     * PUT /api/groups/{id} -> CALL sp_music_group_update
     */
    public function update(int $id): void
    {
        if ($id <= 0) {
            Response::error('ID de grupo inválido.', 400);
        }

        $body = Response::getJsonBody();
        $name = trim($body['name'] ?? '');
        $colorHex = isset($body['color_hex']) ? trim((string)$body['color_hex']) : null;
        $description = isset($body['description']) ? trim((string)$body['description']) : null;

        if ($name === '') {
            Response::error('El nombre del grupo no puede estar vacío.', 422);
        }

        try {
            $stmt = $this->db->prepare('CALL sp_music_group_update(:id, :name, :color_hex, :description)');
            $stmt->execute([
                ':id'          => $id,
                ':name'        => $name,
                ':color_hex'   => $colorHex,
                ':description' => $description
            ]);

            $updated = $stmt->fetch();
            $stmt->closeCursor();

            if (!$updated) {
                Response::error('Grupo no encontrado para actualizar.', 404);
            }

            Response::json($updated, 200, null, 'Grupo actualizado exitosamente.');
        } catch (PDOException $e) {
            Response::error('Error al actualizar grupo: ' . $e->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/groups/{id} -> CALL sp_music_group_delete (Soft Delete)
     */
    public function delete(int $id): void
    {
        if ($id <= 0) {
            Response::error('ID de grupo inválido.', 400);
        }

        try {
            $stmt = $this->db->prepare('CALL sp_music_group_delete(:id)');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            $stmt->closeCursor();

            $affected = (int)($row['affected_rows'] ?? 0);
            if ($affected === 0) {
                Response::error('Grupo no encontrado o ya inactivo.', 404);
            }

            Response::json(['id' => $id, 'deleted' => true], 200, null, 'Grupo desactivado lógicamente.');
        } catch (PDOException $e) {
            Response::error('Error al eliminar grupo: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/groups/{id}/restore -> CALL sp_music_group_restore
     */
    public function restore(int $id): void
    {
        if ($id <= 0) {
            Response::error('ID de grupo inválido.', 400);
        }

        try {
            $stmt = $this->db->prepare('CALL sp_music_group_restore(:id)');
            $stmt->execute([':id' => $id]);
            $group = $stmt->fetch();
            $stmt->closeCursor();

            if (!$group) {
                Response::error('Grupo no encontrado.', 404);
            }

            Response::json($group, 200, null, 'Grupo restaurado exitosamente.');
        } catch (PDOException $e) {
            Response::error('Error al restaurar grupo: ' . $e->getMessage(), 500);
        }
    }
}
