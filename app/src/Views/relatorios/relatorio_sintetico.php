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

$cabecalho = trim($statusLabel . ' de ' . $data['start_date']->format('d/m/Y') . ' a ' . $data['end_date']->format('d/m/Y'));

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

$recuo = [
    0 => '&nbsp;&nbsp;&nbsp;',
    1 => '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;'
];

$totalCentros = 0;
foreach ($data['grupos'] as $grupo) {
    $totalCentros += count($grupo['linhas']);
}
?>
<div>
    <?= $cabecalho ?>
</div>
<?php if (!$data['mostrar_subcentros']): ?>
    <div style="font-size: <?= $fonte - 1 ?>pt; color: #555555;">Sub-centros agrupados no centro de custo superior</div>
<?php endif; ?>
<table>
    <tr>
        <th>Centro de custo</th>
        <th style="text-align: right;">Lançamentos</th>
        <th style="text-align: right;"><?= $valorColumnLabel ?></th>
    </tr>
    <?php foreach ($data['grupos'] as $indice => $grupo): ?>
        <?php if ($indice > 0): ?>
            <tr>
                <td colspan="3" style="background-color: #ffffff; font-size: 6pt;">&nbsp;</td>
            </tr>
        <?php endif; ?>
        <tr>
            <th colspan="3" style="text-align: left;"><?= $curta($grupo['natureza'], $limite) ?></th>
        </tr>
        <?php foreach ($grupo['linhas'] as $linhaData): ?>
            <?php
            if ($linhaData['nivel'] === 0 && $data['mostrar_subcentros']) {
                $estiloLinha = 'background-color: #efefef;';
            } elseif ($linhaData['nivel'] === 1) {
                $estiloLinha = 'color: #444444;';
            } else {
                $estiloLinha = '';
            }
            $negrito = $linhaData['eh_subtotal'] ? 'strong' : 'span';
            ?>
            <tr>
                <td style="<?= $estiloLinha ?>"><<?= $negrito ?>><?= $recuo[$linhaData['nivel']] ?? $recuo[0] ?><?= $curta($linhaData['nome'], $limite) ?></<?= $negrito ?>>
                <td style="text-align: right; <?= $estiloLinha ?>"><<?= $negrito ?>><?= $linhaData['quantidade'] ?></<?= $negrito ?>>
                <td style="text-align: right; <?= $estiloLinha ?>"><<?= $negrito ?>><?= Money::centavos_para_reais($linhaData['total']) ?></<?= $negrito ?>>
            </tr>
        <?php endforeach; ?>
        <tr>
            <td><strong><?= $recuo[0] ?>Subtotal <?= $curta($grupo['natureza'], $limite) ?></strong></td>
            <td style="text-align: right;"><strong><?= $grupo['quantidade'] ?></strong></td>
            <td style="text-align: right;"><strong><?= Money::centavos_para_reais($grupo['subtotal']) ?></strong></td>
        </tr>
    <?php endforeach; ?>
</table>
<table style="width: 100%; border-collapse: collapse; margin-top: 12pt">
    <tr>
        <td style="text-align: left; font-size: 12pt;">
            Naturezas: <?= count($data['grupos']) ?> &nbsp;·&nbsp; Centros de custo: <?= $totalCentros ?> &nbsp;·&nbsp;
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
