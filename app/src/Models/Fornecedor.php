<?php

namespace App\Models;

use App\Controllers\Services\Logger;
use App\Models\Database;
use PDO;
use PDOException;

class Fornecedor
{
    public static $tableName = 'fornecedor';

    public function __construct(
        private int $id,
        private string $nome,
        private string|null $telefone,
        private string $data_criacao,
        private string|null $data_edicao,
        private bool $enabled
    ) {
        $this->nome = htmlspecialchars($this->nome, ENT_QUOTES, 'UTF-8', false);
    }

    /**
     * Get the value of id
     */
    public function getId(): int
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
     * Get the value of nome
     */
    public function getNome(): string
    {
        return $this->nome;
    }

    /**
     * Set the value of nome
     *
     * @return self
     */
    public function setNome($nome)
    {
        $this->nome = $nome;

        return $this;
    }

    /**
     * Get the value of telefone
     */
    public function getTelefone(): string|null
    {
        return $this->telefone;
    }

    /**
     * Set the value of telefone
     *
     * @return self
     */
    public function setTelefone($telefone)
    {
        $this->telefone = $telefone;

        return $this;
    }

    /**
     * Get the value of data_criacao
     */
    public function getData_criacao(): string
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

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): bool
    {
        $query = 'SELECT COUNT(*) 
        FROM fornecedor
        INNER JOIN conta
        ON fornecedor_id = fornecedor.id
        WHERE conta.paid = 0
        AND conta.enabled = 1
        AND fornecedor.id = :fornecedor_id';

        $stmt = Database::getConnection()->prepare($query);
        $stmt->bindParam(":fornecedor_id", $this->id, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->fetch()[0] != 0) {
            return false;
        }

        $this->enabled = $enabled;
        return true;
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
                $stmt = $conn->prepare('INSERT INTO ' . self::$tableName . ' (nome, telefone) VALUES (:nome, :telefone)');
                $stmt->bindParam(':nome', $this->nome, PDO::PARAM_STR);
                $stmt->bindParam(':telefone', $this->telefone, PDO::PARAM_STR);

                $stmt->execute();

                Logger::log_create($conn, self::$tableName);

                return true;
            } else {
                // atualizar
                $fornecedorAntigo = self::getById($this->id);

                $stmt = $conn->prepare('UPDATE ' . self::$tableName . ' SET nome = :nome, telefone = :telefone, enabled = :enabled WHERE id = :id');
                $stmt->bindParam(':nome', $this->nome, PDO::PARAM_STR);
                $stmt->bindParam(':telefone', $this->telefone, PDO::PARAM_STR);
                $stmt->bindParam(':enabled', $this->enabled, PDO::PARAM_BOOL);
                $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);

                $stmt->execute();

                if ($fornecedorAntigo->getNome() != $this->nome) {
                    Logger::log(self::$tableName, 'nome', $fornecedorAntigo->getNome(), $this->nome, $this->id, $_SESSION['usuario_id']);
                }

                if ($fornecedorAntigo->getTelefone() != $this->telefone) {
                    Logger::log(self::$tableName, 'telefone', $fornecedorAntigo->getTelefone(), $this->telefone, $this->id, $_SESSION['usuario_id']);
                }

                return true;
            }
        } catch (PDOException $e) {
            Logger::error('Erro ao cadastrar|atualizar fornecedor', ['PDOException' => $e->getMessage()]);
            $_SESSION['message'] = ['Erro ao cadastrar|atualizar fornecedor', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/fornecedores');
            exit;
        }
    }

    public static function getAll(bool $enabled, string $search): array
    {
        $sql = "SELECT id, nome, telefone
        FROM fornecedor
        WHERE fornecedor.enabled = :enabled";

        if (strlen($search) > 0) {
            $sql .= ' AND nome LIKE :search OR telefone LIKE :search';
        }

        $sql .= ' ORDER BY nome';

        try {
            $stmt = Database::getConnection()->prepare($sql);
            $stmt->bindValue(':enabled', $enabled, PDO::PARAM_BOOL);

            if (strlen($search) > 0) {
                $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
            }

            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Logger::error('Falha ao listar fornecedores', ['enabled' => $enabled, 'search' => $search, 'PDOException' => $e->getMessage()]);
            $_SESSION['message'] = ['Erro inesperado, entre em contato com o desenvolvedor do sistema.', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/dashboard');
            exit;
        }
    }

    public static function getById(int $id): Fornecedor
    {
        $fornecedor = Database::getById(self::$tableName, $id);
        if (!$fornecedor) {
            $_SESSION['message'] = ['Fornecedor não encontrado', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/fornecedores');
            exit;
        }

        extract($fornecedor);
        return new Fornecedor($id, $nome, $telefone, $data_criacao, $data_edicao, $enabled);
    }

    public static function getTotalsByPeriod(string $start, string $end, string $status = 'todas', string $naturezaId = 'all'): array
    {
        $restante = "SELECT
                        f.id,
                        f.nome,
                        (p.valor_em_centavos + COALESCE(p.juros_em_centavos, 0) - p.valor_pago_em_centavos) AS total
                    FROM fornecedor f
                    INNER JOIN conta c ON f.id = c.fornecedor_id
                    INNER JOIN parcela p ON c.id = p.conta_id
                    WHERE p.paid = 0
                    AND p.data_vencimento BETWEEN :start AND :end
                    AND f.enabled = 1
                    AND " . Parcela::activeCondition('p', 'c');

        $pago = "SELECT
                        f.id,
                        f.nome,
                        (pp.valor_em_centavos + COALESCE(pp.juros_em_centavos, 0)) AS total
                    FROM fornecedor f
                    INNER JOIN conta c ON f.id = c.fornecedor_id
                    INNER JOIN parcela p ON c.id = p.conta_id
                    INNER JOIN parcela_pagamento pp ON pp.parcela_id = p.id
                    WHERE pp.data_pagamento BETWEEN :start AND :end
                    AND f.enabled = 1
                    AND " . Parcela::activeCondition('p', 'c');

        if ($naturezaId != 'all') {
            $restante .= " AND c.natureza_id = :natureza_id";
            $pago .= " AND c.natureza_id = :natureza_id";
        }

        $partes = match ($status) {
            'a_pagar' => [$restante],
            'pagas' => [$pago],
            default => [$restante, $pago],
        };

        $sql = "SELECT nome, SUM(total) as total
                FROM (" . implode(' UNION ALL ', $partes) . ") AS fluxo
                GROUP BY id, nome
                ORDER BY total DESC";

        try {
            $stmt = Database::getConnection()->prepare($sql);
            if ($naturezaId != 'all') {
                $stmt->bindParam(':natureza_id', $naturezaId, PDO::PARAM_INT);
            }
            $stmt->bindParam(':start', $start);
            $stmt->bindParam(':end', $end);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Logger::error('Falha ao buscar totais por fornecedores', ['start' => $start, 'end' => $end, 'status' => $status, 'PDOException' => $e->getMessage()]);
            return [];
        }
    }
}
