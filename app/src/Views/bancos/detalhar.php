<?php
$data_criacao = DateTime::createFromFormat("Y-m-d H:i:s", $banco->getData_criacao());
if ($banco->getData_edicao() != null) {
    $data_edicao = DateTime::createFromFormat("Y-m-d H:i:s", $banco->getData_edicao());
    $data_edicao = $data_edicao->format("d/m/Y \à\s H:i");
} else {
    $data_edicao = 'nunca';
}

$formatter = new NumberFormatter('PT_BR', NumberFormatter::SPELLOUT);
$extenso = $formatter->format($banco->getSaldo_em_centavos() / 100);

use App\Controllers\Services\Money;

?>

<h1>Conta bancária: <?= $banco->getNome() ?></h1>
<p title="<?= $extenso ?>">Saldo: R$ <?= $banco->getSaldo_em_reais() ?></p>
<br>
<a class="btn btn-secondary" href="<?= $_ENV['BASE_URL'] ?>/bancos">Voltar</a>
<button type="button" class="btn btn-success" onclick="openContaForm({ mode: 'cash', bancoId: <?= $banco->getId() ?>, lockBanco: true })"><i class="fa-solid fa-receipt"></i> Novo Lançamento</button>
<div class="report-dropdown">
    <button class="btn btn-secondary" id="toggle-report-menu" type="button" onclick="toggleReportMenu()"><i class="fa-solid fa-file"></i>
        Relatório</button>
    <div id="report-menu" class="report-menu" style="display:none;">
        <div class="report-menu-orientation">
            <span>Orientação:</span>
            <label><input type="radio" name="report_orientation" value="landscape" checked> Paisagem</label>
            <label><input type="radio" name="report_orientation" value="portrait"> Retrato</label>
        </div>
        <input type="hidden" id="banco_report_id" value="<?= $banco->getId() ?>">
        <div class="report-menu-field">
            <label for="banco_start_date">Data inicial:</label>
            <input type="date" id="banco_start_date" value="<?= date('Y-m-01') ?>">
        </div>
        <div class="report-menu-field">
            <label for="banco_end_date">Data final:</label>
            <input type="date" id="banco_end_date" value="<?= date('Y-m-t') ?>">
        </div>
        <button type="button" onclick="gerarRelatorioBancario()">Gerar extrato</button>
    </div>
</div>
<a class="btn btn-success" href="<?= $_ENV['BASE_URL'] ?>/bancos/depositar/<?= $banco->getId() ?>"><i class="fa-solid fa-arrow-down"></i> Depositar</a>
<?php if (isAdmin()): ?>
    <a class="btn btn-secondary" href="<?= $_ENV['BASE_URL'] ?>/bancos/atualizar/<?= $banco->getId() ?>"><i class="fa-solid fa-pen-to-square"></i> Atualizar</a>

    <form method="POST" action="">
        <input type="hidden" name="banco_id" value="<?= $banco->getId() ?>">
        <?php if ($banco->isEnabled()): ?>
            <button class="btn btn-secondary" name="type" value="unable" onclick="return confirm('Realmente deseja inativar esta conta bancária?')">
                <i class="fa-solid fa-box-archive"></i> Inativar
            </button>
        <?php else: ?>
            <button class="btn btn-secondary" name="type" value="enable" onclick="return confirm('Realmente deseja ativar esta conta bancária?')">
                <i class="fa-solid fa-box-open"></i> Ativar
            </button>
        <?php endif; ?>
    </form>
<?php endif; ?>

<div class="section" style="padding: 1rem 1.5rem;">
    <form method="GET" action="<?= $_ENV['BASE_URL'] ?>/bancos/detalhar/<?= $banco->getId() ?>" style="display: flex; align-items: center; flex-wrap: wrap; gap: 0.75rem; margin: 0;">
        <label for="mes" style="margin: 0;">Ver movimentações de:</label>
        <select name="mes" id="mes" onchange="this.form.submit()">
            <?php foreach ($mesesOpcoes as $opcao): ?>
                <option value="<?= $opcao['valor'] ?>" <?= $opcao['valor'] === $mesSelecionado ? 'selected' : '' ?>><?= $opcao['rotulo'] ?></option>
            <?php endforeach; ?>
        </select>
        <noscript><button type="submit" class="btn btn-secondary">Aplicar</button></noscript>
    </form>
