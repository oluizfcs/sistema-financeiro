<?php

namespace App\Models;

use App\Controllers\Services\Logger;
use PDO;
use PDOException;

/**
 * Livro caixa de pagamentos de parcelas.
 *
 * valor_em_centavos é o principal amortizado e juros_em_centavos é o juros
 * cobrado no evento; o caixa debitado do banco é a soma dos dois.
 */
class ParcelaPagamento
{
    public static string $tableName = 'parcela_pagamento';

    public function __construct(
        private int $id,
        private int $parcela_id,
        private int $conta_id,
        private int $numero_parcela,
        private int $banco_id,
        private string $banco_nome,
        private int $valor_em_centavos,
        private int $juros_em_centavos,
        private string $data_pagamento,
        private int|null $usuario_id,
        private string|null $usuario_nome,
        private string $criado_em
    ) {}

    public function getId(): int
    {
        return $this->id;
    }

    public function getParcela_id(): int
    {
        return $this->parcela_id;
    }

    public function getConta_id(): int
    {
        return $this->conta_id;
    }

    public function getNumero_parcela(): int
    {
        return $this->numero_parcela;
    }

    public function getBanco_id(): int
    {
        return $this->banco_id;
    }

    public function getBanco_nome(): string
    {
        return $this->banco_nome;
    }

    public function getValor_em_centavos(): int
    {
        return $this->valor_em_centavos;
    }

    public function getJuros_em_centavos(): int
    {
        return $this->juros_em_centavos;
    }

    public function getValor_total_em_centavos(): int
    {
        return $this->valor_em_centavos + $this->juros_em_centavos;
    }

    public function getData_pagamento(): string
    {
        return $this->data_pagamento;
    }

    public function getUsuario_id(): int|null
    {
        return $this->usuario_id;
    }

    public function getUsuario_nome(): string|null
    {
        return $this->usuario_nome;
    }

    public function getCriado_em(): string
    {
        return $this->criado_em;
    }

    public static function getByConta(int $contaId): array
    {
        return self::buscar('p.conta_id = :conta_id', [':conta_id' => [$contaId, PDO::PARAM_INT]]);
    }

    public static function getByParcela(int $parcelaId): array
    {
        return self::buscar('pp.parcela_id = :parcela_id', [':parcela_id' => [$parcelaId, PDO::PARAM_INT]]);
    }

    private static function buscar(string $where, array $params): array
    {
        try {
            $sql = "SELECT
                        pp.id,
                        pp.parcela_id,
                        pp.banco_id,
                        pp.valor_em_centavos,
                        pp.juros_em_centavos,
                        pp.data_pagamento,
                        pp.usuario_id,
                        pp.criado_em,
                        p.numero_parcela,
                        p.conta_id,
                        b.nome AS banco_nome,
                        u.nome AS usuario_nome
                    FROM parcela_pagamento pp
                    INNER JOIN parcela p ON pp.parcela_id = p.id
                    INNER JOIN banco b ON pp.banco_id = b.id
                    LEFT JOIN usuario u ON pp.usuario_id = u.id
                    WHERE $where
                    ORDER BY pp.data_pagamento, pp.id";

            $stmt = Database::getConnection()->prepare($sql);

            foreach ($params as $nome => [$valor, $tipo]) {
                $stmt->bindValue($nome, $valor, $tipo);
            }

            $stmt->execute();

            $pagamentos = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $pagamentos[] = new ParcelaPagamento(
                    (int) $row['id'],
                    (int) $row['parcela_id'],
                    (int) $row['conta_id'],
                    (int) $row['numero_parcela'],
                    (int) $row['banco_id'],
                    $row['banco_nome'],
                    (int) $row['valor_em_centavos'],
                    (int) $row['juros_em_centavos'],
                    $row['data_pagamento'],
                    $row['usuario_id'] !== null ? (int) $row['usuario_id'] : null,
                    $row['usuario_nome'],
                    $row['criado_em']
                );
            }

            return $pagamentos;
        } catch (PDOException $e) {
            Logger::error('Falha ao buscar pagamentos de parcelas', ['where' => $where, 'PDOException' => $e->getMessage()]);
            return [];
        }
    }
}
