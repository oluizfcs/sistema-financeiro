<?php

namespace App\Controllers\Services;

use App\Models\Database;
use DateTimeImmutable;
use PDO;
use Throwable;

/**
 * Único ponto de escrita de pagamentos de parcelas.
 *
 * Cada pagamento (total ou parcial) gera uma linha em parcela_pagamento.
 * A tabela parcela mantém apenas um cache (valor_pago_em_centavos e
 * juros_em_centavos) atualizado dentro da mesma transação.
 */
class PagamentoService
{
    private const JUROS_MAXIMO_EM_CENTAVOS = 100000000;

    /**
     * Registra um pagamento de uma parcela.
     *
     * @param int $parcelaId
     * @param int $bancoId
     * @param string $data Data do pagamento (Y-m-d)
     * @param int|null $principal Valor do principal a amortizar. null = restante total.
     * @param int $juros Juros cobrados neste pagamento (somam ao caixa debitado)
     * @param int|null $usuarioId
     * @return array Estado atualizado da parcela
     *
     * @throws PagamentoException
     */
    public static function pagarParcela(
        int $parcelaId,
        int $bancoId,
        string $data,
        int|null $principal,
        int $juros,
        int|null $usuarioId
    ): array {
        self::validarData($data);

        if ($juros < 0 || $juros > self::JUROS_MAXIMO_EM_CENTAVOS) {
            throw new PagamentoException('O valor do juros é inválido.');
        }

        $conn = Database::getConnection();
        $saldoBancoAntes = null;
        $saldoBancoDepois = null;

        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare('SELECT id, conta_id, valor_em_centavos, juros_em_centavos, valor_pago_em_centavos, paid, enabled FROM parcela WHERE id = :id FOR UPDATE');
            $stmt->bindValue(':id', $parcelaId, PDO::PARAM_INT);
            $stmt->execute();
            $parcela = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($parcela === false) {
                throw new PagamentoException('Parcela não encontrada.');
            }

            if ((int) $parcela['enabled'] !== 1) {
                throw new PagamentoException('Não é possível pagar uma parcela inativa.');
            }

            if ((int) $parcela['paid'] === 1) {
                throw new PagamentoException('Parcela já foi paga.');
            }

            $restante = (int) $parcela['valor_em_centavos']
                + (int) $parcela['juros_em_centavos']
                - (int) $parcela['valor_pago_em_centavos'];

            if ($restante <= 0) {
                throw new PagamentoException('Parcela já foi paga.');
            }

            if ($principal === null) {
                $principal = $restante;
            }

            if ($principal <= 0) {
                throw new PagamentoException('O valor do pagamento deve ser maior que zero.');
            }

            if ($principal > $restante) {
                throw new PagamentoException('O valor do pagamento não pode ser maior que o valor restante da parcela.');
            }

            $totalPago = $principal + $juros;

            $stmt = $conn->prepare('SELECT id, saldo_em_centavos, enabled FROM banco WHERE id = :id FOR UPDATE');
            $stmt->bindValue(':id', $bancoId, PDO::PARAM_INT);
            $stmt->execute();
            $banco = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($banco === false) {
                throw new PagamentoException('Conta bancária não encontrada.');
            }

            if ((int) $banco['enabled'] !== 1) {
                throw new PagamentoException('Não é possível pagar usando uma conta bancária inativa.');
            }

            $saldoBancoAntes = (int) $banco['saldo_em_centavos'];

            $stmt = $conn->prepare('INSERT INTO parcela_pagamento (parcela_id, banco_id, valor_em_centavos, juros_em_centavos, data_pagamento, usuario_id) VALUES (:parcela_id, :banco_id, :valor, :juros, :data, :usuario_id)');
            $stmt->bindValue(':parcela_id', $parcelaId, PDO::PARAM_INT);
            $stmt->bindValue(':banco_id', $bancoId, PDO::PARAM_INT);
            $stmt->bindValue(':valor', $principal, PDO::PARAM_INT);
            $stmt->bindValue(':juros', $juros, PDO::PARAM_INT);
            $stmt->bindValue(':data', $data, PDO::PARAM_STR);
            $stmt->bindValue(':usuario_id', $usuarioId, $usuarioId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
            $stmt->execute();

            $valorPagoTotal = (int) $parcela['valor_pago_em_centavos'] + $totalPago;
            $jurosTotal = (int) $parcela['juros_em_centavos'] + $juros;
            $restanteDepois = (int) $parcela['valor_em_centavos'] + $jurosTotal - $valorPagoTotal;
            $paid = $restanteDepois === 0;

            $stmt = $conn->prepare('UPDATE parcela SET valor_pago_em_centavos = :valor_pago, juros_em_centavos = :juros, data_pagamento = :data, banco_id = :banco_id, paid = :paid WHERE id = :id');
            $stmt->bindValue(':valor_pago', $valorPagoTotal, PDO::PARAM_INT);
            $stmt->bindValue(':juros', $jurosTotal, PDO::PARAM_INT);
            $stmt->bindValue(':data', $data, PDO::PARAM_STR);
            $stmt->bindValue(':banco_id', $bancoId, PDO::PARAM_INT);
            $stmt->bindValue(':paid', $paid, PDO::PARAM_BOOL);
            $stmt->bindValue(':id', $parcelaId, PDO::PARAM_INT);
            $stmt->execute();

            $stmt = $conn->prepare('UPDATE banco SET saldo_em_centavos = saldo_em_centavos - :total WHERE id = :id');
            $stmt->bindValue(':total', $totalPago, PDO::PARAM_INT);
            $stmt->bindValue(':id', $bancoId, PDO::PARAM_INT);
            $stmt->execute();

            $conn->commit();
            $saldoBancoDepois = $saldoBancoAntes - $totalPago;
        } catch (PagamentoException $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            Logger::error('Erro ao pagar parcela', [
                'parcela_id' => $parcelaId,
                'banco_id' => $bancoId,
                'data' => $data,
                'principal' => $principal,
                'juros' => $juros,
                'erro' => $e->getMessage(),
            ]);
            throw new PagamentoException('Erro ao pagar parcela.');
        }

        self::logSaldoBanco($bancoId, $saldoBancoAntes, $saldoBancoDepois, $usuarioId);

        return [
            'id' => $parcelaId,
            'conta_id' => (int) $parcela['conta_id'],
            'principal_em_centavos' => $principal,
            'juros_pagos_em_centavos' => $juros,
            'total_pago_em_centavos' => $totalPago,
            'valor_pago_em_centavos' => $valorPagoTotal,
            'valor_restante_em_centavos' => $restanteDepois,
            'juros_em_centavos' => $jurosTotal,
            'data_pagamento' => $data,
            'banco_id' => $bancoId,
            'paid' => $paid,
            'parcial' => !$paid && $valorPagoTotal > 0,
        ];
    }

