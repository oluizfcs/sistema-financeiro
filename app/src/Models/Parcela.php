<?php

namespace App\Models;

use App\Models\Database;
use PDO;
use PDOException;
use App\Controllers\Services\Logger;

class Parcela
{
    public static string $tableName = 'parcela';
    public string $centro_de_custo;
    public string|null $centro_pai = null;
    public string|null $fornecedor;
    public string $historico;
    public string $natureza;
    public int $total_parcelas;

    public function __construct(
        private int $id,
        private int $numero_parcela,
        private int $valor_em_centavos,
        private string $data_vencimento,
        private string|null $data_pagamento,
        private int $conta_id,
        private int|null $banco_id,
        private bool $paid,
        private int $juros_em_centavos = 0,
        private int $valor_pago_em_centavos = 0
    ) {}

    /**
     * Get the value of id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the value of id
     *
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the value of numero_parcela
     */
    public function getNumero_parcela()
    {
        return $this->numero_parcela;
    }

    /**
     * Set the value of numero_parcela
     *
     * @return self
     */
    public function setNumero_parcela($numero_parcela)
    {
        $this->numero_parcela = $numero_parcela;

        return $this;
    }

    /**
     * Get the value of valor_em_centavos
     */
    public function getValor_em_centavos()
    {
        return $this->valor_em_centavos;
    }

    /**
     * Set the value of valor_em_centavos
     *
     * @return self
     */
    public function setValor_em_centavos($valor_em_centavos)
    {
        $this->valor_em_centavos = $valor_em_centavos;

        return $this;
    }

    /**
     * Get the value of juros_em_centavos
     */
    public function getJuros_em_centavos()
    {
        return $this->juros_em_centavos;
    }

    /**
     * Set the value of juros_em_centavos
     *
     * @return self
     */
    public function setJuros_em_centavos($juros_em_centavos)
    {
        $this->juros_em_centavos = $juros_em_centavos;

        return $this;
    }

    /**
     * Get the value of valor_pago_em_centavos (cache do livro caixa)
     */
    public function getValor_pago_em_centavos(): int
    {
        return $this->valor_pago_em_centavos;
    }

    /**
     * Set the value of valor_pago_em_centavos
     */
    public function setValor_pago_em_centavos($valor_pago_em_centavos)
    {
        $this->valor_pago_em_centavos = $valor_pago_em_centavos;

        return $this;
    }

    /**
     * Quanto ainda falta pagar nesta parcela (principal).
     */
    public function getValorRestante(): int
    {
        return $this->valor_em_centavos + $this->juros_em_centavos - $this->valor_pago_em_centavos;
    }

    /**
     * Parcela com pagamento registrado, mas ainda não quitada.
     */
    public function isParcial(): bool
    {
        return !$this->paid && $this->valor_pago_em_centavos > 0;
    }

    /**
     * Get the value of data_vencimento
     */
    public function getData_vencimento()
    {
        return $this->data_vencimento;
    }

    /**
     * Set the value of data_vencimento
     *
     * @return self
     */
    public function setData_vencimento($data_vencimento)
    {
        $this->data_vencimento = $data_vencimento;

        return $this;
    }

    /**
     * Get the value of data_pagamento
     */
    public function getData_pagamento(): string|null
    {
        return $this->data_pagamento;
    }

    /**
     * Set the value of data_pagamento
     *
     * @return self
     */
    public function setData_pagamento($data_pagamento)
    {
        $this->data_pagamento = $data_pagamento;

        return $this;
    }

    /**
     * Get the value of conta_id
     */
    public function getConta_id()
    {
        return $this->conta_id;
    }

    /**
     * Set the value of conta_id
     *
     * @return self
     */
    public function setConta_id($conta_id)
    {
        $this->conta_id = $conta_id;

        return $this;
    }

    /**
     * Get the value of paid
     */
    public function isPaid(): bool
    {
        return $this->paid;
    }

    /**
     * Set the value of paid
     *
     * @return self
     */
    public function setPaid($paid)
    {
        $this->paid = $paid;

        return $this;
    }

    public function getBanco_id(): int|null
    {
        return $this->banco_id;
    }

    public function setBanco_id($banco_id)
    {
        $this->banco_id = $banco_id;
    }