</div>

<div class="section">
    <details>
        <summary style="cursor: pointer;">Ver depósitos de <?= $periodoLabel ?></summary>
        <div class="table-section">
            <table>
                <tr>
                    <th>Valor (R$)</th>
                    <th>Depositado em</th>
                    <th>Histórico</th>
                    <?php if (isAdmin()): ?>
                        <th>Ação</th>
                    <?php endif; ?>
                </tr>
                <?php if (count($depositos) === 0): ?>
                    <tr style="cursor: default">
                        <td colspan="<?= isAdmin() ? 4 : 3 ?>">Nenhum depósito em <?= $periodoLabel ?>.</td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($depositos as $deposito): ?>
                    <tr style="cursor: default">
                        <td><?= Money::centavos_para_reais($deposito->getValorEmCentavos()) ?></td>
                        <td><?= new DateTime($deposito->getDataDeposito())->format('d/m/Y') ?></td>
                        <td><?= $deposito->getHistorico() ?></td>
                        <?php if (isAdmin()): ?>
                            <td>
                                <button class="btn btn-secondary" data-deposito-id="<?= $deposito->getId() ?>" onclick="openEditarDepositoForm(this)"><i class="fa-solid fa-pen-to-square"></i></button>
                                <?php
                                $saldoAtualBanco = $banco->getSaldo_em_centavos();
                                $novoSaldoBanco = $saldoAtualBanco - $deposito->getValorEmCentavos();
                                $confirmacao = "Realmente deseja excluir o depósito de R\$ " . Money::centavos_para_reais($deposito->getValorEmCentavos())
                                    . " (" . (new DateTime($deposito->getDataDeposito()))->format('d/m/Y') . ")?\n\n"
                                    . "O valor será subtraído do saldo do banco, que passará de R\$ " . Money::centavos_para_reais($saldoAtualBanco)
                                    . " para R\$ " . Money::centavos_para_reais($novoSaldoBanco) . ".\n\n"
                                    . "Esta ação não pode ser desfeita.";
                                ?>
                                <form method="POST" action="<?= $_ENV['BASE_URL'] ?>/bancos/detalhar/<?= $banco->getId() ?>" style="display: inline;"
                                    onsubmit="return confirm(<?= htmlspecialchars(json_encode($confirmacao, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG), ENT_QUOTES) ?>);">
                                    <input type="hidden" name="type" value="deletarDeposito">
                                    <input type="hidden" name="banco_id" value="<?= $banco->getId() ?>">
                                    <input type="hidden" name="mes" value="<?= $mesSelecionado ?>">
                                    <input type="hidden" name="deposito_id" value="<?= $deposito->getId() ?>">
                                    <button type="submit" class="btn btn-danger" data-deposito-id="<?= $deposito->getId() ?>" title="Excluir depósito"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </td>
                         <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    </details>
</div>

<?php if (isAdmin()): ?>
<dialog id="editar-deposito-dialog" closedBy="any">
    <div class="dialog-wrapper">
        <div class="dialog-header">
            <h2 id="editar-deposito-dialog-title">Editar Depósito</h2>
            <button class="dialog-close" onclick="this.closest('dialog').close()">&times;</button>
        </div>
        <div class="dialog-content">
            <form method="POST" action="<?= $_ENV['BASE_URL'] ?>/bancos/detalhar/<?= $banco->getId() ?>" id="editar-deposito-form">
                <input type="hidden" name="type" value="editarDeposito">
                <input type="hidden" name="banco_id" value="<?= $banco->getId() ?>">
                <input type="hidden" name="mes" value="<?= $mesSelecionado ?>">
                <input type="hidden" name="deposito_id" id="editar-deposito-id" value="">

                <div class="form-row">
                    <div class="input-wrapper">
                        <label for="editar-deposito-valor">Valor (R$)</label>
                        <input type="text" class="money" id="editar-deposito-valor" name="valor"
                            maxlength="15" autocomplete="off" required autofocus>
                    </div>
                </div>
                <div class="form-row">
                    <div class="input-wrapper">
                        <label for="editar-deposito-historico">Histórico</label>
                        <input type="text" id="editar-deposito-historico" name="historico"
                            maxlength="255" autocomplete="off" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-wrapper">
                        <label for="editar-deposito-data">Data</label>
                        <input type="date" id="editar-deposito-data" name="data" required>
                    </div>
                </div>
                <div class="dialog-actions">
                    <button type="submit" class="btn btn-primary">Salvar</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('editar-deposito-dialog').close()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</dialog>
