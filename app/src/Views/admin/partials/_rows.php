<?php

use App\Controllers\Services\Money;

$nomesUsuarios = $nomesUsuarios ?? [];
$nomesReferencias = $nomesReferencias ?? [];

$acoesEspeciais = [
    'create' => 'Cadastro',
    'unable' => 'Inativação',
    'enable' => 'Ativação',
    'pay' => 'Pagamento',
];

$formataReferencia = function ($valor, array $mapa) {
    if ($valor === null || $valor === '') {
        return '-';
    }
    if (is_numeric($valor) && (int)$valor > 0) {
        return isset($mapa[(int)$valor]) ? $mapa[(int)$valor] : $valor;
    }
    return $valor;
};

foreach ($logs as $log):
    $info = $entidades[$log['entidade']] ?? ['label' => ucfirst($log['entidade']), 'route' => ''];

    $destinoId = $log['entidade'] === 'deposito' ? ($log['banco_destino'] ?? '') : $log['entidade_id'];
    $href = ($destinoId !== null && $destinoId !== '' && $info['route'] !== '')
        ? $_ENV['BASE_URL'] . '/' . $info['route'] . '/detalhar/' . $destinoId
        : '';

    $campo = $log['campo'];
    $antigo = $log['valor_antigo'];
    $novo = $log['valor_novo'];

    if (array_key_exists($campo, $acoesEspeciais)) {
        $campo = $acoesEspeciais[$campo];
        $antigo = '-';
        $novo = '-';
    }

    if (str_contains($log['campo'], 'Estorno') || $log['campo'] === 'parcelas estornadas') {
        $campo = 'Estorno';
        $antigo = '-';
        $novo = '-';
    }

    if (str_contains($log['campo'], 'Parcela ') && $log['campo'] !== 'Nº Parcelas') {
        $campo = 'Pagamento: ' . $log['campo'];
        $antigo = '-';
        $novo = '-';
    }

    if ($log['campo'] === 'valor' || str_contains($log['campo'], 'juros')) {
        $antigo = $antigo !== '' && $antigo !== null ? 'R$ ' . Money::centavos_para_reais($antigo) : '-';
        $novo = $novo !== '' && $novo !== null ? 'R$ ' . Money::centavos_para_reais($novo) : '-';
    }

    if ($log['campo'] === 'vencimento') {
        $antigo = $antigo ? implode('/', array_reverse(explode('-', $antigo))) : '-';
        $novo = $novo ? implode('/', array_reverse(explode('-', $novo))) : '-';
    }

    if (in_array($log['campo'], ['centro_de_custo_id', 'fornecedor_id', 'natureza_id'], true)) {
        $tipo = str_replace('_id', '', $log['campo']);
        $mapa = $nomesReferencias[$tipo] ?? [];
        $antigo = $formataReferencia($antigo, $mapa);
        $novo = $formataReferencia($novo, $mapa);
    }

    $dataLog = DateTime::createFromFormat('Y-m-d H:i:s', $log['data_log']);
    $dataFormatada = $dataLog ? $dataLog->format('d/m/Y H:i') : $log['data_log'];
?>
    <tr data-href="<?= htmlspecialchars($href) ?>">
        <td><?= $dataFormatada ?></td>
        <td><?= htmlspecialchars($nomesUsuarios[$log['usuario_id']] ?? '#' . $log['usuario_id']) ?></td>
        <td>
            <?php if ($href !== ''): ?>
                <a href="<?= htmlspecialchars($href) ?>"><?= $info['label'] ?> #<?= $log['entidade_id'] ?></a>
            <?php else: ?>
                <?= $info['label'] ?> #<?= $log['entidade_id'] ?>
            <?php endif; ?>
        </td>
        <td><?= $campo ?></td>
        <td><?= $antigo ?></td>
        <td><?= $novo ?></td>
    </tr>
<?php endforeach; ?>