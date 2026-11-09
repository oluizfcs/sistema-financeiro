<?php

use App\Controllers\Services\Money;
use App\Models\Fornecedor;
use App\Models\CentroDeCusto;
use App\Models\Conta;
use App\Models\Natureza;
use App\Models\ParcelaPagamento;

$data_criacao = DateTime::createFromFormat("Y-m-d H:i:s", $conta->getData_criacao());
if ($conta->getData_edicao() != null) {
    $data_edicao = DateTime::createFromFormat("Y-m-d H:i:s", $conta->getData_edicao());
    $data_edicao = $data_edicao->format("d/m/Y \à\s H:i");
} else {
    $data_edicao = 'nunca';
}

$formatter = new NumberFormatter('PT_BR', NumberFormatter::SPELLOUT);
$extenso = $formatter->format($conta->getValor_em_centavos() / 100);

$isPaid = $conta->isPaid();
$isEnabled = $conta->isEnabled();
$hasPaid = Conta::hasPayments($conta->getId());
$isParcial = !$isPaid && $isEnabled && $hasPaid;
$statusText = $isPaid ? 'Paga' : ($isParcial ? 'Parcial' : ($isEnabled ? 'A pagar' : 'Inativa'));
$statusClass = $isPaid ? 'status-paid' : ($isParcial ? 'status-partial' : ($isEnabled ? 'status-unpaid' : 'status-inactive'));

$totalPagoEmCentavos = 0;
$totalRestanteEmCentavos = 0;
foreach ($conta->getParcelas() as $parcelaResumo) {
    $totalPagoEmCentavos += $parcelaResumo->getValor_pago_em_centavos();
    $totalRestanteEmCentavos += $parcelaResumo->getValorRestante();
}
$pagamentos = ParcelaPagamento::getByConta($conta->getId());
?>

<h1>
    Detalhes da Conta
    <span class="status-badge <?= $statusClass ?>"><?= $statusText ?></span>
</h1>

<div class="section">
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">Valor total</span>
            <span class="info-value" title="<?= $extenso ?>">R$ <?= Money::centavos_para_reais($conta->getValor_em_centavos()) ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Pago</span>
            <span class="info-value">R$ <?= Money::centavos_para_reais($totalPagoEmCentavos) ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Restante</span>
            <span class="info-value">R$ <?= Money::centavos_para_reais($totalRestanteEmCentavos) ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Natureza</span>
            <span class="info-value"><?= Natureza::getById($conta->getNaturezaId())->getNome() ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Centro de custo</span>
            <span class="info-value"><?= CentroDeCusto::getById($conta->getCentro_de_custo_id())->getNome() ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Fornecedor</span>
            <span class="info-value"><?= $conta->getFornecedor_id() != null ? Fornecedor::getById($conta->getFornecedor_id())->getNome() : '<span style="color: #999;">N/A</span>' ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Criada em</span>
            <span class="info-value"><?= $data_criacao->format('d/m/Y \à\s H:i') ?></span>
        </div>
        <div class="info-item">
            <span class="info-label">Editada em</span>
            <span class="info-value"><?= $data_edicao ?></span>
        </div>
    </div>
</div>

<div class="section">
    <h2>Histórico</h2>
    <p class="description"><?= $conta->getHistorico() ?></p>
</div>

<div class="actions-bar">
    <a class="btn btn-secondary" href="<?= $_ENV['BASE_URL'] ?>/contas">Voltar</a>
    <?php if (isAdmin()): ?>
        <button type="button" class="btn btn-secondary" id="editar-conta-btn" onclick="openEditarContaDialog()">
            <i class="fa-solid fa-pen-to-square"></i> Editar
        </button>
        <form method="POST" action="" id="conta-action-form">
            <input type="hidden" name="conta_id" value="<?= $conta->getId() ?>">
            <input type="hidden" name="type" id="conta-action-type" value="unable">
            <?php if ($isEnabled): ?>
                <button type="submit" class="btn btn-secondary" id="inativar-btn">
                    <i class="fa-solid fa-box-archive"></i> Inativar
                </button>
            <?php else: ?>
                <button type="submit" class="btn btn-secondary" name="type" value="enable"
                    onclick="return confirm('Realmente deseja ativar esta conta?')">
                    <i class="fa-solid fa-box-open"></i> Ativar
                </button>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

