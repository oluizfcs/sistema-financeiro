<?php

declare(strict_types=1);

namespace App\Tests\Support;

use PDO;
use RuntimeException;

trait Fixtures
{
    private static int $fixtureCpf = 1;

    protected function insertRow(string $table, array $data): int
    {
        $columns = array_keys($data);
        $quotedColumns = array_map(static fn(string $column): string => '`' . $column . '`', $columns);
        $placeholders = array_map(static fn(string $column): string => ':' . $column, $columns);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', $quotedColumns),
            implode(', ', $placeholders)
        );

        $stmt = $this->pdo->prepare($sql);

        foreach ($data as $column => $value) {
            $type = $value === null ? PDO::PARAM_NULL : (is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
            $stmt->bindValue(':' . $column, $value, $type);
        }

        $stmt->execute();

        return (int) $this->pdo->lastInsertId();
    }

    protected function createUser(string $nome = 'Tester'): int
    {
        $cpf = sprintf('%011d', self::$fixtureCpf++);

        return $this->insertRow('usuario', [
            'cpf' => $cpf,
            'nome' => $nome,
            'senha' => 'x',
            'permissions' => 1,
            'enabled' => 1,
        ]);
    }

    protected function createBank(int $saldoEmCentavos = 100000, int $enabled = 1): int
    {
        return $this->insertRow('banco', [
            'nome' => 'Banco de teste',
            'saldo_em_centavos' => $saldoEmCentavos,
            'enabled' => $enabled,
        ]);
    }

    protected function createNatureza(string $nome = 'Natureza de teste'): int
    {
        return $this->insertRow('natureza', [
            'nome' => $nome,
            'enabled' => 1,
        ]);
    }

    protected function createCentroDeCusto(string $nome = 'Centro de teste', int|null $categoriaId = null): int
    {
        return $this->insertRow('centro_de_custo', [
            'nome' => $nome,
            'categoria_id' => $categoriaId,
            'enabled' => 1,
        ]);
    }

    protected function createFornecedor(string $nome = 'Fornecedor de teste'): int
    {
        return $this->insertRow('fornecedor', [
            'nome' => $nome,
            'enabled' => 1,
        ]);
    }

    protected function createConta(int $valorEmCentavos = 0, array $overrides = []): int
    {
        $data = [
            'historico' => 'Conta de teste',
            'valor_em_centavos' => $valorEmCentavos,
            'centro_de_custo_id' => $this->createCentroDeCusto(),
            'natureza_id' => $this->createNatureza(),
            'fornecedor_id' => $this->createFornecedor(),
            'paid' => 0,
            'enabled' => 1,
        ];

        foreach ($overrides as $column => $value) {
            $data[$column] = $value;
        }

        return $this->insertRow('conta', $data);
    }

    protected function createParcela(
        int $contaId,
        int $valorEmCentavos,
        string $dataVencimento = '2026-01-10',
        array $overrides = []
    ): int {
        if (!array_key_exists('numero_parcela', $overrides)) {
            $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(numero_parcela), 0) + 1 FROM parcela WHERE conta_id = :conta_id');
            $stmt->bindValue(':conta_id', $contaId, PDO::PARAM_INT);
            $stmt->execute();
            $overrides['numero_parcela'] = (int) $stmt->fetchColumn();
        }

        $data = [
            'numero_parcela' => 1,
            'valor_em_centavos' => $valorEmCentavos,
            'juros_em_centavos' => 0,
            'valor_pago_em_centavos' => 0,
            'data_vencimento' => $dataVencimento,
            'data_pagamento' => null,
            'banco_id' => null,
            'conta_id' => $contaId,
            'paid' => 0,
            'enabled' => 1,
        ];

        foreach ($overrides as $column => $value) {
            $data[$column] = $value;
        }

        return $this->insertRow('parcela', $data);
    }

    protected function createPagamento(
        int $parcelaId,
        int $bancoId,
        int $valorEmCentavos,
        int $jurosEmCentavos = 0,
        string $dataPagamento = '2026-01-01',
        int|null $usuarioId = null
    ): int {
        return $this->insertRow('parcela_pagamento', [
            'parcela_id' => $parcelaId,
            'banco_id' => $bancoId,
            'valor_em_centavos' => $valorEmCentavos,
            'juros_em_centavos' => $jurosEmCentavos,
            'data_pagamento' => $dataPagamento,
            'usuario_id' => $usuarioId,
        ]);
    }

    protected function createDeposito(
        int $usuarioId,
        int $bancoId,
        int $valorEmCentavos,
        string $dataDeposito = '2026-01-01 00:00:00'
    ): int {
        return $this->insertRow('deposito', [
            'usuario_id' => $usuarioId,
            'banco_id' => $bancoId,
            'valor_em_centavos' => $valorEmCentavos,
            'historico' => 'Depósito de teste',
            'data_deposito' => $dataDeposito,
        ]);
    }

    protected function fetchConta(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM conta WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new RuntimeException("Conta {$id} não encontrada.");
        }

        foreach (['id', 'valor_em_centavos', 'centro_de_custo_id', 'natureza_id', 'paid', 'enabled'] as $column) {
            $row[$column] = (int) $row[$column];
        }

        $row['fornecedor_id'] = $row['fornecedor_id'] !== null ? (int) $row['fornecedor_id'] : null;

        return $row;
    }

    protected function fetchParcela(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM parcela WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new RuntimeException("Parcela {$id} não encontrada.");
        }

        foreach (['id', 'numero_parcela', 'valor_em_centavos', 'juros_em_centavos', 'valor_pago_em_centavos', 'conta_id', 'paid', 'enabled'] as $column) {
            $row[$column] = (int) $row[$column];
        }

        $row['banco_id'] = $row['banco_id'] !== null ? (int) $row['banco_id'] : null;

        return $row;
    }

    protected function fetchBanco(int $id): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM banco WHERE id = :id');
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            throw new RuntimeException("Banco {$id} não encontrado.");
        }

        $row['id'] = (int) $row['id'];
        $row['saldo_em_centavos'] = (int) $row['saldo_em_centavos'];
        $row['enabled'] = (int) $row['enabled'];

        return $row;
    }

    protected function fetchLedger(int $parcelaId): array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM parcela_pagamento WHERE parcela_id = :parcela_id ORDER BY id');
        $stmt->bindValue(':parcela_id', $parcelaId, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            foreach (['id', 'parcela_id', 'banco_id', 'valor_em_centavos', 'juros_em_centavos'] as $column) {
                $row[$column] = (int) $row[$column];
            }

            $row['usuario_id'] = $row['usuario_id'] !== null ? (int) $row['usuario_id'] : null;
        }

        return $rows;
    }

    protected function countLedger(int $parcelaId): int
    {
        return count($this->fetchLedger($parcelaId));
    }

    protected function setSessionUser(int $usuarioId): void
    {
        $_SESSION['usuario_id'] = $usuarioId;
    }
}
