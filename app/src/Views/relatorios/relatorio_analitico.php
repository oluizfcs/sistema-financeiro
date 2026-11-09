<?php

use App\Controllers\Services\Money;

$showVencimentoColumn = true;
$showFornecedorColumn = true;
$showNaturezaColumn = true;
$showCentroColumn = true;
$showSubCentroColumn = true;
$showHistoricoColumn = true;
$showParcelaColumn = true;
$showValorColumn = true;

if (isset($data['report_source']) && $data['report_source'] === 'contas') {
    $hidden = $data['hidden_columns'];
    if (in_array('column1', $hidden)) $showVencimentoColumn = false;
    if (in_array('column2', $hidden)) $showFornecedorColumn = false;
    if (in_array('column3', $hidden)) $showNaturezaColumn = false;
    if (in_array('column4', $hidden)) {
        $showCentroColumn = false;
        $showSubCentroColumn = false;
    }
    if (in_array('column5', $hidden)) $showHistoricoColumn = false;
    if (in_array('column6', $hidden)) $showParcelaColumn = false;
    if (in_array('column7', $hidden)) $showValorColumn = false;
}

$fonte = ($data['orientation'] ?? 'landscape') === 'portrait' ? 10 : 11;
$limite = $fonte >= 11 ? 28 : 24;
$limiteHistorico = $fonte >= 11 ? 45 : 35;

$statusLabel = '';
$valorColumnLabel = 'Valor (R$)';
switch ($data['status']) {
    case 'unpaid':
        $statusLabel = 'Parcelas não pagas';
        $valorColumnLabel = 'A pagar (R$)';
        break;
    case 'paid':
        $statusLabel = 'Pagamentos realizados';
        $valorColumnLabel = 'Pago (R$)';
        break;
}

$filtros = [];
if ($data['natureza'] != null) {
    $filtros[] = 'Natureza: ' . $data['natureza'];
}

if ($data['centro'] != null) {
    $filtros[] = 'Centro de custo: ' . $data['centro'];
}

if ($data['fornecedor'] != null) {
    $filtros[] = 'Fornecedor: ' . $data['fornecedor'];
}

$cabecalho = trim($statusLabel . ' de ' . $data['start_date']->format('d/m/Y') . ' a ' . $data['end_date']->format('d/m/Y'));

if (count($filtros) > 0) {
    $cabecalho .= ' - ' . implode(' - ', $filtros);
}

$isPaid = $data['status'] === 'paid';

$formataData = function ($dataSql) {
    if ($dataSql === null || $dataSql === '') {
        return '-';
    }
    return implode('/', array_reverse(explode('-', $dataSql)));
};

$curta = function ($texto, $maximo) {
    $texto = (string)$texto;
    if (mb_strlen($texto) <= $maximo) {
        return $texto;
    }
    return trim(mb_substr($texto, 0, $maximo - 1)) . '…';
};
?>
<div>
    <?= $cabecalho ?>
</div>
<table>
    <tr>
        <?php if ($showVencimentoColumn): ?>
            <th><?= $isPaid ? 'Pago em' : 'Vencimento' ?></th>
        <?php endif; ?>
        <?php if ($showFornecedorColumn): ?>
            <th>Fornecedor</th>
        <?php endif; ?>
        <?php if ($showNaturezaColumn): ?>
            <th>Natureza</th>
        <?php endif; ?>
        <?php if ($showCentroColumn): ?>
            <th>Centro</th>
        <?php endif; ?>
        <?php if ($showSubCentroColumn): ?>
            <th>Sub centro</th>
        <?php endif; ?>
        <?php if ($showHistoricoColumn): ?>
            <th>Histórico</th>
        <?php endif; ?>
        <?php if ($showParcelaColumn): ?>
            <th>Parcela</th>
        <?php endif; ?>
        <?php if ($showValorColumn): ?>
            <th><?= $valorColumnLabel ?></th>
        <?php endif; ?>
    </tr>
    <?php $linha = 0; ?>
    <?php foreach ($data['rows'] as $row): ?>
        <?php $classeLinha = $linha % 2 === 1 ? ' class="zebra"' : ''; $linha++; ?>
        <tr<?= $classeLinha ?>>
            <?php if ($showVencimentoColumn): ?>
                <td><?= $formataData($isPaid ? ($row['data_pagamento'] ?? null) : $row['data_vencimento']) ?></td>
            <?php endif; ?>
            <?php if ($showFornecedorColumn): ?>
                <td><?= $curta($row['fornecedor'] ?? '-', $limite) ?></td>
            <?php endif; ?>
            <?php if ($showNaturezaColumn): ?>
                <td><?= $curta($row['natureza'], $limite) ?></td>
            <?php endif; ?>
            <?php if ($showCentroColumn): ?>
                <td><?= $curta($row['centro_pai'] ?? $row['centro'], $limite) ?></td>
            <?php endif; ?>
            <?php if ($showSubCentroColumn): ?>
                <td><?= $curta($row['centro'], $limite) ?></td>
            <?php endif; ?>
            <?php if ($showHistoricoColumn): ?>
                <td><?= $curta($row['historico'], $limiteHistorico) ?></td>
            <?php endif; ?>
            <?php if ($showParcelaColumn): ?>
                <td><?= $row['numero_parcela'] . '/' . $row['total_parcelas'] . (!empty($row['parcela_parcial']) ? ' (P)' : '') ?></td>
            <?php endif; ?>
            <?php if ($showValorColumn): ?>
                <td><?= Money::centavos_para_reais($row['valor_total_em_centavos']) ?></td>
            <?php endif; ?>
        </tr>
    <?php endforeach; ?>
</table>
<table style="width: 100%; border-collapse: collapse; margin-top: 12pt">
    <tr>
        <td style="text-align: left; font-size: 12pt;">
            Lançamentos: <?= count($data['rows']) ?>
        </td>
        <td style="text-align: right;">
            <strong style="font-size: 12pt;">Total: R$ <?= Money::centavos_para_reais($data['total']) ?></strong>
        </td>
    </tr>
</table>
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
</style>