<?php endif; ?>

    <div class="section">
        <details>
            <summary style="cursor: pointer;">Ver contas pagas de <?= $periodoLabel ?></summary>
            <div class="table-section">
                <table>
                    <tr>
                        <th>Centro de custo</th>
                        <th>Fornecedor</th>
                        <th>Valor (R$)</th>
                        <th>Data de pagamento informada</th>
                        <th>Parcela</th>
                    </tr>
                    <?php if (count($parcelas) === 0): ?>
                        <tr style="cursor: default">
                            <td colspan="5">Nenhuma conta paga em <?= $periodoLabel ?>.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($parcelas as $parcela): ?>
                        <tr onclick="window.open('<?= $_ENV['BASE_URL'] ?>/contas/detalhar/<?= $parcela->getConta_id() ?>', '_blank')">
                            <td><?= $parcela->centro_de_custo ?></td>
                            <td><?= $parcela->fornecedor ?? '-' ?></td>
                            <td><?= Money::centavos_para_reais($parcela->getValor_em_centavos() + $parcela->getJuros_em_centavos()) ?></td>
                            <td><?= new DateTime($parcela->getData_pagamento())->format('d/m/Y') ?></td>
                            <td><?= $parcela->getNumero_parcela() . '/' . $parcela->total_parcelas ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </details>
    </div>

<?php
include '../src/Views/contas/partials/_form.php';
if (isAdmin()) include '../src/templates/auditoria.php';
?>

<?php if (isAdmin()): ?>
<script>
const BASE_URL = '<?= $_ENV['BASE_URL'] ?>';

function openEditarDepositoForm(btn) {
    const depositoId = btn.getAttribute('data-deposito-id');
    if (!depositoId) return;

    fetch(`${BASE_URL}/bancos/detalhar/<?= $banco->getId() ?>?format=json&deposito_id=${depositoId}`)
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                alert(data.error);
                return;
            }

            document.getElementById('editar-deposito-id').value = data.id;
            document.getElementById('editar-deposito-valor').value = 'R$ ' + (data.valor_em_centavos / 100).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
            document.getElementById('editar-deposito-historico').value = data.historico;
            document.getElementById('editar-deposito-data').value = data.data_deposito;

            const dialog = document.getElementById('editar-deposito-dialog');
            dialog.showModal();
            requestAnimationFrame(() => {
                $(dialog).find('.money').maskMoney({ prefix: 'R$ ' }).trigger('mask.maskMoney');
                dialog.querySelector('input[name="valor"]').focus();
            });
        })
        .catch(err => {
            console.error('Erro ao buscar depósito:', err);
            alert('Erro ao carregar dados do depósito');
        });
}

function resetEditarDepositoForm() {
    const form = document.getElementById('editar-deposito-form');
    if (form) form.reset();
    document.getElementById('editar-deposito-id').value = '';
    document.getElementById('editar-deposito-data').value = '<?= date('Y-m-d') ?>';
}

document.getElementById('editar-deposito-dialog').addEventListener('close', resetEditarDepositoForm);

document.getElementById('editar-deposito-form').addEventListener('submit', function(e) {
    const valorInput = document.getElementById('editar-deposito-valor');
    const historicoInput = document.getElementById('editar-deposito-historico');

    const valorCentavos = parseInt(valorInput.value.replaceAll(/[.,R$ ]/g, ''));
    if (isNaN(valorCentavos) || valorCentavos <= 0) {
        e.preventDefault();
        alert('O valor do depósito deve ser maior que zero');
        valorInput.focus();
        return;
    }

    if (historicoInput.value.length > 255) {
        e.preventDefault();
        alert('Histórico muito longo! (máx. 255 caracteres)');
        historicoInput.focus();
        return;
    }

    if (!confirm('Tem certeza que deseja salvar as alterações?')) {
        e.preventDefault();
    }
});
</script>
<?php endif; ?>