    /**
     * Estorna todos os pagamentos de uma conta.
     *
     * As linhas do livro caixa são removidas (retenção de auditoria não é
     * necessária) e o valor é devolvido ao banco de cada pagamento.
     *
     * @return array{estornoCount: int, estornoTotal: int}
     *
     * @throws PagamentoException
     */
    public static function estornarConta(int $contaId, int|null $usuarioId): array
    {
        $conn = Database::getConnection();
        $pagamentosPorBanco = [];
        $parcelasEstornadas = 0;
        $estornoTotal = 0;
        $saldosAntes = [];

        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare('SELECT id, valor_pago_em_centavos, paid FROM parcela WHERE conta_id = :conta_id ORDER BY id FOR UPDATE');
            $stmt->bindValue(':conta_id', $contaId, PDO::PARAM_INT);
            $stmt->execute();
            $parcelas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($parcelas) === 0) {
                $conn->commit();
                return ['estornoCount' => 0, 'estornoTotal' => 0];
            }

            $parcelaIds = array_map(static fn(array $p): int => (int) $p['id'], $parcelas);
            $placeholders = implode(',', array_fill(0, count($parcelaIds), '?'));

            $stmt = $conn->prepare("SELECT id, banco_id, valor_em_centavos, juros_em_centavos FROM parcela_pagamento WHERE parcela_id IN ($placeholders) ORDER BY banco_id, id FOR UPDATE");
            $stmt->execute($parcelaIds);
            $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($pagamentos as $pagamento) {
                $bancoId = (int) $pagamento['banco_id'];
                $valor = (int) $pagamento['valor_em_centavos'] + (int) $pagamento['juros_em_centavos'];
                $pagamentosPorBanco[$bancoId] = ($pagamentosPorBanco[$bancoId] ?? 0) + $valor;
                $estornoTotal += $valor;
            }