<dialog closedby="any" id="estorno-dialog" style="max-width: 420px; padding:0;">
    <div class="dialog-wrapper">
        <div class="dialog-header">
            <h2>Estornar Parcelas Pagas</h2>
        </div>
        <div class="dialog-content">
            <p>Esta conta possui pagamentos (totais ou parciais). Para inativá-la, é necessário estornar todos os pagamentos. As cobranças serão removidas das contas bancárias vinculadas.</p>
            <div id="estorno-dialog-details" style="background-color: #dadada; border-radius: 8px; padding: 10px; margin-bottom: 10px; font-size: 0.9em;"></div>
            <div style="display:flex;gap:8px;">
                <button id="estorno-confirm-btn" class="btn btn-danger">Estornar e inativar</button>
                <button type="button" commandfor="estorno-dialog" command="close" class="btn btn-secondary">Cancelar</button>
            </div>
        </div>
    </div>
</dialog>

<?php if (isAdmin()): ?>
    <dialog closedby="any" id="editar-conta-dialog" style="max-width: 520px; padding:0;">
        <div class="dialog-wrapper">
            <div class="dialog-header">
                <h2>Editar Conta</h2>
                <button class="dialog-close" onclick="this.closest('dialog').close()">&times;</button>
            </div>
            <div class="dialog-content">
                <form method="POST" action="<?= $_ENV['BASE_URL'] ?>/contas" id="editar-conta-form">
                    <input type="hidden" name="type" value="editarAtributos">
                    <input type="hidden" name="conta_id" value="<?= $conta->getId() ?>">

                    <div class="form-row">
                        <div class="input-wrapper">
                            <label for="editar-conta-fornecedor">Fornecedor</label>
                            <select name="fornecedor" id="editar-conta-fornecedor" data-select2-dialog style="width: 100%">
                                <option value="-1">-- Nenhum --</option>
                                <?php foreach ($availableFilterOptions['fornecedores'] as $f): ?>
                                    <option value="<?= $f['id'] ?>" <?= $conta->getFornecedor_id() == $f['id'] ? 'selected' : '' ?>><?= $f['nome'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="input-wrapper">
                            <label for="editar-conta-natureza">Natureza</label>
                            <select name="natureza" id="editar-conta-natureza" data-select2-dialog style="width: 100%">
                                <?php foreach ($availableFilterOptions['naturezas'] as $n): ?>
                                    <option value="<?= $n['id'] ?>" <?= $conta->getNaturezaId() == $n['id'] ? 'selected' : '' ?>><?= $n['nome'] ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="input-wrapper">
                            <label for="btnSelectCentro">Centro de custo</label>
                            <input type="hidden" name="centro" id="centro" value="<?= $conta->getCentro_de_custo_id() ?>">
                            <button type="button" id="btnSelectCentro" class="centro-select-btn">
                                <span id="btnSelectCentroText"><?= CentroDeCusto::getById($conta->getCentro_de_custo_id())->getNome() ?></span>
                                <span>▼</span>
                            </button>
                        </div>

                        <div class="input-wrapper">
                            <label for="editar-conta-historico">Histórico</label>
                            <input type="text" id="editar-conta-historico" name="historico" maxlength="399" autocomplete="off"
                                value="<?= $conta->getHistorico() ?>">
                        </div>
                    </div>

                    <p style="font-size: 0.75rem; color: #6b7280; margin: 0.5rem 0 0;">
                        O valor e as parcelas desta conta não são alterados por esta edição.
                    </p>

                    <div class="dialog-actions">
                        <button type="submit" class="btn btn-primary">Salvar</button>
                        <button type="button" class="btn btn-secondary" commandfor="editar-conta-dialog" command="close">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </dialog>
    <?php include 'partials/_centros.php'; ?>
<?php endif; ?>

<div class="section">
    <h2><i class="fa-solid fa-money-bill"></i> Parcelas</h2>
    <div class="table-section">
        <table id="tableParcelas" class="non-clickable-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Vencimento</th>
                    <th>Paga em</th>
                    <th>Valor (R$)</th>
                    <th>Juros (R$)</th>
                    <th>Total (R$)</th>
                    <th>Pago (R$)</th>
                    <th>Restante (R$)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($conta->getParcelas() as $parcela): ?>
                    <?php
                    $parcelaTemPagamento = $parcela->getValor_pago_em_centavos() > 0;
                    $rowClass = $parcela->isPaid() ? 'paid-row' : ($parcela->isParcial() ? 'partial-row' : '');
                    ?>
                    <tr class="<?= $rowClass ?>" data-tem-pagamento="<?= $parcelaTemPagamento ? '1' : '0' ?>">
                        <td>
                            <?= $parcela->getNumero_parcela() ?>ª
                            <?php if ($parcela->isParcial()): ?>
                                <span class="partial-badge">parcial</span>
                            <?php endif; ?>
                        </td>
                        <td><?= new DateTime($parcela->getData_vencimento())->format('d/m/Y') ?></td>
                        <td>
                            <?php if ($parcelaTemPagamento && $parcela->getData_pagamento() !== null): ?>
                                <?= new DateTime($parcela->getData_pagamento())->format('d/m/Y') ?>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?= Money::centavos_para_reais($parcela->getValor_em_centavos()) ?></td>
                        <td><?= ($parcela->getJuros_em_centavos() > 0) ? Money::centavos_para_reais($parcela->getJuros_em_centavos()) : '-' ?></td>
                        <td><?= Money::centavos_para_reais($parcela->getValor_em_centavos() + $parcela->getJuros_em_centavos()) ?></td>
                        <td><?= $parcelaTemPagamento ? Money::centavos_para_reais($parcela->getValor_pago_em_centavos()) : '-' ?></td>
                        <td><?= $parcela->isPaid() ? '-' : Money::centavos_para_reais($parcela->getValorRestante()) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (count($pagamentos) > 0): ?>
    <div class="section">
        <h2><i class="fa-solid fa-receipt"></i> Pagamentos</h2>
        <div class="table-section">
            <table class="non-clickable-table">
                <thead>
                    <tr>
                        <th>Parcela</th>
                        <th>Data</th>
                        <th>Banco</th>
                        <th>Valor (R$)</th>
                        <th>Juros (R$)</th>
                        <th>Total (R$)</th>
                        <th>Usuário</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pagamentos as $pagamento): ?>
                        <tr>
                            <td><?= $pagamento->getNumero_parcela() ?>ª</td>
                            <td><?= (new DateTime($pagamento->getData_pagamento()))->format('d/m/Y') ?></td>
                            <td><?= $pagamento->getBanco_nome() ?></td>
                            <td><?= Money::centavos_para_reais($pagamento->getValor_em_centavos()) ?></td>
                            <td><?= $pagamento->getJuros_em_centavos() > 0 ? Money::centavos_para_reais($pagamento->getJuros_em_centavos()) : '-' ?></td>
                            <td><?= Money::centavos_para_reais($pagamento->getValor_total_em_centavos()) ?></td>
                            <td><?= $pagamento->getUsuario_nome() ?? '-' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<style>
    .status-badge {
        display: inline-block;
        font-size: 0.7rem;
        font-weight: 600;
        padding: 0.2rem 0.6rem;
        border-radius: 10px;
        vertical-align: middle;
        margin-left: 0.5rem;
    }

    .status-paid {
        background: #d1fae5;
        color: #065f46;
    }

    .status-unpaid {
        background: #fef3c7;
        color: #92400e;
    }

    .status-inactive {
        background: #e5e7eb;
        color: #374151;
    }

    .status-partial {
        background: #fde68a;
        color: #92400e;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
        gap: 1.5rem;
    }

    .info-item {
        display: flex;
        flex-direction: column;
        gap: 0.2rem;
    }

    .info-label {
        font-size: 0.7rem;
        font-weight: 500;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: 0.04em;
    }

    .info-value {
        font-size: 0.95rem;
        font-weight: 500;
        color: #111827;
    }

    .actions-bar {
        display: flex;
        gap: 0.5rem;
        align-items: center;
        margin: 1.5rem 0;
    }

    .paid-row {
        background-color: #d1fae5;
    }

    .partial-row {
        background-color: #fef3c7;
    }

    .partial-badge {
        display: inline-block;
        font-size: 0.62rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 0.1rem 0.4rem;
        border-radius: 8px;
        background: #f59e0b;
        color: #fff;
        margin-left: 0.25rem;
        vertical-align: middle;
    }

    .non-clickable-table tbody tr:hover {
        background-image: none;
    }
