<?php
declare(strict_types=1);

/**
 * Front Controller & Router de la API RESTful (PHP 8.2+)
 * Base de Datos: c_msk
 */

require_once __DIR__ . '/config/cors.php';
require_once __DIR__ . '/utils/Response.php';

require_once __DIR__ . '/controllers/SongController.php';
require_once __DIR__ . '/controllers/PlaylistController.php';
require_once __DIR__ . '/controllers/MusicGroupController.php';
require_once __DIR__ . '/controllers/PlaylistSongController.php';
require_once __DIR__ . '/controllers/GroupPlaylistController.php';

// 1. Activar cabeceras CORS
handleCors();

// 2. Normalizar la ruta solicitada
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$scriptName = dirname($_SERVER['SCRIPT_NAME'] ?? '');

// Si se ejecuta en subcarpeta (ej. Apache XAMPP /MSK/backend/), limpiar prefijo
if ($scriptName !== '/' && str_starts_with($requestUri, $scriptName)) {
    $path = substr($requestUri, strlen($scriptName));
} else {
    $path = $requestUri;
}

// Remover index.php si viene en la URL
$path = preg_replace('#^/index\.php#', '', $path);
$path = '/' . trim($path, '/');

// Permitir pasar ruta por query param si no hay mod_rewrite (?route=/api/songs)
if ($path === '/' && isset($_GET['route'])) {
    $path = '/' . trim((string)$_GET['route'], '/');
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// Instanciar controladores
$songCtrl = new SongController();
$playlistCtrl = new PlaylistController();
$groupCtrl = new MusicGroupController();
$playlistSongCtrl = new PlaylistSongController();
$groupPlaylistCtrl = new GroupPlaylistController();

// 3. Tabla de Enrutamiento RESTful

// --- Endpoint raíz / Healthcheck ---
if ($path === '' || $path === '/' || $path === '/api') {
    Response::json([
        'api'     => 'MSK Music Manager API',
        'version' => '1.0.0',
        'status'  => 'online',
        'db'      => 'c_msk'
    ]);
}

// =============================================================================
// RUTAS: CANCIONES (/api/songs)
// =============================================================================

// POST /api/songs -> Crear
if ($method === 'POST' && $path === '/api/songs') {
    $songCtrl->create();
}

// GET /api/songs -> Listar/Buscar
if ($method === 'GET' && $path === '/api/songs') {
    $songCtrl->list();
}

// POST /api/songs/{id}/playlists -> Añadir canción a varias playlists
if ($method === 'POST' && preg_match('#^/api/songs/(\d+)/playlists$#', $path, $matches)) {
    $songCtrl->addToPlaylists((int)$matches[1]);
}

// GET /api/songs/{id}/playlists -> Listar playlists de una canción
if ($method === 'GET' && preg_match('#^/api/songs/(\d+)/playlists$#', $path, $matches)) {
    $songCtrl->listPlaylists((int)$matches[1]);
}

// POST /api/songs/{id}/restore -> Restaurar borrado lógico
if ($method === 'POST' && preg_match('#^/api/songs/(\d+)/restore$#', $path, $matches)) {
    $songCtrl->restore((int)$matches[1]);
}

// GET /api/songs/{id} -> Detalle
if ($method === 'GET' && preg_match('#^/api/songs/(\d+)$#', $path, $matches)) {
    $songCtrl->getById((int)$matches[1]);
}

// PUT /api/songs/{id} -> Actualizar
if ($method === 'PUT' && preg_match('#^/api/songs/(\d+)$#', $path, $matches)) {
    $songCtrl->update((int)$matches[1]);
}

// DELETE /api/songs/{id} -> Borrado lógico
if ($method === 'DELETE' && preg_match('#^/api/songs/(\d+)$#', $path, $matches)) {
    $songCtrl->delete((int)$matches[1]);
}

// =============================================================================
// RUTAS: PIVOTE PLAYLIST-SONGS (/api/playlists/{id}/songs)
// =============================================================================

// PUT /api/playlists/{playlistId}/songs/{songId}/reorder -> Reordenar posición
if ($method === 'PUT' && preg_match('#^/api/playlists/(\d+)/songs/(\d+)/reorder$#', $path, $matches)) {
    $playlistSongCtrl->reorder((int)$matches[1], (int)$matches[2]);
}

// DELETE /api/playlists/{playlistId}/songs/{songId} -> Remover canción de lista
if ($method === 'DELETE' && preg_match('#^/api/playlists/(\d+)/songs/(\d+)$#', $path, $matches)) {
    $playlistSongCtrl->remove((int)$matches[1], (int)$matches[2]);
}

// GET /api/playlists/{id}/songs -> Listar canciones de la playlist
if ($method === 'GET' && preg_match('#^/api/playlists/(\d+)/songs$#', $path, $matches)) {
    $playlistSongCtrl->list((int)$matches[1]);
}

// POST /api/playlists/{id}/songs -> Añadir canción a la playlist
if ($method === 'POST' && preg_match('#^/api/playlists/(\d+)/songs$#', $path, $matches)) {
    $playlistSongCtrl->add((int)$matches[1]);
}

// =============================================================================
// RUTAS: PLAYLISTS (/api/playlists)
// =============================================================================

// POST /api/playlists -> Crear
if ($method === 'POST' && $path === '/api/playlists') {
    $playlistCtrl->create();
}

// GET /api/playlists -> Listar
if ($method === 'GET' && $path === '/api/playlists') {
    $playlistCtrl->list();
}

// POST /api/playlists/{id}/restore -> Restaurar borrado lógico
if ($method === 'POST' && preg_match('#^/api/playlists/(\d+)/restore$#', $path, $matches)) {
    $playlistCtrl->restore((int)$matches[1]);
}

// GET /api/playlists/{id} -> Detalle
if ($method === 'GET' && preg_match('#^/api/playlists/(\d+)$#', $path, $matches)) {
    $playlistCtrl->getById((int)$matches[1]);
}

// POST /api/playlists/{id}/groups -> Añadir playlist a varios grupos
if ($method === 'POST' && preg_match('#^/api/playlists/(\d+)/groups$#', $path, $matches)) {
    $groupPlaylistCtrl->addToGroups((int)$matches[1]);
}

// PUT /api/playlists/{id} -> Actualizar
if ($method === 'PUT' && preg_match('#^/api/playlists/(\d+)$#', $path, $matches)) {
    $playlistCtrl->update((int)$matches[1]);
}

// DELETE /api/playlists/{id} -> Borrado lógico
if ($method === 'DELETE' && preg_match('#^/api/playlists/(\d+)$#', $path, $matches)) {
    $playlistCtrl->delete((int)$matches[1]);
}

// =============================================================================
// RUTAS: PIVOTE GROUP-PLAYLISTS (/api/groups/{id}/playlists)
// =============================================================================

// PUT /api/groups/{groupId}/playlists/{playlistId}/reorder -> Reordenar playlist en grupo
if ($method === 'PUT' && preg_match('#^/api/groups/(\d+)/playlists/(\d+)/reorder$#', $path, $matches)) {
    $groupPlaylistCtrl->reorder((int)$matches[1], (int)$matches[2]);
}

// DELETE /api/groups/{groupId}/playlists/{playlistId} -> Remover playlist del grupo
if ($method === 'DELETE' && preg_match('#^/api/groups/(\d+)/playlists/(\d+)$#', $path, $matches)) {
    $groupPlaylistCtrl->remove((int)$matches[1], (int)$matches[2]);
}

// GET /api/groups/{id}/playlists -> Listar playlists del grupo
if ($method === 'GET' && preg_match('#^/api/groups/(\d+)/playlists$#', $path, $matches)) {
    $groupPlaylistCtrl->list((int)$matches[1]);
}

// POST /api/groups/{id}/playlists -> Añadir playlist al grupo
if ($method === 'POST' && preg_match('#^/api/groups/(\d+)/playlists$#', $path, $matches)) {
    $groupPlaylistCtrl->add((int)$matches[1]);
}

// =============================================================================
// RUTAS: GRUPOS (/api/groups)
// =============================================================================

// POST /api/groups -> Crear
if ($method === 'POST' && $path === '/api/groups') {
    $groupCtrl->create();
}

// GET /api/groups -> Listar
if ($method === 'GET' && $path === '/api/groups') {
    $groupCtrl->list();
}

// POST /api/groups/{id}/restore -> Restaurar borrado lógico
if ($method === 'POST' && preg_match('#^/api/groups/(\d+)/restore$#', $path, $matches)) {
    $groupCtrl->restore((int)$matches[1]);
}

// GET /api/groups/{id} -> Detalle
if ($method === 'GET' && preg_match('#^/api/groups/(\d+)$#', $path, $matches)) {
    $groupCtrl->getById((int)$matches[1]);
}

// PUT /api/groups/{id} -> Actualizar
if ($method === 'PUT' && preg_match('#^/api/groups/(\d+)$#', $path, $matches)) {
    $groupCtrl->update((int)$matches[1]);
}

// DELETE /api/groups/{id} -> Borrado lógico
if ($method === 'DELETE' && preg_match('#^/api/groups/(\d+)$#', $path, $matches)) {
    $groupCtrl->delete((int)$matches[1]);
}

// 4. Ruta no encontrada (404)
Response::error("Ruta no encontrada: [{$method}] {$path}", 404);
