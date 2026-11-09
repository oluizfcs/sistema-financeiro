<?php

namespace App\Models;

use App\Controllers\Services\Logger;
use App\Controllers\Services\Money;
use App\Models\Database;
use DateTimeImmutable;
use PDO;
use PDOException;

class Banco
{
    public static string $tableName = 'banco';
    public int $lastInsertId;

    public function __construct(
        private int $id,
        private string $nome,
        private int $saldo_em_centavos,
        private string $data_criacao,
        private string|null $data_edicao,
        private bool $enabled
    ) {
        $this->nome = htmlspecialchars($this->nome, ENT_QUOTES, 'UTF-8', false);
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
     * Get the value of nome
     */
    public function getNome()
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
     * Get the value of saldo_em_centavos
     */
    public function getSaldo_em_centavos()
    {
        return $this->saldo_em_centavos;
    }

    /**
     * Set the value of saldo_em_centavos
     *
     * @return self
     */
    public function setSaldo_em_centavos($saldo_em_centavos)
    {
        $this->saldo_em_centavos = $saldo_em_centavos;

        return $this;
    }

    public function getSaldo_em_reais(): string
    {
        return Money::centavos_para_reais($this->saldo_em_centavos);
    }

    /**
     * Saldo do banco ao final do dia informado.
     *
     * A coluna banco.saldo_em_centavos guarda o saldo atual (hoje). Para obter o
     * saldo de uma data passada desconta-se todo movimento com data posterior a
     * ela: saldo_atual - entradas + saidas.
     *
     * Os filtros dos movimentos são os mesmos usados na listagem do extrato
     * (cada pagamento registrado no livro caixa e a parcela habilitada), para
     * que a soma das linhas do relatório sempre feche com o saldo apurado.
     *
     * @param int $bancoId
     * @param string $data Data no formato Y-m-d (inclusive)
     * @return int Saldo em centavos ao final da data informada
     */
    public static function getSaldoEmData(int $bancoId, string $data): int
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $data);

        if ($date === false) {
            Logger::error('Data inválida ao calcular o saldo do banco', ['banco_id' => $bancoId, 'data' => $data]);
            return 0;
        }

        $proxDia = $date->modify('+1 day')->format('Y-m-d');

        try {
            $conn = Database::getConnection();

            $stmt = $conn->prepare("
            SELECT 
                (SELECT b.saldo_em_centavos FROM banco b WHERE b.id = :banco) AS saldo_atual,
                COALESCE((SELECT SUM(d.valor_em_centavos)
                            FROM deposito d
                            WHERE d.banco_id = :banco_deposito
                                AND d.data_deposito >= :proxDia_deposito), 0) AS entradas,
                COALESCE((SELECT SUM(pp.valor_em_centavos + pp.juros_em_centavos)
                            FROM parcela_pagamento pp
                            INNER JOIN parcela p ON pp.parcela_id = p.id
                            INNER JOIN conta c ON p.conta_id = c.id
                            WHERE pp.banco_id = :banco_parcela
                                AND " . Parcela::activeCondition('p', 'c') . "
                                AND pp.data_pagamento >= :proxDia_parcela), 0) AS saidas");
            $stmt->bindParam(':banco', $bancoId, PDO::PARAM_INT);
            $stmt->bindParam(':banco_deposito', $bancoId, PDO::PARAM_INT);
            $stmt->bindParam(':banco_parcela', $bancoId, PDO::PARAM_INT);
            $stmt->bindParam(':proxDia_deposito', $proxDia, PDO::PARAM_STR);
            $stmt->bindParam(':proxDia_parcela', $proxDia, PDO::PARAM_STR);
            $stmt->execute();

            $resultado = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($resultado === false) {
                return 0;
            }

            return (int)$resultado['saldo_atual'] - (int)$resultado['entradas'] + (int)$resultado['saidas'];
        } catch (PDOException $e) {
            Logger::error('Falha ao calcular o saldo do banco por data', ['banco_id' => $bancoId, 'data' => $data, 'PDOException' => $e->getMessage()]);
            return 0;
        }
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

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
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
                $stmt = $conn->prepare('INSERT INTO ' . self::$tableName . ' (nome, saldo_em_centavos) VALUES (:nome, :saldo_em_centavos)');
                $stmt->bindParam(':nome', $this->nome, PDO::PARAM_STR);
                $stmt->bindParam(':saldo_em_centavos', $this->saldo_em_centavos, PDO::PARAM_STR);

                $stmt->execute();
                $this->lastInsertId = $conn->lastInsertId();

                Logger::log_create($conn, self::$tableName);

                return true;
            } else {
                // atualizar
                $bancoAntigo = self::getById($this->id);

                $stmt = $conn->prepare('UPDATE ' . self::$tableName . ' SET nome = :nome, saldo_em_centavos = :saldo_em_centavos, enabled = :enabled WHERE id = :id');
                $stmt->bindParam(':nome', $this->nome, PDO::PARAM_STR);
                $stmt->bindParam(':saldo_em_centavos', $this->saldo_em_centavos, PDO::PARAM_STR);
                $stmt->bindParam(':enabled', $this->enabled, PDO::PARAM_BOOL);
                $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);

                $stmt->execute();

                if ($bancoAntigo->getNome() != $this->nome) {
                    Logger::log(self::$tableName, 'nome', $bancoAntigo->getNome(), $this->nome, $this->id, $_SESSION['usuario_id']);
                }
                if ($bancoAntigo->getSaldo_em_centavos() != $this->saldo_em_centavos) {
                    Logger::log(self::$tableName, 'saldo', Money::centavos_para_reais($bancoAntigo->getSaldo_em_centavos()), Money::centavos_para_reais($this->saldo_em_centavos), $this->id, $_SESSION['usuario_id']);
                }

                return true;
            }
        } catch (PDOException $e) {
            Logger::error('Erro ao cadastrar|atualizar banco', ['PDOException' => $e->getMessage()]);
            $_SESSION['message'] = ['Erro ao cadastrar|atualizar banco', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/bancos');
            exit;
        }
    }

    public static function getAll(bool $enabled, string $search): array
    {
        $sql = "SELECT 
            id,
            nome,
            saldo_em_centavos
        FROM banco
        WHERE enabled = :enabled";

        if (strlen($search) > 0) {
            $sql .= ' AND nome LIKE :search';
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
            Logger::error('Falha ao listar bancos', ['enabled' => $enabled, 'search' => $search, 'PDOException' => $e->getMessage()]);
            $_SESSION['message'] = ['Erro inesperado, entre em contato com o desenvolvedor do sistema.', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/dashboard');
            exit;
        }
    }

    public static function getById(int $id): Banco
    {
        $banco = Database::getById(self::$tableName, $id);
        if (!$banco) {
            $_SESSION['message'] = ['Conta bancária não encontrada', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/bancos');
            exit;
        }

        extract($banco);
        return new Banco($id, $nome, $saldo_em_centavos, $data_criacao, $data_edicao, $enabled);
    }
}
