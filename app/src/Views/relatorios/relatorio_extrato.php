<?php

use App\Controllers\Services\Money;

$fonte = ($data['orientation'] ?? 'landscape') === 'portrait' ? 10 : 11;
$limiteHistorico = $fonte >= 11 ? 60 : 50;

$curta = function ($texto, $maximo) {
    $texto = (string)$texto;
    if (mb_strlen($texto) <= $maximo) {
        return $texto;
    }
    return trim(mb_substr($texto, 0, $maximo - 1)) . '…';
};

$formataData = function ($dataSql) {
    if ($dataSql === null || $dataSql === '') {
        return '-';
    }
    return implode('/', array_reverse(explode('-', $dataSql)));
};

$cabecalho = 'Contas pagas de ' . $data['start_date']->format('d/m/Y') . ' a ' . $data['end_date']->format('d/m/Y');

if ($data['banco_nome'] !== null) {
    $cabecalho .= ' - Banco: ' . $data['banco_nome'];
} else {
    $cabecalho .= ' - Bancos: todos';
}
?>
<div>
    <?= $cabecalho ?>
</div>
<?php foreach ($data['extrato'] as $banco): ?>
    <div class="banco-titulo">
        <?= $banco['banco_nome'] ?>
    </div>
    <?php if (count($banco['rows']) > 0): ?>
        <table>
            <tr>
                <th>Pago em</th>
                <th>Fornecedor</th>
                <th>Natureza</th>
                <th>Centro</th>
                <th>Sub centro</th>
                <th>Histórico</th>
                <th>Parcela</th>
                <th>Valor (R$)</th>
            </tr>
            <?php $linha = 0; ?>
            <?php foreach ($banco['rows'] as $mov): ?>
                <?php $classeLinha = $linha % 2 === 1 ? ' class="zebra"' : ''; $linha++; ?>
                <tr<?= $classeLinha ?>>
                    <td><?= $formataData($mov['data']) ?></td>
                    <td><?= $mov['fornecedor'] !== null ? $curta($mov['fornecedor'], 20) : '-' ?></td>
                    <td><?= $mov['natureza'] !== null ? $curta($mov['natureza'], 20) : '-' ?></td>
                    <td><?= $mov['centro'] ?? '-' ?></td>
                    <td><?= $mov['subcentro'] ?? '-' ?></td>
                    <td><?= $curta($mov['historico'], $limiteHistorico) ?></td>
                    <td><?= $mov['numero_parcela'] !== null ? $mov['numero_parcela'] : 'Entrada:' ?></td>
                    <?php if($mov['saida'] > 0): ?>
                        <td><?= Money::centavos_para_reais($mov['saida']) ?></td>
                    <?php elseif($mov['entrada'] > 0): ?>
                        <td>+<?= Money::centavos_para_reais($mov['entrada']) ?></td>
                    <?php else: ?>
                        <td>-</td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
        </table>
        <table style="width: 100%; border-collapse: collapse; margin-top: 12pt">
            <tr>
                <td style="text-align: left;">Saldo inicial: <?= Money::centavos_para_reais($banco['saldo_inicial']) ?></td>
                <td style="text-align: center;">Entradas: <?= Money::centavos_para_reais($banco['total_entradas']) ?></td>
                <td style="text-align: center;">Saídas: <?= Money::centavos_para_reais($banco['total_saidas']) ?></td>
                <td style="text-align: right;">
                    <strong>
                        Líquido: R$ <?= Money::centavos_para_reais(($banco['saldo_inicial'] + $banco['total_entradas']) - $banco['total_saidas']) ?>
                    </strong>
                </td>
            </tr>
        </table>
        <br>
    <?php endif; ?>
<?php endforeach; ?>
<style>
    table {
        border-collapse: collapse;
        width: 100%;
    }

    th {
        font-size: <?= $fonte ?>pt;
        padding: 2px 3px;
        text-align: left;
        background-color: #dddddd;
        white-space: nowrap;
    }

    td {
        font-size: <?= $fonte ?>pt;
        padding: 1px 3px;
        white-space: nowrap;
    }

    tr {
        page-break-inside: avoid;
    }

    .zebra td {
        background-color: #ececec;
    }

    .banco-titulo {
        font-size: <?= $fonte ?>pt;
        font-weight: bold;
        margin-top: 10pt;
        margin-bottom: 4pt;
    }
</style>