    public function save(): bool
    {
        try {
            $conn = Database::getConnection();
            $stmt = $conn->prepare('SELECT * FROM ' . self::$tableName . ' WHERE id = :id');
            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
            $stmt->execute();

            $entry = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($entry == false) {
                // criar
                $stmt = $conn->prepare('INSERT INTO ' . self::$tableName . ' (numero_parcela, valor_em_centavos, juros_em_centavos, data_vencimento, conta_id, paid) VALUES (:numero_parcela, :valor_em_centavos, :juros_em_centavos, :data_vencimento, :conta_id, :paid)');
                $stmt->bindParam(':numero_parcela', $this->numero_parcela, PDO::PARAM_INT);
                $stmt->bindParam(':valor_em_centavos', $this->valor_em_centavos, PDO::PARAM_INT);
                $stmt->bindParam(':juros_em_centavos', $this->juros_em_centavos, PDO::PARAM_INT);
                $stmt->bindParam(':data_vencimento', $this->data_vencimento, PDO::PARAM_STR);

                if ($this->conta_id != null) {
                    $stmt->bindParam(':conta_id', $this->conta_id, PDO::PARAM_INT);
                }

                $stmt->bindParam(':paid', $this->paid, PDO::PARAM_BOOL);

                $stmt->execute();

                // Logger::log_create($conn, self::$tableName);

                return true;
            } else {
                if ($entry['paid'] == 1) {
                    return false;
                }

                $stmt = $conn->prepare('UPDATE ' . self::$tableName . ' SET numero_parcela = :numero_parcela, valor_em_centavos = :valor_em_centavos, juros_em_centavos = :juros_em_centavos, data_vencimento = :data_vencimento, data_pagamento = :data_pagamento, banco_id = :banco_id, paid = :paid WHERE id = :id');

                $stmt->bindParam(':numero_parcela', $this->numero_parcela, PDO::PARAM_INT);

                $stmt->bindParam(':valor_em_centavos', $this->valor_em_centavos, PDO::PARAM_INT);
                $stmt->bindParam(':juros_em_centavos', $this->juros_em_centavos, PDO::PARAM_INT);
                $stmt->bindParam(':data_vencimento', $this->data_vencimento, PDO::PARAM_STR);
                $stmt->bindParam(':data_pagamento', $this->data_pagamento);
                $stmt->bindParam(':banco_id', $this->banco_id);
                $stmt->bindParam(':paid', $this->paid, PDO::PARAM_BOOL);
                $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);

                $stmt->execute();

                return true;
            }
        } catch (PDOException $e) {
            Logger::error('Erro ao cadastrar|atualizar conta', ['PDOException' => $e->getMessage()]);
            $_SESSION['message'] = ['Erro ao cadastrar|atualizar conta', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas');
            exit;
        }
    }

    /**
     * Coluna de data que deve ser usada para filtrar e ordenar parcelas conforme a situação
     * informada. Contas pagas são filtradas pela data de pagamento, as demais pela data de vencimento.
     *
     * @param string $status 'a pagar', 'a_pagar', 'pagas', 'paid', 'todas' ou 'inativadas'
     * @return string 'data_pagamento' ou 'data_vencimento'
     */
    public static function dateColumnForStatus(string $status): string
    {
        return match ($status) {
            'pagas', 'paid' => 'data_pagamento',
            default => 'data_vencimento',
        };
    }

    /**
     * Predicado único de visibilidade de uma cobrança ativa.
     *
     * Uma parcela só deve aparecer em listagens, totais e relatórios quando
     * ela e a conta dona estão habilitadas. Centralizar aqui evita que cada
     * consulta invente a sua própria combinação de filtros.
     */
    public static function activeCondition(string $parcelaAlias = 'parcela', string $contaAlias = 'conta'): string
    {
        return "{$parcelaAlias}.enabled = 1 AND {$contaAlias}.enabled = 1";
    }

