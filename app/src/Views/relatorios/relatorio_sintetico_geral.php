<?php

use App\Controllers\Services\Money;

$fonte = ($data['orientation'] ?? 'landscape') === 'portrait' ? 10 : 11;
$limite = $fonte >= 11 ? 28 : 24;

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

$cabecalho = 'Relatório Sintético Geral - ' . trim($statusLabel . ' de ' . $data['start_date']->format('d/m/Y') . ' a ' . $data['end_date']->format('d/m/Y'));

if (count($filtros) > 0) {
    $cabecalho .= ' - ' . implode(' - ', $filtros);
}

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
<div style="font-size: <?= $fonte - 1 ?>pt; color: #555555;">Sub-centros agrupados no centro de custo superior</div>
<table>
    <tr>
        <th>Centro de custo</th>
        <th style="text-align: right;">Lançamentos</th>
        <th style="text-align: right;"><?= $valorColumnLabel ?></th>
    </tr>
    <?php foreach ($data['centros'] as $centro): ?>
        <tr>
            <td><?= $curta($centro['centro_nome'], $limite) ?></td>
            <td style="text-align: right;"><?= $centro['quantidade'] ?></td>
            <td style="text-align: right;"><?= Money::centavos_para_reais($centro['total']) ?></td>
        </tr>
    <?php endforeach; ?>
</table>
<table style="width: 100%; border-collapse: collapse; margin-top: 12pt">
    <tr>
        <td style="text-align: left; font-size: 12pt;">
            Centros de custo: <?= count($data['centros']) ?> &nbsp;·&nbsp;
            Lançamentos: <?= $data['quantidade'] ?>
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
</style>
