<?php
declare(strict_types=1);

/**
 * Configuración y conexión a la base de datos MySQL mediante PDO.
 * Base de Datos: c_msk
 */
class Database
{
    private static ?PDO $instance = null;

    private static string $host = '127.0.0.1';
    private static string $port = '3306';
    private static string $dbName = 'c_msk';
    private static string $username = 'root';
    private static string $password = ''; // Ajusta tu contraseña si aplica (por defecto en XAMPP es vacía)
    private static string $charset = 'utf8mb4';

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            // Permitir sobreescribir mediante variables de entorno si existen
            $host = getenv('DB_HOST') ?: self::$host;
            $port = getenv('DB_PORT') ?: self::$port;
            $dbName = getenv('DB_NAME') ?: self::$dbName;
            $username = getenv('DB_USER') ?: self::$username;
            $password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : self::$password;

            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=" . self::$charset;

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            try {
                self::$instance = new PDO($dsn, $username, $password, $options);
            } catch (PDOException $e) {
                http_response_code(500);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'status'  => 'error',
                    'message' => 'Error de conexión a la base de datos: ' . $e->getMessage()
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        return self::$instance;
    }
}
