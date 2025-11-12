<?php

namespace App\Config;

use PDO;
use PDOException;

/**
 * Classe de configuração e conexão com banco de dados PostgreSQL
 * 
 * @package App\Config
 * @author SES-MS
 * @version 2.0.0
 */
class Database
{
    private static ?PDO $connection = null;
    private static array $config = [];

    /**
     * Inicializa a configuração do banco
     */
    public static function init(): void
    {
        self::$config = [
            'host' => $_ENV['DB_HOST'] ?? 'localhost',
            'port' => $_ENV['DB_PORT'] ?? '5432',
            'dbname' => $_ENV['DB_NAME'] ?? 'apsdigital',
            'username' => $_ENV['DB_USER'] ?? 'apsdigital',
            'password' => $_ENV['DB_PASS'] ?? '',
            'charset' => 'utf8',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]
        ];
    }

    /**
     * Obtém conexão com banco de dados (Singleton)
     * 
     * @return PDO
     * @throws PDOException
     */
    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            self::connect();
        }

        return self::$connection;
    }

    /**
     * Estabelece conexão com PostgreSQL
     * 
     * @throws PDOException
     */
    private static function connect(): void
    {
        if (empty(self::$config)) {
            self::init();
        }

        try {
            $dsn = sprintf(
                'pgsql:host=%s;port=%s;dbname=%s;charset=%s',
                self::$config['host'],
                self::$config['port'],
                self::$config['dbname'],
                self::$config['charset']
            );

            self::$connection = new PDO(
                $dsn,
                self::$config['username'],
                self::$config['password'],
                self::$config['options']
            );

            // Configurações específicas do PostgreSQL
            self::$connection->exec("SET timezone TO 'America/Campo_Grande'");
            self::$connection->exec("SET lc_time TO 'pt_BR.UTF-8'");

        } catch (PDOException $e) {
            error_log("Erro na conexão com banco: " . $e->getMessage());
            throw new PDOException("Erro de conexão com banco de dados", 500, $e);
        }
    }

    /**
     * Executa query preparada
     * 
     * @param string $query
     * @param array $params
     * @return \PDOStatement
     */
    public static function query(string $query, array $params = []): \PDOStatement
    {
        $stmt = self::getConnection()->prepare($query);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Busca um registro
     * 
     * @param string $query
     * @param array $params
     * @return array|null
     */
    public static function fetch(string $query, array $params = []): ?array
    {
        $result = self::query($query, $params)->fetch();
        return $result ?: null;
    }

    /**
     * Busca múltiplos registros
     * 
     * @param string $query
     * @param array $params
     * @return array
     */
    public static function fetchAll(string $query, array $params = []): array
    {
        return self::query($query, $params)->fetchAll();
    }

    /**
     * Executa INSERT/UPDATE/DELETE e retorna linhas afetadas
     * 
     * @param string $query
     * @param array $params
     * @return int
     */
    public static function execute(string $query, array $params = []): int
    {
        return self::query($query, $params)->rowCount();
    }

    /**
     * Retorna último ID inserido
     * 
     * @param string $sequence Nome da sequence (PostgreSQL)
     * @return string
     */
    public static function lastInsertId(string $sequence = null): string
    {
        return self::getConnection()->lastInsertId($sequence);
    }

    /**
     * Inicia transação
     * 
     * @return bool
     */
    public static function beginTransaction(): bool
    {
        return self::getConnection()->beginTransaction();
    }

    /**
     * Confirma transação
     * 
     * @return bool
     */
    public static function commit(): bool
    {
        return self::getConnection()->commit();
    }

    /**
     * Desfaz transação
     * 
     * @return bool
     */
    public static function rollback(): bool
    {
        return self::getConnection()->rollBack();
    }

    /**
     * Verifica se está em transação
     * 
     * @return bool
     */
    public static function inTransaction(): bool
    {
        return self::getConnection()->inTransaction();
    }

    /**
     * Fecha conexão
     */
    public static function disconnect(): void
    {
        self::$connection = null;
    }

    /**
     * Testa conexão com banco
     * 
     * @return bool
     */
    public static function testConnection(): bool
    {
        try {
            self::getConnection();
            self::query("SELECT 1");
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Obtém informações do banco
     * 
     * @return array
     */
    public static function getInfo(): array
    {
        try {
            $pdo = self::getConnection();
            return [
                'server_version' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
                'client_version' => $pdo->getAttribute(PDO::ATTR_CLIENT_VERSION),
                'driver_name' => $pdo->getAttribute(PDO::ATTR_DRIVER_NAME),
                'connection_status' => $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS)
            ];
        } catch (PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }
}