    public static function getAll(
        string $status,
        string $naturezaId = 'all',
        string $centroId = 'all',
        string $fornecedorId = 'all',
        string $startDate = '',
        string $endDate = ''
    ): array {
        // Linhas com origem "pagamento" trazem exatamente as mesmas chaves de
        // uma linha de parcela, para que a tabela liste cada pagamento como se
        // fosse um lançamento (mesma granularidade do relatório de pagos).
        $sql = "SELECT
            p.id,
            p.conta_id,
            p.numero_parcela,
            p.valor_em_centavos,
            p.juros_em_centavos,
            p.valor_pago_em_centavos,
            p.paid,
            (p.valor_em_centavos + COALESCE(p.juros_em_centavos, 0)) AS valor_total_em_centavos,
            (p.valor_em_centavos + COALESCE(p.juros_em_centavos, 0) - p.valor_pago_em_centavos) AS valor_restante_em_centavos,
            p.data_vencimento,
            p.data_pagamento,
            n.nome natureza,
            p.banco_id,
            cc.nome centro,
            f.nome fornecedor,
            c.historico,
            (
                SELECT COUNT(*)
                FROM parcela p2
                WHERE p2.conta_id = p.conta_id
            ) AS total_parcelas,
            'parcela' AS origem,
            NULL AS pagamento_id,
            p.valor_pago_em_centavos AS pagamento_total_em_centavos
        FROM parcela p
        INNER JOIN conta AS c ON p.conta_id = c.id
        INNER JOIN natureza n ON c.natureza_id = n.id
        INNER JOIN centro_de_custo cc ON c.centro_de_custo_id = cc.id
        LEFT JOIN fornecedor f ON c.fornecedor_id = f.id
        WHERE 1 = 1";

        switch ($status) {
            case "a pagar":
                $sql .= ' AND p.paid = 0 AND ' . self::activeCondition('p', 'c');
                break;
            case "pagas":
                // Parcelas quitadas e parcelas com pagamento parcial. Quando
                // há lançamentos no livro caixa, eles são listados à parte
                // (getPagamentos), então a parcela em si não aparece.
                $sql .= ' AND (p.paid = 1 OR p.valor_pago_em_centavos > 0) AND ' . self::activeCondition('p', 'c');
                $sql .= ' AND NOT EXISTS (SELECT 1 FROM parcela_pagamento pp WHERE pp.parcela_id = p.id)';
                break;
            case "inativadas":
                $sql .= ' AND c.enabled = 0';
                break;
            default: // also "todas"
                $sql .= ' AND ' . self::activeCondition('p', 'c');
        }

        if ($naturezaId != 'all') {
            $sql = $sql . " AND n.id = :natureza_id";
        }

        if ($centroId != 'all') {
            $sql = $sql . " AND (cc.id = :centro_id OR cc.categoria_id = :centro_id)";
        }

        if ($fornecedorId != 'all') {
            $sql = $sql . " AND f.id = :fornecedor_id";
        }

        $dateColumn = self::dateColumnForStatus($status);

        if ($startDate != '' && $endDate != '') {
            $sql = $sql . " AND p.$dateColumn BETWEEN :startDate AND :endDate";
        }

        $sql = $sql . " ORDER BY p.$dateColumn, p.id";

        try {
            $stmt = Database::getConnection()->prepare($sql);

            if ($startDate != '' && $endDate != '') {
                $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
                $stmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);
            }

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

            $parcelas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($status !== 'pagas') {
                return $parcelas;
            }

            $pagamentos = self::getPagamentos($naturezaId, $centroId, $fornecedorId, $startDate, $endDate);
            $linhas = array_merge($parcelas, $pagamentos);
            usort($linhas, static fn(array $a, array $b): int =>
                strcmp((string) $a['data_pagamento'], (string) $b['data_pagamento'])
                ?: ((int) ($a['pagamento_id'] ?? 0) <=> (int) ($b['pagamento_id'] ?? 0)));

            return $linhas;
        } catch (PDOException $e) {
            Logger::error(
                'Falha ao listar contas',
                [
                    'status' => $status,
                    'natureza' => $naturezaId,
                    'centro' => $centroId,
                    'fornecedor' => $fornecedorId,
                    'PDOException' => $e->getMessage()
                ]
            );
            $_SESSION['message'] = ['Erro inesperado, entre em contato com o desenvolvedor do sistema.', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/dashboard');
            exit;
        }
    }

