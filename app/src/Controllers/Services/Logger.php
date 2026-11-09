<?php

namespace App\Controllers\Services;

use App\Models\Database;
use Monolog\Handler\StreamHandler;
use Monolog\Logger as Monolog;
use PDO;
use PDOException;

class Logger
{
    public static function error(string $message, array $context = [])
    {
        $log = new Monolog('monolog');
        $log->pushHandler(new StreamHandler('../Logs/errors.log'));

        $log->error($message, $context);
    }

    /**
     * A default log for inserts
     *
     * @param PDO $pdo connection to get lastInsertId
     * @param [type] $user_id who made insert
     */
    public static function log_create(PDO $pdo, string $table): void
    {
        Logger::log($table, 'create', '', '', $pdo->lastInsertId(), $_SESSION['usuario_id']);
    }

    public static function log_unable(string $table, int $entity_id, int $usuario_id): void
    {
        Logger::log($table, 'unable', '', '', $entity_id, $usuario_id);
    }

    public static function log_enable(string $table, int $entity_id, int $usuario_id): void
    {
        Logger::log($table, 'enable', '', '', $entity_id, $usuario_id);
    }

    public static function log(
        string $table,
        string $campo,
        string $valor_antigo,
        string $valor_novo,
        int|null $entidade_id,
        int $usuario_id
    ): void {

        try {
            $conn = Database::getConnection();
            $entityColumnName = $table != 'usuario' ? $table : 'usuario_editado';

            $sql = "INSERT INTO log_{$table} (
                        campo, valor_antigo, valor_novo, {$entityColumnName}_id, usuario_id) 
                    VALUES (
                        :campo, :valor_antigo, :valor_novo, :entidade_id, :usuario_id)";

            $stmt = $conn->prepare($sql);

            $stmt->bindValue(':campo', $campo, PDO::PARAM_STR);
            $stmt->bindValue(':valor_antigo', $valor_antigo, PDO::PARAM_STR);
            $stmt->bindValue(':valor_novo', $valor_novo, PDO::PARAM_STR);
            $stmt->bindValue(':entidade_id', $entidade_id, PDO::PARAM_INT);
            $stmt->bindValue(':usuario_id', $usuario_id, PDO::PARAM_INT);

            $stmt->execute();
        } catch (PDOException $e) {
            echo 'erro ao fazer log: ' . $e->getMessage();
        }
    }
}