            if (count($pagamentosPorBanco) > 0) {
                $bancoIds = array_keys($pagamentosPorBanco);
                $placeholdersBancos = implode(',', array_fill(0, count($bancoIds), '?'));

                $stmt = $conn->prepare("SELECT id, saldo_em_centavos FROM banco WHERE id IN ($placeholdersBancos) ORDER BY id FOR UPDATE");
                $stmt->execute($bancoIds);

                foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $banco) {
                    $saldosAntes[(int) $banco['id']] = (int) $banco['saldo_em_centavos'];
                }

                $stmt = $conn->prepare('UPDATE banco SET saldo_em_centavos = saldo_em_centavos + :total WHERE id = :id');
                foreach ($pagamentosPorBanco as $bancoId => $valor) {
                    $stmt->bindValue(':total', $valor, PDO::PARAM_INT);
                    $stmt->bindValue(':id', $bancoId, PDO::PARAM_INT);
                    $stmt->execute();
                }
            }

            $stmt = $conn->prepare("DELETE FROM parcela_pagamento WHERE parcela_id IN ($placeholders)");
            $stmt->execute($parcelaIds);

            $stmt = $conn->prepare('UPDATE parcela SET valor_pago_em_centavos = 0, juros_em_centavos = 0, data_pagamento = NULL, banco_id = NULL, paid = 0 WHERE conta_id = :conta_id');
            $stmt->bindValue(':conta_id', $contaId, PDO::PARAM_INT);
            $stmt->execute();

            foreach ($parcelas as $parcela) {
                if ((int) $parcela['valor_pago_em_centavos'] > 0 || (int) $parcela['paid'] === 1) {
                    $parcelasEstornadas++;
                }
            }

            $conn->commit();
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            Logger::error('Erro ao estornar pagamentos', ['conta_id' => $contaId, 'erro' => $e->getMessage()]);
            throw new PagamentoException('Erro ao estornar pagamentos.');
        }

        foreach ($pagamentosPorBanco as $bancoId => $valor) {
            self::logSaldoBanco(
                (int) $bancoId,
                $saldosAntes[$bancoId] ?? null,
                ($saldosAntes[$bancoId] ?? 0) + $valor,
                $usuarioId
            );
        }

        return ['estornoCount' => $parcelasEstornadas, 'estornoTotal' => $estornoTotal];
    }

    private static function validarData(string $data): void
    {
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $data);

        if ($date === false || $date->format('Y-m-d') !== $data) {
            throw new PagamentoException('Data inválida.');
        }
    }

    private static function logSaldoBanco(int $bancoId, int|null $antes, int|null $depois, int|null $usuarioId): void
    {
        if ($antes === null || $depois === null) {
            return;
        }

        $usuario = $usuarioId ?? (int) ($_SESSION['usuario_id'] ?? 0);

        if ($usuario <= 0) {
            return;
        }

        Logger::log(
            'banco',
            'saldo',
            Money::centavos_para_reais($antes),
            Money::centavos_para_reais($depois),
            $bancoId,
            $usuario
        );
    }
}