    /**
     * Um pagamento do livro caixa por linha, no mesmo formato de getAll().
     *
     * id, conta_id, numero_parcela, valor/juros/paid/total/restante e as
     * dimensões vêm da parcela; data_pagamento e banco_id são do pagamento;
     * pagamento_total_em_centavos é o valor do próprio lançamento.
     * valor_pago_em_centavos continua sendo o cache acumulado da parcela.
     */
    private static function getPagamentos(
        string $naturezaId,
        string $centroId,
        string $fornecedorId,
        string $startDate,
        string $endDate
    ): array {
        $sql = "SELECT
            p.id,
            p.conta_id,
            p.numero_parcela,
            p.valor_em_centavos,
            p.juros_em_centavos,
            p.valor_pago_em_centavos,
            p.paid,
            (pp.valor_em_centavos + COALESCE(pp.juros_em_centavos, 0)) AS valor_total_em_centavos,
            (p.valor_em_centavos + COALESCE(p.juros_em_centavos, 0) - p.valor_pago_em_centavos) AS valor_restante_em_centavos,
            p.data_vencimento,
            pp.data_pagamento,
            n.nome natureza,
            pp.banco_id,
            cc.nome centro,
            f.nome fornecedor,
            c.historico,
            (
                SELECT COUNT(*)
                FROM parcela p2
                WHERE p2.conta_id = p.conta_id
            ) AS total_parcelas,
            'pagamento' AS origem,
            pp.id AS pagamento_id,
            (pp.valor_em_centavos + COALESCE(pp.juros_em_centavos, 0)) AS pagamento_total_em_centavos
        FROM parcela_pagamento pp
        INNER JOIN parcela p ON pp.parcela_id = p.id
        INNER JOIN conta AS c ON p.conta_id = c.id
        INNER JOIN natureza n ON c.natureza_id = n.id
        INNER JOIN centro_de_custo cc ON c.centro_de_custo_id = cc.id
        LEFT JOIN fornecedor f ON c.fornecedor_id = f.id
        WHERE p.enabled = 1 AND c.enabled = 1";

        if ($naturezaId != 'all') {
            $sql .= ' AND n.id = :natureza_id';
        }

        if ($centroId != 'all') {
            $sql .= ' AND (cc.id = :centro_id OR cc.categoria_id = :centro_id)';
        }

        if ($fornecedorId != 'all') {
            $sql .= ' AND f.id = :fornecedor_id';
        }

        if ($startDate != '' && $endDate != '') {
            $sql .= ' AND pp.data_pagamento BETWEEN :startDate AND :endDate';
        }

        $sql .= ' ORDER BY pp.data_pagamento, pp.id';

        $stmt = Database::getConnection()->prepare($sql);

        if ($startDate != '' && $endDate != '') {
            $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
            $stmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);
        }

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
    }

    public static function getById(int $id): Parcela
    {
        $parcela = Database::getById(self::$tableName, $id);
        if (!$parcela) {
            $_SESSION['message'] = ['Parcela não encontrada', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas');
            exit;
        }

        extract($parcela);
        return new Parcela($id, $numero_parcela, $valor_em_centavos, $data_vencimento, $data_pagamento, $conta_id, $banco_id, $paid, $juros_em_centavos, $valor_pago_em_centavos ?? 0);
    }

    public static function getDailyTotal(string $start, string $end, string $status = 'todas', string $naturezaId = 'all'): array
    {
        try {
            if ($status === 'pagas') {
                return self::getDailyPaidTotals($start, $end, $naturezaId);
            }

            if ($status === 'a_pagar') {
                return self::getDailyRemainingTotals($start, $end, $naturezaId);
            }

            // "todas": o que foi pago aparece na data de cada pagamento e o que
            // ainda falta aparece na data de vencimento.
            $porData = [];

            foreach (self::getDailyPaidTotals($start, $end, $naturezaId) as $linha) {
                $porData[$linha['date']] = ($porData[$linha['date']] ?? 0) + (int) $linha['total'];
            }

            foreach (self::getDailyRemainingTotals($start, $end, $naturezaId) as $linha) {
                $porData[$linha['date']] = ($porData[$linha['date']] ?? 0) + (int) $linha['total'];
            }

            ksort($porData);

            $resultado = [];
            foreach ($porData as $date => $total) {
                $resultado[] = ['date' => $date, 'total' => $total];
            }

            return $resultado;
        } catch (PDOException $e) {
            Logger::error('Falha ao buscar totais diários', ['start' => $start, 'end' => $end, 'status' => $status, 'PDOException' => $e->getMessage()]);
            return [];
        }
    }

    private static function getDailyPaidTotals(string $start, string $end, string $naturezaId): array
    {
        $sql = "SELECT
                    pp.data_pagamento as date,
                    SUM(pp.valor_em_centavos + pp.juros_em_centavos) as total
                FROM parcela_pagamento pp
                INNER JOIN parcela p ON pp.parcela_id = p.id
                INNER JOIN conta ON p.conta_id = conta.id
                INNER JOIN natureza ON conta.natureza_id = natureza.id
                WHERE pp.data_pagamento BETWEEN :start AND :end
                AND conta.enabled = 1
                AND p.enabled = 1";

        if ($naturezaId != 'all') {
            $sql .= " AND conta.natureza_id = :natureza_id";
        }

        $sql .= " GROUP BY pp.data_pagamento
                ORDER BY pp.data_pagamento";

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->bindParam(':start', $start);
        $stmt->bindParam(':end', $end);

        if ($naturezaId != 'all') {
            $stmt->bindParam(':natureza_id', $naturezaId, PDO::PARAM_INT);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function getDailyRemainingTotals(string $start, string $end, string $naturezaId): array
    {
        $sql = "SELECT
                    p.data_vencimento as date,
                    SUM(p.valor_em_centavos + COALESCE(p.juros_em_centavos, 0) - p.valor_pago_em_centavos) as total
                FROM parcela p
                INNER JOIN conta ON p.conta_id = conta.id
                INNER JOIN natureza ON conta.natureza_id = natureza.id
                WHERE p.data_vencimento BETWEEN :start AND :end
                AND p.paid = 0
                AND conta.enabled = 1
                AND p.enabled = 1";

        if ($naturezaId != 'all') {
            $sql .= " AND conta.natureza_id = :natureza_id";
        }

        $sql .= " GROUP BY p.data_vencimento
                ORDER BY p.data_vencimento";

        $stmt = Database::getConnection()->prepare($sql);
        $stmt->bindParam(':start', $start);
        $stmt->bindParam(':end', $end);

        if ($naturezaId != 'all') {
            $stmt->bindParam(':natureza_id', $naturezaId, PDO::PARAM_INT);
        }

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function getOverdueTotals(): array
    {
        $sql = "SELECT 
                    COUNT(*) AS quantidade,
                    COALESCE(SUM(parcela.valor_em_centavos + COALESCE(parcela.juros_em_centavos, 0) - parcela.valor_pago_em_centavos), 0) AS total,
                    MIN(parcela.data_vencimento) AS mais_antiga
                FROM parcela
                INNER JOIN conta ON parcela.conta_id = conta.id
                WHERE parcela.paid = 0
                AND parcela.enabled = 1
                AND conta.enabled = 1
                AND parcela.data_vencimento < CURDATE()";

        try {
            return Database::getConnection()->query($sql)->fetch(PDO::FETCH_ASSOC)
                ?: ['quantidade' => 0, 'total' => 0, 'mais_antiga' => null];
        } catch (PDOException $e) {
            Logger::error('Falha ao buscar contas atrasadas', ['PDOException' => $e->getMessage()]);
            return ['quantidade' => 0, 'total' => 0, 'mais_antiga' => null];
        }
    }

    public static function getDueInRangeTotals(string $start, string $end): array
    {
        $sql = "SELECT 
                    COUNT(*) AS quantidade,
                    COALESCE(SUM(parcela.valor_em_centavos + COALESCE(parcela.juros_em_centavos, 0) - parcela.valor_pago_em_centavos), 0) AS total
                FROM parcela
                INNER JOIN conta ON parcela.conta_id = conta.id
                WHERE parcela.paid = 0
                AND parcela.enabled = 1
                AND conta.enabled = 1
                AND parcela.data_vencimento BETWEEN :start AND :end";

        try {
            $stmt = Database::getConnection()->prepare($sql);
            $stmt->bindParam(':start', $start, PDO::PARAM_STR);
            $stmt->bindParam(':end', $end, PDO::PARAM_STR);
            $stmt->execute();

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: ['quantidade' => 0, 'total' => 0];
        } catch (PDOException $e) {
            Logger::error('Falha ao buscar vencimentos por período', ['start' => $start, 'end' => $end, 'PDOException' => $e->getMessage()]);
            return ['quantidade' => 0, 'total' => 0];
        }
    }

    public static function bulkInsert(array $parcelas, int $conta_id): void
    {
        $sql = "INSERT INTO parcela(numero_parcela, valor_em_centavos, data_vencimento, conta_id) VALUES ";

        for ($i = 0; $i < count($parcelas); $i++) {
            $sql = $sql . "(:numero_parcela$i, :valor$i, :vencimento$i, :conta_id)";

            if ($i != count($parcelas) - 1) {
                $sql = $sql . ", ";
            }
        }

        try {
            $stmt = Database::getConnection()->prepare($sql);

            for ($i = 0; $i < count($parcelas); $i++) {
                $stmt->bindValue(":numero_parcela$i", $i + 1);
                $stmt->bindValue(":valor$i", $parcelas[$i]['valor'], PDO::PARAM_INT);
                $stmt->bindValue(":vencimento$i", $parcelas[$i]['vencimento'], PDO::PARAM_STR);
                $stmt->bindValue(':conta_id', $conta_id, PDO::PARAM_INT);
            }

            $stmt->execute();
        } catch (PDOException $e) {
            Logger::error('Falha ao inserir parcelas', ['PDOException' => $e->getMessage()]);
            $_SESSION['message'] = ['Erro ao cadastrar parcelas, entre em contato com o desenvolvedor do sistema.', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas');
            exit;
        }
    }

    public static function getByBank(int $banco_id, string|null $startDate): array
    {
        try {
            $sql = "
            SELECT 
                pp.parcela_id,
                pp.valor_em_centavos,
                pp.juros_em_centavos,
                pp.data_pagamento,
                p.numero_parcela,
                p.conta_id,
                centro.nome as centro,
                f.nome as fornecedor
            FROM parcela_pagamento pp
            INNER JOIN parcela p ON pp.parcela_id = p.id
            INNER JOIN conta c ON p.conta_id = c.id
            INNER JOIN centro_de_custo centro ON c.centro_de_custo_id = centro.id
            LEFT JOIN fornecedor f ON c.fornecedor_id = f.id
            WHERE pp.banco_id = :id
                AND " . self::activeCondition('p', 'c');

            if ($startDate !== null) {
                $sql .= " AND pp.data_pagamento >= :startDate";
            }

            $sql .= " ORDER BY pp.data_pagamento, pp.id";

            $stmt = Database::getConnection()->prepare($sql);
            $stmt->bindParam(':id', $banco_id, PDO::PARAM_INT);

            if ($startDate !== null) {
                $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
            }

            $stmt->execute();

            $parcelas = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $parcela) {
                extract($parcela);
                $p = new Parcela($parcela_id, $numero_parcela, $valor_em_centavos, '', $data_pagamento, $conta_id, $banco_id, 1, $juros_em_centavos);
                $p->centro_de_custo = $centro;
                $p->fornecedor = $fornecedor;
                $parcelas[] = $p;
            }

            return $parcelas;
        } catch (PDOException $e) {
            Logger::error('Falha ao buscar parcelas por banco', ['id' => $banco_id, 'PDOException' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Retorna os meses (formato YYYY-MM) que possuem pagamentos registrados
     * pelo banco, inclusive parciais, do mais recente para o mais antigo.
     */
    public static function getPaidMonthsByBank(int $banco_id): array
    {
        try {
            $stmt = Database::getConnection()->prepare("
            SELECT DISTINCT DATE_FORMAT(pp.data_pagamento, '%Y-%m') AS mes
            FROM parcela_pagamento pp
            INNER JOIN parcela p ON pp.parcela_id = p.id
            INNER JOIN conta c ON p.conta_id = c.id
            WHERE pp.banco_id = :id
                AND " . self::activeCondition('p', 'c') . "
            ORDER BY mes DESC");
            $stmt->bindParam(':id', $banco_id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            Logger::error('Falha ao buscar meses de parcelas pagas por banco', ['id' => $banco_id, 'PDOException' => $e->getMessage()]);
            return [];
        }
    }

    public static function getPaidByBankBetween(int $banco_id, string $startDate, string $endDate): array
    {
        try {
            $stmt = Database::getConnection()->prepare("
            SELECT 
                pp.parcela_id,
                pp.valor_em_centavos,
                pp.juros_em_centavos,
                pp.data_pagamento,
                p.numero_parcela,
                p.conta_id,
                c.historico,
                centro.nome as centro,
                centro.categoria_id,
                pai.nome as centro_pai,
                f.nome as fornecedor,
                nat.nome as natureza,
                (SELECT COUNT(*) FROM parcela pc WHERE pc.conta_id = p.conta_id) as total_parcelas
            FROM parcela_pagamento pp
            INNER JOIN parcela p ON pp.parcela_id = p.id
            INNER JOIN conta c ON p.conta_id = c.id
            INNER JOIN centro_de_custo centro ON c.centro_de_custo_id = centro.id
            LEFT JOIN centro_de_custo pai ON centro.categoria_id = pai.id
            INNER JOIN natureza nat ON c.natureza_id = nat.id
            LEFT JOIN fornecedor f ON c.fornecedor_id = f.id
            WHERE pp.banco_id = :id
                AND pp.data_pagamento >= :startDate
                AND pp.data_pagamento <= :endDate
                AND " . self::activeCondition('p', 'c') . "
            ORDER BY pp.data_pagamento, pp.id");
            $stmt->bindParam(':id', $banco_id, PDO::PARAM_INT);
            $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
            $stmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);
            $stmt->execute();

            $parcelas = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $parcela) {
                extract($parcela);
                $p = new Parcela($parcela_id, $numero_parcela, $valor_em_centavos, '', $data_pagamento, $conta_id, $banco_id, 1, $juros_em_centavos);
                $p->centro_de_custo = $centro;
                $p->centro_pai = $centro_pai ?? null;
                $p->fornecedor = $fornecedor;
                $p->historico = $historico;
                $p->natureza = $natureza;
                $p->total_parcelas = $total_parcelas;
                $parcelas[] = $p;
            }

            return $parcelas;
        } catch (PDOException $e) {
            Logger::error('Falha ao buscar parcelas pagas por banco', ['id' => $banco_id, 'PDOException' => $e->getMessage()]);
            return [];
        }
    }

    public function delete(): bool
    {
        try {
            $conn = Database::getConnection();
            $stmt = $conn->prepare('DELETE FROM ' . self::$tableName . ' WHERE id = :id AND paid = 0');
            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
            $stmt->execute();
            return true;
        } catch (PDOException $e) {
            Logger::error('Falha ao deletar parcela', ['id' => $this->id, 'PDOException' => $e->getMessage()]);
            return false;
        }
    }

    public static function deleteByContaId(int $conta_id): void
    {
        try {
            $conn = Database::getConnection();
            $stmt = $conn->prepare('DELETE FROM ' . self::$tableName . ' WHERE conta_id = :conta_id AND paid = 0 AND valor_pago_em_centavos = 0');
            $stmt->bindParam(':conta_id', $conta_id, PDO::PARAM_INT);
            $stmt->execute();
        } catch (PDOException $e) {
            Logger::error('Falha ao deletar parcelas por conta', ['conta_id' => $conta_id, 'PDOException' => $e->getMessage()]);
        }
    }

    public static function getByContaId(int $conta_id): array
    {
        try {
            $conn = Database::getConnection();
            $stmt = $conn->prepare('SELECT * FROM ' . self::$tableName . ' WHERE conta_id = :conta_id ORDER BY numero_parcela');
            $stmt->bindParam(':conta_id', $conta_id, PDO::PARAM_INT);
            $stmt->execute();
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $parcelas = [];
            foreach ($results as $row) {
                $parcelas[] = new Parcela(
                    $row['id'],
                    $row['numero_parcela'],
                    $row['valor_em_centavos'],
                    $row['data_vencimento'],
                    $row['data_pagamento'],
                    $row['conta_id'],
                    $row['banco_id'] ?? null,
                    (bool)$row['paid'],
                    $row['juros_em_centavos'] ?? 0,
                    $row['valor_pago_em_centavos'] ?? 0
                );
            }
            return $parcelas;
        } catch (PDOException $e) {
            Logger::error('Falha ao buscar parcelas por conta', ['conta_id' => $conta_id, 'PDOException' => $e->getMessage()]);
            return [];
        }
    }

    public static function renumber(array $orderedIds): void
    {
        try {
            $conn = Database::getConnection();
            foreach ($orderedIds as $idx => $id) {
                $num = $idx + 1;
                $stmt = $conn->prepare('UPDATE ' . self::$tableName . ' SET numero_parcela = :num WHERE id = :id');
                $stmt->bindParam(':num', $num, PDO::PARAM_INT);
                $stmt->bindParam(':id', $id, PDO::PARAM_INT);
                $stmt->execute();
            }
        } catch (PDOException $e) {
            Logger::error('Falha ao renumerar parcelas', ['PDOException' => $e->getMessage()]);
        }
    }
}