</style>

<script>
    const inativarBtn = document.getElementById("inativar-btn");
    const actionForm = document.getElementById("conta-action-form");
    const estornoDialog = document.getElementById("estorno-dialog");
    const hasPaid = <?= $hasPaid ? 'true' : 'false' ?>;

    if (inativarBtn) {
        inativarBtn.addEventListener("click", function(e) {
            if (!hasPaid) return;

            e.preventDefault();

            const details = document.getElementById("estorno-dialog-details");
            const parcelas = document.querySelectorAll('#tableParcelas tbody tr[data-tem-pagamento="1"]');
            const count = parcelas.length;

            details.innerHTML = "<strong>" + count + (count === 1 ? " parcela com pagamento será estornada" : " parcelas com pagamento serão estornadas") + ".</strong>";

            estornoDialog.showModal();

            const confirmBtn = document.getElementById("estorno-confirm-btn");
            const newConfirmBtn = confirmBtn.cloneNode(true);
            confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

            newConfirmBtn.addEventListener("click", function() {
                const formData = new FormData();
                formData.append("type", "estorno");
                formData.append("conta_id", "<?= $conta->getId() ?>");

                fetch('/contas', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(result => {
                        if (!result.ok) {
                            alert(result.msg);
                            return;
                        }
                        estornoDialog.close();
                        actionForm.submit();
                    });
            });
        });
    }

    const editarContaDialog = document.getElementById("editar-conta-dialog");

    function openEditarContaDialog() {
        if (!editarContaDialog) return;

        editarContaDialog.showModal();

        requestAnimationFrame(function() {
            $(editarContaDialog).find('[data-select2-dialog]').each(function() {
                if (!$(this).data('select2')) {
                    $(this).select2({ dropdownParent: $(editarContaDialog) });
                }
            });
        });
    }

    if (editarContaDialog) {
        // getNome() devolve o texto ja escapado pelo construtor. Decodifica para
        // exibir corretamente e usa json_encode com flags hex: dentro de um bloco
        // script as entidades html nao sao interpretadas, e as flags impedem que
        // um nome contendo a tag de fechamento quebre o bloco.
        const centroInicialNome = <?= json_encode(
            html_entity_decode(CentroDeCusto::getById($conta->getCentro_de_custo_id())->getNome(), ENT_QUOTES, 'UTF-8'),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        ) ?>;

        editarContaDialog.addEventListener("close", function() {
            document.getElementById("editar-conta-form").reset();
            document.getElementById("centro").value = "<?= $conta->getCentro_de_custo_id() ?>";
            document.getElementById("btnSelectCentroText").textContent = centroInicialNome;

            // o select2 guarda em cache o rotulo exibido, entao precisa ser avisado
            // de que os selects voltaram ao valor original do html.
            $("#editar-conta-fornecedor, #editar-conta-natureza").trigger("change");
        });

        document.getElementById("editar-conta-form").addEventListener("submit", function(e) {
            const natureza = document.getElementById("editar-conta-natureza");
            const centro = document.getElementById("centro");

            if (!natureza.value) {
                e.preventDefault();
                alert("Selecione uma natureza");
                natureza.focus();
                return;
            }

            if (centro.value === '') {
                e.preventDefault();
                alert("Selecione um centro de custo");
                document.getElementById("btnSelectCentro").focus();
                return;
            }
        });
    }
</script>

<?php if (isAdmin()) include '../src/templates/auditoria.php'; ?>