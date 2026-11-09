<?php

namespace App\Models;

use App\Controllers\Services\Logger;
use PDO;
use PDOException;

class Database
{
    private static ?PDO $conn = null;

    public static function getConnection(): PDO
    {
        if (self::$conn == null) {

            try {
                self::$conn = new PDO(
                    "mysql:dbname={$_ENV['DB_NAME']};host={$_ENV['HOST_NAME']};charset=utf8mb4",
                    $_ENV['DB_USERNAME'],
                    $_ENV['DB_PASSWORD'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
            } catch (PDOException $e) {
                Logger::error('Erro de banco de dados', ['PDOException' => $e->getMessage()]);
                $_SESSION['message'] = ['Houve um erro sério, favor contatar o desenvolvedor do sistema', 'fail'];
                header('Location: ' . $_ENV['BASE_URL'] . '/login');
                exit;
            }
        }

        return self::$conn;
    }

    public static function countAll(string $table): int
    {
        $conn = Database::getConnection();
        $stmt = $conn->query("SELECT COUNT(*) FROM $table");
        if ($stmt == false) {
            $_SESSION['message'] = ['Erro inesperado, favor contatar o desenvolvedor do sistema', 'fail'];
            Logger::error("Falha ao contar registros", ["table" => $table]);
            exit;
        }
        return $stmt->fetch()[0];
    }

    public static function countDeleted(string $table, bool $deleted): int
    {
        $deleted = ($deleted) ? 1 : 0;
        $conn = Database::getConnection();
        $stmt = $conn->query("SELECT COUNT(*) FROM $table WHERE deleted = $deleted");
        if ($stmt == false) {
            $_SESSION['message'] = ['Erro inesperado, favor contatar o desenvolvedor do sistema', 'fail'];
            Logger::error("Falha ao contar registros (não-deletados)", ["table" => $table]);
            exit;
        }
        return $stmt->fetch()[0];
    }

    public static function getByPage(
        string $table,
        string $columns,
        int $rowsPerPage,
        int $page,
        bool $deleted
    ): array {
        if ($page == 0) {
            return [];
        }
        $page--;
        $offset = $rowsPerPage * $page;

        try {
            $conn = Database::getConnection();

            $stmt = $conn->prepare("SELECT $columns FROM $table WHERE deleted = :deleted LIMIT :limit OFFSET :offset");
            $stmt->bindValue(':deleted', $deleted, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $rowsPerPage, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            echo 'erro no getByPage' . $e->getMessage();
        }
        return [];
    }

    public static function getById(string $table, int $id): array|false
    {
        $stmt = self::getConnection()->query("SELECT * FROM $table WHERE id = $id");
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function getByColumn(string $table, string $column, string $value): array|false
    {
        try {
            $stmt = self::getConnection()->query("SELECT * FROM $table WHERE $column = $value");
            if (!$stmt) {
                return false;
            }
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $_SESSION['message'] = ['Erro inesperado, favor contatar o desenvolvedor do sistema', 'fail'];
            Logger::error("Falha ao recuperar por coluna", ["table" => $table, "column" => $column, 'value' => $value]);
            exit;
        }
    }

    public static function getLog(string $table, int $id): array
    {
        $entity = $table;

        if ($table == 'usuario') {
            $entity = $table . '_editado';
        }

        $sql = "SELECT * FROM log_$table WHERE $entity" . "_id = :id ORDER BY data_log DESC";

        try {
            $stmt = self::getConnection()->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            $_SESSION['message'] = ['Erro inesperado, favor contatar o desenvolvedor do sistema', 'fail'];
            Logger::error("Falha ao recuperar logs", ["table" => $table]);
            exit;
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retrieves every enabled row's name and id, used as <option></option> in forms
     *
     * @param string $table
     * @return array
     */
    public static function getOptions(string $table): array
    {
        $stmt = self::getConnection()->query("SELECT id, nome FROM $table WHERE `enabled` = 1");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getReport(
        $startDate,
        $endDate,
        $naturezaId = 'all',
        $centroId = 'all',
        $fornecedorId = 'all',
        $status = 'all'
    ): array {
        $isPaid = $status === 'paid';
        $isUnpaid = $status === 'unpaid';

        // "Pagas" usa o regime de caixa: cada linha é um pagamento do ledger.
        // "Não pagas" continua por parcela, mas mostra o saldo restante.
        $dateColumn = $isPaid ? 'pp.data_pagamento' : 'parcela.data_vencimento';

        $valorTotal = $isPaid
            ? 'pp.valor_em_centavos + COALESCE(pp.juros_em_centavos, 0)'
            : 'parcela.valor_em_centavos + COALESCE(parcela.juros_em_centavos, 0)'
                . ($isUnpaid ? ' - parcela.valor_pago_em_centavos' : '');

        $valorRestante = $isPaid
            ? '0'
            : 'parcela.valor_em_centavos + COALESCE(parcela.juros_em_centavos, 0) - parcela.valor_pago_em_centavos';

        $from = $isPaid
            ? 'parcela_pagamento pp INNER JOIN parcela ON pp.parcela_id = parcela.id'
            : 'parcela';

        try {
            $query = "SELECT 
                    parcela.data_vencimento,
                    " . ($isPaid ? 'pp.data_pagamento' : 'parcela.data_pagamento') . " AS data_pagamento,
                    natureza.nome natureza,
                    centro_de_custo.nome centro,
                    centro_de_custo.categoria_id,
                    categoria.nome as centro_pai,
                    fornecedor.nome fornecedor,
                    conta.historico,
                    parcela.numero_parcela,
                    CASE
                        WHEN parcela.paid = 0 AND parcela.valor_pago_em_centavos > 0 THEN 1
                        ELSE 0
                    END AS parcela_parcial,
                    (
                        SELECT COUNT(*)
                        FROM parcela p2
                        WHERE p2.conta_id = parcela.conta_id
                    ) AS total_parcelas,
                    $valorTotal AS valor_total_em_centavos,
                    " . ($isPaid ? 'pp.valor_em_centavos + COALESCE(pp.juros_em_centavos, 0)' : 'parcela.valor_pago_em_centavos') . " AS valor_pago_em_centavos,
                    $valorRestante AS valor_restante_em_centavos
                FROM $from
                INNER JOIN conta ON parcela.conta_id = conta.id
                INNER JOIN natureza ON conta.natureza_id = natureza.id
                INNER JOIN centro_de_custo ON conta.centro_de_custo_id = centro_de_custo.id
                LEFT JOIN centro_de_custo categoria ON centro_de_custo.categoria_id = categoria.id
                LEFT JOIN fornecedor ON conta.fornecedor_id = fornecedor.id
                WHERE 1 = 1
            ";

            switch ($status) {
                case 'unpaid':
                    $query .= ' AND parcela.paid = 0 AND parcela.enabled = 1 AND conta.enabled = 1';
                    break;
                case 'paid':
                    $query .= ' AND parcela.enabled = 1 AND conta.enabled = 1';
                    break;
                case 'inativadas':
                    $query .= ' AND conta.enabled = 0';
                    break;
                default:
                    $query .= ' AND parcela.enabled = 1 AND conta.enabled = 1';
            }

            if ($naturezaId != 'all') {
                $query .= " AND natureza.id = :natureza_id";
            }

            if ($centroId != 'all') {
                $query .= " AND (centro_de_custo.id = :centro_id OR centro_de_custo.categoria_id = :centro_id)";
            }

            if ($fornecedorId != 'all') {
                $query .= " AND fornecedor.id = :fornecedor_id";
            }

            $query .= " AND $dateColumn BETWEEN :startDate AND :endDate";

            $stmt = self::getConnection()->prepare($query);

            $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
            $stmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);

            if ($naturezaId != 'all') {
                $stmt->bindParam(':natureza_id', $naturezaId, PDO::PARAM_INT);
            }

            if ($centroId != 'all') {
                $stmt->bindParam(':centro_id', $centroId, PDO::PARAM_INT);
            }

            if ($fornecedorId != 'all') {
                $stmt->bindParam(':fornecedor_id', $fornecedorId, PDO::PARAM_INT);
            }

            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Logger::error('Erro ao gerar relatório: ' . $e->getMessage(), ['usuario_id' => $_SESSION['usuario_id']]);
            $_SESSION['message'] = ['Não foi possível gerar relatório', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/relatorios');
            exit;
        }
    }

    /**
     * Retorna os totais por natureza e centro de custo, já com os sub-centros agrupados
     * no centro de custo superior (pai_nome/pai_id). O agrupamento em memoria para exibir
     * ou nao os sub-centros acontece no controller, sem nova consulta.
     *
     * @param string $naturezaId
     * @param string $centroId
     * @param string $fornecedorId
     * @param string $status 'unpaid', 'paid' ou 'inativadas'
     * @return array
     */
    public static function getSinteticoReport(
        $startDate,
        $endDate,
        $naturezaId = 'all',
        $centroId = 'all',
        $fornecedorId = 'all',
        $status = 'all'
    ): array {
        $isPaid = $status === 'paid';
        $isUnpaid = $status === 'unpaid';

        $dateColumn = $isPaid ? 'pp.data_pagamento' : 'parcela.data_vencimento';

        $totalExpression = $isPaid
            ? 'pp.valor_em_centavos + COALESCE(pp.juros_em_centavos, 0)'
            : 'parcela.valor_em_centavos + COALESCE(parcela.juros_em_centavos, 0)'
                . ($isUnpaid ? ' - parcela.valor_pago_em_centavos' : '');

        $from = $isPaid
            ? 'parcela_pagamento pp INNER JOIN parcela ON pp.parcela_id = parcela.id'
            : 'parcela';

        try {
            $query = "SELECT
                    natureza.id natureza_id,
                    natureza.nome natureza,
                    centro_de_custo.id centro_id,
                    centro_de_custo.nome centro,
                    centro_de_custo.categoria_id,
                    COALESCE(categoria.id, centro_de_custo.id) pai_id,
                    COALESCE(categoria.nome, centro_de_custo.nome) pai_nome,
                    COUNT(*) quantidade,
                    SUM($totalExpression) total
                FROM $from
                INNER JOIN conta ON parcela.conta_id = conta.id
                INNER JOIN natureza ON conta.natureza_id = natureza.id
                INNER JOIN centro_de_custo ON conta.centro_de_custo_id = centro_de_custo.id
                LEFT JOIN centro_de_custo categoria ON centro_de_custo.categoria_id = categoria.id
                LEFT JOIN fornecedor ON conta.fornecedor_id = fornecedor.id
                WHERE 1 = 1
            ";

            switch ($status) {
                case 'unpaid':
                    $query .= ' AND parcela.paid = 0 AND parcela.enabled = 1 AND conta.enabled = 1';
                    break;
                case 'paid':
                    $query .= ' AND parcela.enabled = 1 AND conta.enabled = 1';
                    break;
                case 'inativadas':
                    $query .= ' AND conta.enabled = 0';
                    break;
                default:
                    $query .= ' AND parcela.enabled = 1 AND conta.enabled = 1';
            }

            if ($naturezaId != 'all') {
                $query .= " AND natureza.id = :natureza_id";
            }

            if ($centroId != 'all') {
                $query .= " AND (centro_de_custo.id = :centro_id OR centro_de_custo.categoria_id = :centro_id)";
            }

            if ($fornecedorId != 'all') {
                $query .= " AND fornecedor.id = :fornecedor_id";
            }

            $query .= " AND $dateColumn BETWEEN :startDate AND :endDate
                GROUP BY
                    natureza.id,
                    natureza.nome,
                    centro_de_custo.id,
                    centro_de_custo.nome,
                    centro_de_custo.categoria_id,
                    categoria.id,
                    categoria.nome
                ORDER BY natureza.nome, total DESC";

            $stmt = self::getConnection()->prepare($query);

            $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
            $stmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);

            if ($naturezaId != 'all') {
                $stmt->bindParam(':natureza_id', $naturezaId, PDO::PARAM_INT);
            }

            if ($centroId != 'all') {
                $stmt->bindParam(':centro_id', $centroId, PDO::PARAM_INT);
            }

            if ($fornecedorId != 'all') {
                $stmt->bindParam(':fornecedor_id', $fornecedorId, PDO::PARAM_INT);
            }

            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Logger::error('Erro ao gerar relatório sintético: ' . $e->getMessage(), ['usuario_id' => $_SESSION['usuario_id']]);
            $_SESSION['message'] = ['Não foi possível gerar relatório', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/relatorios');
            exit;
        }
    }

    /**
     * Retorna totais por centro de custo, somando os sub-centros no centro de
     * custo superior (independente de natureza). Usado pelo Relatório Sintético Geral.
     *
     * @param string $naturezaId
     * @param string $centroId
     * @param string $fornecedorId
     * @param string $status 'unpaid' ou 'paid'
     * @return array
     */
    public static function getSinteticoGeralReport(
        $startDate,
        $endDate,
        $naturezaId = 'all',
        $centroId = 'all',
        $fornecedorId = 'all',
        $status = 'all'
    ): array {
        $isPaid = $status === 'paid';
        $isUnpaid = $status === 'unpaid';

        $dateColumn = $isPaid ? 'pp.data_pagamento' : 'parcela.data_vencimento';

        $totalExpression = $isPaid
            ? 'pp.valor_em_centavos + COALESCE(pp.juros_em_centavos, 0)'
            : 'parcela.valor_em_centavos + COALESCE(parcela.juros_em_centavos, 0)'
                . ($isUnpaid ? ' - parcela.valor_pago_em_centavos' : '');

        $from = $isPaid
            ? 'parcela_pagamento pp INNER JOIN parcela ON pp.parcela_id = parcela.id'
            : 'parcela';

        try {
            $query = "SELECT
                    COALESCE(categoria.id, centro_de_custo.id) centro_id,
                    COALESCE(categoria.nome, centro_de_custo.nome) centro_nome,
                    COUNT(*) quantidade,
                    SUM($totalExpression) total
                FROM $from
                INNER JOIN conta ON parcela.conta_id = conta.id
                INNER JOIN natureza ON conta.natureza_id = natureza.id
                INNER JOIN centro_de_custo ON conta.centro_de_custo_id = centro_de_custo.id
                LEFT JOIN centro_de_custo categoria ON centro_de_custo.categoria_id = categoria.id
                LEFT JOIN fornecedor ON conta.fornecedor_id = fornecedor.id
                WHERE 1 = 1
            ";

            switch ($status) {
                case 'unpaid':
                    $query .= ' AND parcela.paid = 0 AND parcela.enabled = 1 AND conta.enabled = 1';
                    break;
                case 'paid':
                    $query .= ' AND parcela.enabled = 1 AND conta.enabled = 1';
                    break;
                default:
                    $query .= ' AND parcela.enabled = 1 AND conta.enabled = 1';
            }

            if ($naturezaId != 'all') {
                $query .= " AND natureza.id = :natureza_id";
            }

            if ($centroId != 'all') {
                $query .= " AND (centro_de_custo.id = :centro_id OR centro_de_custo.categoria_id = :centro_id)";
            }

            if ($fornecedorId != 'all') {
                $query .= " AND fornecedor.id = :fornecedor_id";
            }

            $query .= " AND $dateColumn BETWEEN :startDate AND :endDate
                GROUP BY
                    COALESCE(categoria.id, centro_de_custo.id),
                    COALESCE(categoria.nome, centro_de_custo.nome)
                ORDER BY centro_nome ASC";

            $stmt = self::getConnection()->prepare($query);

            $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
            $stmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);

            if ($naturezaId != 'all') {
                $stmt->bindParam(':natureza_id', $naturezaId, PDO::PARAM_INT);
            }

            if ($centroId != 'all') {
                $stmt->bindParam(':centro_id', $centroId, PDO::PARAM_INT);
            }

            if ($fornecedorId != 'all') {
                $stmt->bindParam(':fornecedor_id', $fornecedorId, PDO::PARAM_INT);
            }

            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Logger::error('Erro ao gerar relatório sintético geral: ' . $e->getMessage(), ['usuario_id' => $_SESSION['usuario_id']]);
            $_SESSION['message'] = ['Não foi possível gerar relatório', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/relatorios');
            exit;
        }
    }
}
