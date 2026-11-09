<?php

namespace App\Models;

use App\Controllers\Services\Logger;
use App\Controllers\Services\Money;
use App\Models\Database;
use PDO;
use PDOException;

class Deposito
{
    public static string $tableName = 'deposito';
    public static int $LIMITE_DEPOSITO = 100000000;
    public string|null $usuario_nome = null;

    public function __construct(
        private int $id,
        private int $usuarioId,
        private int $bancoId,
        private int $valorEmCentavos,
        private string $historico,
        private string $dataDeposito
    ) {
        $this->historico = htmlspecialchars($this->historico, ENT_QUOTES, 'UTF-8', false);
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getUsuarioId(): int
    {
        return $this->usuarioId;
    }

    public function getBancoId(): int
    {
        return $this->bancoId;
    }

    public function getValorEmCentavos(): int
    {
        return $this->valorEmCentavos;
    }

    public function setValorEmCentavos(int $valorEmCentavos): void
    {
        $this->valorEmCentavos = $valorEmCentavos;
    }

    public function getHistorico(): string
    {
        return $this->historico;
    }

    public function setHistorico(string $historico): void
    {
        $this->historico = htmlspecialchars($historico, ENT_QUOTES, 'UTF-8', false);
    }

    public function setDataDeposito(string $dataDeposito): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $dataDeposito)) {
            $this->dataDeposito = $dataDeposito;
        } else {
            $this->dataDeposito = date('Y-m-d');
        }
    }

    public function getDataDeposito(): string
    {
        $mysqlDate = strtotime($this->dataDeposito);
        return date('Y-m-d', $mysqlDate);
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
                $hasData = !empty($this->dataDeposito) && preg_match('/^\d{4}-\d{2}-\d{2}/', $this->dataDeposito);
                if ($hasData) {
                    $stmt = $conn->prepare('INSERT INTO ' . self::$tableName . ' (usuario_id, banco_id, valor_em_centavos, historico, data_deposito) VALUES (:usuario_id, :banco_id, :valor_em_centavos, :historico, :data_deposito)');
                    $stmt->bindParam(':usuario_id', $this->usuarioId, PDO::PARAM_INT);
                    $stmt->bindParam(':banco_id', $this->bancoId, PDO::PARAM_INT);
                    $stmt->bindParam(':valor_em_centavos', $this->valorEmCentavos, PDO::PARAM_INT);
                    $stmt->bindParam(':historico', $this->historico, PDO::PARAM_STR);
                    $stmt->bindParam(':data_deposito', $this->dataDeposito, PDO::PARAM_STR);
                } else {
                    $stmt = $conn->prepare('INSERT INTO ' . self::$tableName . ' (usuario_id, banco_id, valor_em_centavos, historico) VALUES (:usuario_id, :banco_id, :valor_em_centavos, :historico)');
                    $stmt->bindParam(':usuario_id', $this->usuarioId, PDO::PARAM_INT);
                    $stmt->bindParam(':banco_id', $this->bancoId, PDO::PARAM_INT);
                    $stmt->bindParam(':valor_em_centavos', $this->valorEmCentavos, PDO::PARAM_INT);
                    $stmt->bindParam(':historico', $this->historico, PDO::PARAM_STR);
                }

                $stmt->execute();

                Logger::log(Deposito::$tableName, $this->historico, '-', "R$ " . Money::centavos_para_reais($this->valorEmCentavos), $conn->lastInsertId(), $_SESSION['usuario_id']);

                return true;
            } else {
                // atualizar
                $depositoAntigo = self::getById($this->id);

                $hasDataUpd = !empty($this->dataDeposito) && preg_match('/^\d{4}-\d{2}-\d{2}/', $this->dataDeposito);
                if ($hasDataUpd) {
                    $stmt = $conn->prepare('UPDATE ' . self::$tableName . ' SET usuario_id = :usuario_id, valor_em_centavos = :valor_em_centavos, historico = :historico, data_deposito = :data_deposito WHERE id = :id');
                    $stmt->bindParam(':usuario_id', $this->usuarioId, PDO::PARAM_INT);
                    $stmt->bindParam(':valor_em_centavos', $this->valorEmCentavos, PDO::PARAM_INT);
                    $stmt->bindParam(':historico', $this->historico, PDO::PARAM_STR);
                    $stmt->bindParam(':data_deposito', $this->dataDeposito, PDO::PARAM_STR);
                    $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
                } else {
                    $stmt = $conn->prepare('UPDATE ' . self::$tableName . ' SET usuario_id = :usuario_id, valor_em_centavos = :valor_em_centavos, historico = :historico WHERE id = :id');
                    $stmt->bindParam(':usuario_id', $this->usuarioId, PDO::PARAM_INT);
                    $stmt->bindParam(':valor_em_centavos', $this->valorEmCentavos, PDO::PARAM_INT);
                    $stmt->bindParam(':historico', $this->historico, PDO::PARAM_STR);
                    $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
                }

                $stmt->execute();

                if ($depositoAntigo->getValorEmCentavos() != $this->valorEmCentavos) {
                    Logger::log(Banco::$tableName, 'valor deposito', Money::centavos_para_reais($depositoAntigo->getValorEmCentavos()), Money::centavos_para_reais($this->valorEmCentavos), $this->bancoId, $_SESSION['usuario_id']);
                }

                if ($depositoAntigo->getHistorico() != $this->historico) {
                    Logger::log(Banco::$tableName, 'historico deposito', $depositoAntigo->getHistorico(), $this->historico, $this->bancoId, $_SESSION['usuario_id']);
                }
                if ($depositoAntigo->getDataDeposito() != $this->dataDeposito) {
                    Logger::log(Banco::$tableName, 'data deposito', implode('/', array_reverse(explode('-', $depositoAntigo->getDataDeposito()))), implode('/', array_reverse(explode('-', $this->dataDeposito))), $this->bancoId, $_SESSION['usuario_id']);
                }

                return true;
            }
            return false;
        } catch (PDOException $e) {
            Logger::error('Erro ao realizar depósito', ['PDOException' => $e->getMessage()]);
            $_SESSION['message'] = ['Erro ao realizar depósito', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/bancos');
            exit;
        }
    }

        public static function getById(int $id): Deposito
    {
        $deposito = Database::getById(self::$tableName, $id);
        if (!$deposito) {
            $_SESSION['message'] = ['Depósito não encontrado', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/bancos');
            exit;
        }

        extract($deposito);
        return new Deposito($id, $usuario_id, $banco_id, $valor_em_centavos, $historico, $data_deposito);
    }

    public static function getByBank(int $banco_id, string $startDate = null): array
    {
        try {
            $sql = "
            SELECT 
                id,
                usuario_id,
                banco_id,
                valor_em_centavos,
                historico,
                data_deposito
            FROM deposito
            WHERE banco_id = :id";

            if ($startDate !== null) {
                $sql .= " AND data_deposito >= :startDate";
            }

            $sql .= "
            ORDER BY id DESC";

            $stmt = Database::getConnection()->prepare($sql);
            $stmt->bindParam(':id', $banco_id, PDO::PARAM_INT);

            if ($startDate !== null) {
                $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
            }

            $stmt->execute();

            $depositos = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $deposito) {
                extract($deposito);
                $depositos[] = new Deposito($id, $usuario_id, $banco_id, $valor_em_centavos, $historico, $data_deposito);
            }

            return $depositos;
        } catch (PDOException $e) {
            Logger::error('Falha ao buscar depositos por banco', ['id' => $banco_id, 'PDOException' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Retorna os meses (formato YYYY-MM) que possuem depósitos para o banco,
     * do mais recente para o mais antigo.
     */
    public static function getMonthsByBank(int $banco_id): array
    {
        try {
            $stmt = Database::getConnection()->prepare("
            SELECT DISTINCT DATE_FORMAT(data_deposito, '%Y-%m') AS mes
            FROM deposito
            WHERE banco_id = :id
            ORDER BY mes DESC");
            $stmt->bindParam(':id', $banco_id, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            Logger::error('Falha ao buscar meses de depositos por banco', ['id' => $banco_id, 'PDOException' => $e->getMessage()]);
            return [];
        }
    }

    public static function getByBankBetween(int $banco_id, string $startDate, string $endDate): array
    {
        try {
            $endExclusive = date('Y-m-d', strtotime($endDate . ' +1 day'));

            $stmt = Database::getConnection()->prepare("
            SELECT 
                d.id,
                d.usuario_id,
                d.banco_id,
                d.valor_em_centavos,
                d.historico,
                d.data_deposito,
                u.nome as usuario_nome
            FROM deposito d
            LEFT JOIN usuario u ON d.usuario_id = u.id
            WHERE d.banco_id = :id
                AND d.data_deposito >= :startDate
                AND d.data_deposito < :endDate
            ORDER BY d.data_deposito, d.id");
            $stmt->bindParam(':id', $banco_id, PDO::PARAM_INT);
            $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
            $stmt->bindParam(':endDate', $endExclusive, PDO::PARAM_STR);
            $stmt->execute();

            $depositos = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $deposito) {
                extract($deposito);
                $d = new Deposito($id, $usuario_id, $banco_id, $valor_em_centavos, $historico, $data_deposito);
                $d->usuario_nome = $usuario_nome;
                $depositos[] = $d;
            }

            return $depositos;
        } catch (PDOException $e) {
            Logger::error('Falha ao buscar depositos por banco', ['id' => $banco_id, 'PDOException' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Exclui o depósito e os logs vinculados a ele.
     *
     * Os logs do depósito são removidos antes porque log_deposito possui chave
     * estrangeira para deposito(id), o que impede a exclusão do depósito.
     *
     * @return bool
     */
    public function delete(): bool
    {
        try {
            $conn = Database::getConnection();

            $stmt = $conn->prepare('DELETE FROM log_deposito WHERE deposito_id = :id');
            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $conn->prepare('DELETE FROM deposito WHERE id = :id');
            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
            $stmt->execute();

            return true;
        } catch (PDOException $e) {
            Logger::error('Falha ao deletar deposito', ['id' => $this->id, 'PDOException' => $e->getMessage()]);
            return false;
        }
    }
}