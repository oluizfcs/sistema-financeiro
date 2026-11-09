<?php

use App\Controllers\Services\Money;

$fonte = ($data['orientation'] ?? 'landscape') === 'portrait' ? 10 : 11;
$limite = $fonte >= 11 ? 28 : 24;
$limiteHistorico = $fonte >= 11 ? 60 : 50;

$curta = function ($texto, $maximo) {
    $texto = (string)$texto;
    if (mb_strlen($texto) <= $maximo) {
        return $texto;
    }
    return trim(mb_substr($texto, 0, $maximo - 1)) . '…';
};

$showVencimentoColumn = true;
$showNaturezaColumn = true;
$showCentroColumn = true;
$showFornecedorColumn = true;
$showHistoricoColumn = true;
$showParcelaColumn = true;
$showValorColumn = true;

if (isset($data['report_source']) && $data['report_source'] === 'contas') {
    $hidden = $data['hidden_columns'];
    if (in_array('column1', $hidden)) $showVencimentoColumn = false;
    if (in_array('column2', $hidden)) $showFornecedorColumn = false;
    if (in_array('column3', $hidden)) $showNaturezaColumn = false;
    if (in_array('column4', $hidden)) $showCentroColumn = false;
    if (in_array('column5', $hidden)) $showHistoricoColumn = false;
    if (in_array('column6', $hidden)) $showParcelaColumn = false;
    if (in_array('column7', $hidden)) $showValorColumn = false;
} else {
    if ($data['natureza'] != null) {
        $showNaturezaColumn = false;
    }

    if ($data['fornecedor'] != null) {
        $showFornecedorColumn = false;
    }
}

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

$formataData = function ($dataSql) {
    if ($dataSql === null || $dataSql === '') {
        return '-';
    }
    return implode('/', array_reverse(explode('-', $dataSql)));
};

$isPaid = $data['status'] === 'paid';
$dateColumn = $isPaid ? 'data_pagamento' : 'data_vencimento';

$colspan = 0;
if ($showVencimentoColumn) $colspan++;
if ($showFornecedorColumn) $colspan++;
if ($showNaturezaColumn) $colspan++;
if ($showCentroColumn) $colspan++;
if ($showHistoricoColumn) $colspan++;
if ($showParcelaColumn) $colspan++;
if ($showValorColumn) $colspan++;

$totalLancamentos = 0;
$semanaNum = 1;
?>
<div>
    <?= $cabecalho ?>
</div>
    <?php foreach ($data['weeks'] as $week): ?>
    <?php
    usort($week['rows'], function ($a, $b) use ($dateColumn) {
        return strcmp($a[$dateColumn], $b[$dateColumn]);
    });
    if (count($week['rows']) > 0):
    ?>
        <?php $totalLancamentos += count($week['rows']); ?>
        <table>
            <tr>
                <th colspan="<?= $colspan ?>" style="text-align: center";>Semana <?= $semanaNum ?></th>
            </tr>
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
                    <th>Centro de custo</th>
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
            <?php foreach ($week['rows'] as $row): ?>
                <?php $classeLinha = $linha % 2 === 1 ? ' class="zebra"' : ''; $linha++; ?>
                <tr<?= $classeLinha ?>>
                    <?php if ($showVencimentoColumn): ?>
                        <td><?= $formataData($row[$dateColumn]) ?></td>
                    <?php endif; ?>

                    <?php if ($showFornecedorColumn): ?>
                        <td><?= $curta($row['fornecedor'] ?? '-', $limite) ?></td>
                    <?php endif; ?>

                    <?php if ($showNaturezaColumn): ?>
                        <td><?= $curta($row['natureza'], $limite) ?></td>
                    <?php endif; ?>

                    <?php if ($showCentroColumn): ?>
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
                <td style="text-align: right;">
                    <strong style="font-size: 12pt;">Subtotal: R$ <?= Money::centavos_para_reais($week['total']) ?></strong>
                </td>
            </tr>
        </table>
        <?php $semanaNum++; ?>
        <br>
    <?php endif; ?>
<?php endforeach; ?>
<table style="width: 100%; border-collapse: collapse; margin-top: 12pt">
    <tr>
        <td style="text-align: left; font-size: 12pt;">
            Lançamentos: <?= $totalLancamentos ?>
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