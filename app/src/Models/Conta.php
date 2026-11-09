<?php

namespace App\Models;

use App\Models\Database;
use PDO;
use PDOException;
use App\Controllers\Services\Logger;

class Conta
{
    public static string $tableName = 'conta';
    public int $lastInsertId;
    public string $centro_de_custo;
    public string|null $fornecedor;
    public string $natureza;

    public function __construct(
        private int $id,
        private string $historico,
        private int $valor_em_centavos,
        private string $data_criacao,
        private string|null $data_edicao,
        private int $centro_de_custo_id,
        private int $naturezaId,
        private int|null $fornecedor_id,
        private array $parcelas,
        private bool $enabled,
        private bool $paid
    ) {
        $this->historico = htmlspecialchars($this->historico, ENT_QUOTES, 'UTF-8', false);
    }

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
     * Get the value of historico
     */
    public function getHistorico()
    {
        return $this->historico;
    }

    /**
     * Set the value of historico
     *
     * @return self
     */
    public function setHistorico($historico)
    {
        $this->historico = $historico;

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
     * Get the value of data_criacao
     */
    public function getData_criacao()
    {
        return $this->data_criacao;
    }

    /**
     * Set the value of data_criacao
     *
     * @return self
     */
    public function setData_criacao($data_criacao)
    {
        $this->data_criacao = $data_criacao;

        return $this;
    }

    /**
     * Get the value of data_edicao
     */
    public function getData_edicao()
    {
        return $this->data_edicao;
    }

    /**
     * Set the value of data_edicao
     *
     * @return self
     */
    public function setData_edicao($data_edicao)
    {
        $this->data_edicao = $data_edicao;

        return $this;
    }

    /**
     * Get the value of centro_de_custo_id
     */
    public function getCentro_de_custo_id()
    {
        return $this->centro_de_custo_id;
    }

    /**
     * Set the value of centro_de_custo_id
     *
     * @return self
     */
    public function setCentro_de_custo_id($centro_de_custo_id)
    {
        $this->centro_de_custo_id = $centro_de_custo_id;

        return $this;
    }

    public function getNaturezaId(): int
    {
        return $this->naturezaId;
    }

    public function setNaturezaId(int $naturezaId): self
    {
        $this->naturezaId = $naturezaId;
        return $this;
    }

    /**
     * Get the value of fornecedor_id
     */
    public function getFornecedor_id()
    {
        return $this->fornecedor_id;
    }

    /**
     * Set the value of fornecedor_id
     *
     * @return self
     */
    public function setFornecedor_id($fornecedor_id)
    {
        $this->fornecedor_id = $fornecedor_id;

        return $this;
    }

    public function getParcelas(): array
    {
        return $this->parcelas;
    }

    public function setParcelas($parcelas): void
    {
        $this->parcelas = $parcelas;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function setPaid(bool $paid): void
    {
        $this->paid = $paid;
    }

    public function isPaid(): bool
    {
        return $this->paid;
    }

    public function save(): bool
    {
        try {
            $conn = Database::getConnection();

            $stmt = $conn->prepare('SELECT COUNT(*) FROM ' . self::$tableName . ' WHERE id = :id');
            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->fetch()[0] == 0) {
                // criar
                $stmt = $conn->prepare('INSERT INTO ' . self::$tableName . ' (historico, valor_em_centavos, natureza_id, centro_de_custo_id, fornecedor_id) VALUES (:historico, :valor_em_centavos, :natureza_id, :centro_de_custo_id, :fornecedor_id)');
                $stmt->bindParam(':historico', $this->historico, PDO::PARAM_STR);
                $stmt->bindParam(':valor_em_centavos', $this->valor_em_centavos, PDO::PARAM_STR);
                $stmt->bindParam(':natureza_id', $this->naturezaId, PDO::PARAM_INT);
                $stmt->bindParam(':centro_de_custo_id', $this->centro_de_custo_id, PDO::PARAM_INT);
                $stmt->bindParam(':fornecedor_id', $this->fornecedor_id, PDO::PARAM_INT);

                $stmt->execute();
                $this->lastInsertId = $conn->lastInsertId();

                Logger::log_create($conn, self::$tableName);

                return true;
            } else {
                // atualizar
                $stmt = $conn->prepare('UPDATE ' . self::$tableName . ' SET historico = :historico, valor_em_centavos = :valor_em_centavos, centro_de_custo_id = :centro_de_custo_id, natureza_id = :natureza_id, fornecedor_id = :fornecedor_id, enabled = :enabled, paid = :paid WHERE id = :id');
                $stmt->bindParam(':historico', $this->historico, PDO::PARAM_STR);
                $stmt->bindParam(':valor_em_centavos', $this->valor_em_centavos, PDO::PARAM_INT);
                $stmt->bindParam(':centro_de_custo_id', $this->centro_de_custo_id, PDO::PARAM_INT);
                $stmt->bindParam(':natureza_id', $this->naturezaId, PDO::PARAM_INT);
                $stmt->bindParam(':fornecedor_id', $this->fornecedor_id, PDO::PARAM_INT);
                $stmt->bindParam(':enabled', $this->enabled, PDO::PARAM_BOOL);
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

    public function syncValorFromParcelas(): void
    {
        $total = 0;

        foreach (self::findInstallments($this->getId()) as $p) {
            $total += $p->getValor_em_centavos() + $p->getJuros_em_centavos();
        }

        $oldValor = $this->getValor_em_centavos();

        if ($total !== $oldValor) {
            Logger::log(self::$tableName, 'valor', (string)$oldValor, (string)$total, $this->getId(), $_SESSION['usuario_id']);
        }

        $this->setValor_em_centavos($total);
        $this->setPaid(!self::hasUnpaidInstallments($this->getId()));
        $this->save();
    }

    public static function getById(int $id): Conta
    {
        $conta = Database::getById(self::$tableName, $id);
        if (!$conta) {
            $_SESSION['message'] = ['Conta não encontrada', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas');
            exit;
        }

        extract($conta);
        return new Conta($id, $historico, $valor_em_centavos, $data_criacao, $data_edicao, $centro_de_custo_id, $natureza_id, $fornecedor_id, self::findInstallments($id), $enabled, $paid);
    }

    public static function getAll(
        string $search,
        string $status,
        string $naturezaId = 'all',
        string $centroId = 'all',
        string $fornecedorId = 'all'
    ): array {
        $sql = "SELECT 
            conta.id,
            historico,
            valor_em_centavos,
            n.nome natureza,
            c.nome centro,
            f.nome fornecedor
        FROM conta
        INNER JOIN natureza n ON natureza_id = n.id
        INNER JOIN centro_de_custo c ON centro_de_custo_id = c.id
        LEFT JOIN fornecedor f ON fornecedor_id = f.id
        WHERE 1 = 1";

        switch ($status) {
            case "a pagar":
                $sql = $sql . " AND paid = 0 AND conta.enabled = 1";
                break;
            case "pagas":
                $sql = $sql . " AND paid = 1 AND conta.enabled = 1";
                break;
            case "inativadas":
                $sql = $sql . " AND conta.enabled = 0";
                break;
            default: // also "todas"
                $sql = $sql . " AND conta.enabled = 1";
        }

        if ($naturezaId != 'all') {
            $sql = $sql . " AND n.id = :natureza_id";
        }

        if ($centroId != 'all') {
            $sql = $sql . " AND c.id = :centro_id";
        }

        if ($fornecedorId != 'all') {
            $sql = $sql . " AND f.id = :fornecedor_id";
        }

        if (strlen($search) > 0) {
            $sql = $sql . " AND historico LIKE :historico";
        }

        try {
            $stmt = Database::getConnection()->prepare($sql);
            if (strlen($search) > 0) {
                $stmt->bindValue(':historico',  '%'.$search.'%', PDO::PARAM_STR);
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

            $contas = [];

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $conta) {
                $c = Conta::getById($conta['id']);
                $c->centro_de_custo = $conta['centro'];
                $c->fornecedor = $conta['fornecedor'];
                $c->natureza = $conta['natureza'];
                $contas[] = $c;
            }

            return $contas;
        } catch (PDOException $e) {
            Logger::error(
                'Falha ao listar contas', 
                [
                    'status' => $status,
                    'search' => $search,
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

    public static function getByForeignKey(string $foreignKey, int $id): array
    {
        $allowedKeys = ['centro_de_custo', 'fornecedor', 'natureza'];
        if (!in_array($foreignKey, $allowedKeys)) {
            return [];
        }

        $stmt = Database::getConnection()->prepare("
            SELECT conta.id, historico, valor_em_centavos, c.nome centro, n.nome natureza, f.nome fornecedor
            FROM conta
            INNER JOIN natureza n ON natureza_id = n.id
            INNER JOIN centro_de_custo c ON centro_de_custo_id = c.id
            LEFT JOIN fornecedor f ON fornecedor_id = f.id
            WHERE $foreignKey" . "_id = :id
            AND paid = 0
            AND conta.enabled = 1;
        ");
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $results = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            extract($row);
            $conta = new Conta($id, $historico, $valor_em_centavos, '', '', 0, 0, 0, self::findInstallments($id), 1, 0);
            $conta->centro_de_custo = $centro;
            $conta->fornecedor = $fornecedor;
            $conta->natureza = $natureza;
            $results[] = $conta;
        }

        return $results;
    }

    public static function findInstallments(int $id): array
    {
        $sql = "SELECT * FROM parcela WHERE conta_id = :conta_id ORDER BY numero_parcela";

        try {
            $stmt = Database::getConnection()->prepare($sql);
            $stmt->bindParam(':conta_id', $id, PDO::PARAM_INT);
            $stmt->execute();

            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $parcelas = [];

            foreach ($results as $row) {
                extract($row);
                $parcelas[] = new Parcela($id, $numero_parcela, $valor_em_centavos, $data_vencimento, $data_pagamento, $conta_id, $banco_id, $paid, $juros_em_centavos, $valor_pago_em_centavos ?? 0);
            }

            return $parcelas;
        } catch (PDOException $e) {
            Logger::error('Falha ao encontrar parcelas', ['conta_id' => $id, 'PDOException' => $e->getMessage()]);
            $_SESSION['message'] = ['Erro inesperado, entre em contato com o desenvolvedor do sistema.', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/dashboard');
            exit;
        }
    }

    public static function hasUnpaidInstallments(int $id): bool
    {
        $sql = "SELECT COUNT(*) FROM parcela WHERE conta_id = :conta_id AND paid = 0";

        try {
            $stmt = Database::getConnection()->prepare($sql);
            $stmt->bindParam(':conta_id', $id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch()[0] > 0;
        } catch (PDOException $e) {
            Logger::error('Falha ao verificar parcelas', ['conta_id' => $id, 'PDOException' => $e->getMessage()]);
            $_SESSION['message'] = ['Erro inesperado, entre em contato com o desenvolvedor do sistema.', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas');
            exit;
        }
    }

    public static function hasPaidInstallments(int $id): bool
    {
        $sql = "SELECT COUNT(*) FROM parcela WHERE conta_id = :conta_id AND paid = 1";

        try {
            $stmt = Database::getConnection()->prepare($sql);
            $stmt->bindParam(':conta_id', $id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch()[0] > 0;
        } catch (PDOException $e) {
            Logger::error('Falha ao verificar parcelas pagas', ['conta_id' => $id, 'PDOException' => $e->getMessage()]);
            $_SESSION['message'] = ['Erro inesperado, entre em contato com o desenvolvedor do sistema.', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas');
            exit;
        }
    }

    /**
     * A conta possui qualquer valor já pago (integral ou parcial)?
     * Editar a conta recria as parcelas, então qualquer pagamento precisa ser
     * estornado antes.
     */
    public static function hasPayments(int $id): bool
    {
        $sql = "SELECT COUNT(*) FROM parcela WHERE conta_id = :conta_id AND (paid = 1 OR valor_pago_em_centavos > 0)";

        try {
            $stmt = Database::getConnection()->prepare($sql);
            $stmt->bindParam(':conta_id', $id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetch()[0] > 0;
        } catch (PDOException $e) {
            Logger::error('Falha ao verificar pagamentos da conta', ['conta_id' => $id, 'PDOException' => $e->getMessage()]);
            $_SESSION['message'] = ['Erro inesperado, entre em contato com o desenvolvedor do sistema.', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas');
            exit;
        }
    }

    public function getNextInstallmentInfo(): array
    {
        $result = [
            'installment' => null,
            'paidInstallmentCount' => 0
        ];

        foreach ($this->getParcelas() as $parcela) {
            if (!$parcela->isPaid() && $result['installment'] == null) {
                $result['installment'] = $parcela;
            }

            if ($parcela->isPaid()) {
                $result['paidInstallmentCount'] += 1;
            }
        }

        return $result;
    }
}
