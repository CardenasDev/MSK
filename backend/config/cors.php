<?php
declare(strict_types=1);

/**
 * Middleware para cabeceras CORS (Cross-Origin Resource Sharing).
 * Permite que Angular consuma la API sin bloqueos de navegador.
 */
function handleCors(): void
{
    // Permitir cualquier origen en entorno de desarrollo local (o especificar http://localhost:4200)
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
    header("Access-Control-Allow-Origin: {$origin}");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Max-Age: 86400");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Origin, Accept");

    // Si es una petición de preflight OPTIONS, responder 204 y finalizar
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit(0);
    }
}
