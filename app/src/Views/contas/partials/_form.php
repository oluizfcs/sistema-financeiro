<?php

use App\Models\Permissions;

if (!isset($centros) || empty($centros)) {
    $centros = \App\Models\CentroDeCusto::getOptionsWithHierarchy();
}
$postData = $_SESSION['post_data'] ?? [];
$isNormalUser = !isAdmin();
$currentUser = $currentUser ?? \App\Models\Usuario::getById($_SESSION['usuario_id']);
$canCreateFutureBills = Permissions::userCan($currentUser->getPermissions(), Permissions::CREATE_FUTURE_BILLS);
?>
<dialog id="conta-dialog-form" closedBy="any">
    <div class="dialog-wrapper">
        <div class="dialog-header">
            <h2 id="dialog-form-title">Cadastrar Conta</h2>
            <button class="dialog-close" onclick="this.closest('dialog').close()">&times;</button>
        </div>
        <div class="dialog-content">
            <?php
                $_SESSION['message'] = $_SESSION['post_data']['show_dialog_msg'];
                include '../src/templates/message.php'; 
            ?>
            <form method="POST" action="<?= $_ENV['BASE_URL'] ?>/contas">
                <input type="hidden" name="type" id="form-type" value="create">
                <input type="hidden" name="conta_id" id="form-conta-id" value="">
                <input type="hidden" name="lancamento_a_vista" id="form-lancamento-a-vista" value="0">
                <input type="hidden" id="today" value="<?= (new DateTime())->format('Y-m-d') ?>">

                <div class="form-row">
                    <div class="input-wrapper">
                        <label for="fornecedor">Fornecedor</label>
                        <select name="fornecedor" id="fornecedor" data-select2-dialog style="width: 100%">
                            <option value="-1">-- Nenhum --</option>
                            <?php foreach ($availableFilterOptions['fornecedores'] as $f): ?>
                                <option value="<?= $f['id'] ?>" <?= ($postData['fornecedor'] ?? null) == $f['id'] ? 'selected' : '' ?>><?= $f['nome'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-wrapper">
                        <label for="natureza">Natureza</label>
                        <select name="natureza" id="natureza" data-select2-dialog style="width: 100%">
                            <option disabled selected value="-1">-- Selecione --</option>
                            <?php foreach ($availableFilterOptions['naturezas'] as $n): ?>
                                <option value="<?= $n['id'] ?>" <?= ($postData['natureza'] ?? null) == $n['id'] ? 'selected' : '' ?>><?= $n['nome'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-wrapper">
                        <label for="btnSelectCentro">Centro de custo</label>
                        <input type="hidden" name="centro" id="centro" value="<?= htmlspecialchars($postData['centro'] ?? '') ?>">
                        <button type="button" id="btnSelectCentro" class="centro-select-btn">
                            <span id="btnSelectCentroText">
                                <?php
                                $selectedName = '-- Selecione --';
                                $selectedValue = $postData['centro'] ?? null;
                                if ($selectedValue) {
                                    foreach ($centros as $centro) {
                                        if (isset($centro['is_group']) && $centro['is_group']) {
                                            foreach ($centro['children'] as $child) {
                                                if ($child['id'] == $selectedValue) {
                                                    $selectedName = $child['nome'];
                                                    break 2;
                                                }
                                            }
                                        } else {
                                            if ($centro['id'] == $selectedValue) {
                                                $selectedName = $centro['nome'];
                                                break;
                                            }
                                        }
                                    }
                                }
                                echo htmlspecialchars($selectedName);
                                ?>
                            </span>
                            <span>▼</span>
                        </button>
                    </div>

                    <div class="input-wrapper">
                        <label for="historico">Histórico</label>
                        <textarea name="historico" id="historico" maxlength="399" autocomplete="off"><?= htmlspecialchars($postData['historico'] ?? '') ?></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="input-wrapper" style="flex:1">
                        <label for="valor_em_reais">Valor</label>
                        <input type="text" class="money" id="valor_em_reais" name="valor_em_reais"
                            maxlength="22" autocomplete="off" required
                            value="<?= $postData['valor_em_reais'] ?? 'R$ 0,00' ?>"
                            onchange="generateInstallments()">
                    </div>

                    <div class="input-wrapper" id="forma-pagamento-wrapper">
                        <p>Forma de pagamento</p>
                        <div id="formas-pagamento">
                            <button class="btn btn-forma-pagamento" id="btn-avista" type="button" style="border-radius: 30px 0px 0px 30px; border-right: 0px;" onclick="paymentMethod('a vista')"><span id="check-avista" style="color: white;">&check;</span> À vista</button>
                            <button class="btn btn-forma-pagamento" id="btn-parcelado" type="button" style="border-radius: 0px 30px 30px 0px; margin-left: -7px;" onclick="paymentMethod('parcelado')"><span id="check-parcelado" style="color: white;">&check;</span> Parcelado</button>
                        </div>
                        <input type="hidden" id="forma-pagamento" name="forma-pagamento" value="<?= $postData['forma-pagamento'] ?? 'a vista' ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="input-wrapper" id="input-parcela-a-vista">
                        <label id="labelDataParcelaAVista" for="dataParcelaAVista">Data de pagamento</label>
                        <input type="date" name="dataParcelaAVista" id="dataParcelaAVista" class="<?= $canCreateFutureBills ? '' : 'hide' ?>"
                            <?= $isNormalUser ? 'min="' . (new DateTime('-3 days'))->format('Y-m-d') . '"' : '' ?>
                            onchange="checkIfShouldShowBankAccounts()"
                            value="<?= $postData['dataParcelaAVista'] ?? (new DateTime())->format('Y-m-d') ?>">
                        <div id="date-quick-buttons" class="<?= $canCreateFutureBills ? 'hide' : '' ?>">
                            <button type="button" class="date-btn" data-offset="0" onclick="setQuickDate(0)">
                                <span class="date-btn-day"></span>
                                <span class="date-btn-label">(hoje)</span>
                            </button>
                            <button type="button" class="date-btn" data-offset="-1" onclick="setQuickDate(-1)">
                                <span class="date-btn-day"></span>
                                <span class="date-btn-label">(ontem)</span>
                            </button>
                            <button type="button" class="date-btn" data-offset="-2" onclick="setQuickDate(-2)">
                                <span class="date-btn-day"></span>
                                <span class="date-btn-label">(2 dias)</span>
                            </button>
                            <button type="button" class="date-btn" data-offset="-3" onclick="setQuickDate(-3)">
                                <span class="date-btn-day"></span>
                                <span class="date-btn-label">(3 dias)</span>
                            </button>
                        </div>
                    </div>
                    <div class="input-wrapper hide" id="input-banco">
                        <label for="banco">Conta bancária <span style="font-weight:200;font-size:13px">(onde será descontado)</span></label>
                        <select name="banco" id="banco" data-select2-dialog style="width: 100%">
                            <option value="-1">-- Selecione --</option>
                            <?php foreach ($bancos as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= ($postData['banco'] ?? null) == $b['id'] ? 'selected' : '' ?>><?= $b['nome'] ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div id="quantidade-parcelas" class="<?= ($postData['forma-pagamento'] ?? 'a vista') == 'parcelado' ? '' : 'hide' ?>">
                        <div class="input-wrapper">
                            <label for="qtd_parcela">Quantidade de parcelas</label>
                            <input type="number" name="qtd_parcela" id="qtd_parcela" min="1" max="300" step="1"
                                value="<?= $postData['qtd_parcela'] ?? '1' ?>"
                                onchange="generateInstallments()">
                        </div>
                    </div>

                    <div id="auto-dates" class="input-wrapper">
                        <div class="input-wrapper">
                            <label for="initial-date">Primeiro Vencimento:</label>
                            <input type="date" id="initial-date" onchange="updateButtonSempreDiaX()"
                                <?= $isNormalUser ? 'min="' . (new DateTime('-3 days'))->format('Y-m-d') . '"' : '' ?>>
                        </div>
                        <div class="auto-dates-buttons">
                            <button type="button" id="sempre-dia-x" onclick="dateAutoFill(document.getElementById('initial-date').value.split('-')[2], 'alwaysOnDayX')">sempre no dia __</button>
                            <button type="button" onclick="dateAutoFill(30)">a cada 30 dias</button>
                            <button type="button" onclick="dateAutoFill(7)">a cada 7 dias</button>
                        </div>
                        <div class="auto-dates-custom">
                            <span>a cada </span>
                            <input type="number" id="a-cada-x" min="1" max="999" step="1" value="1">
                            <select id="unit">
                                <option id="opt-dia" value="dia">dia</option>
                                <option id="opt-mes" value="mes">mês</option>
                                <option id="opt-ano" value="ano">ano</option>
                            </select>
                            <button type="button" id="customPace">></button>
                        </div>
                    </div>
                </div>

                <div id="secao-parcelas" class="<?= ($postData['forma-pagamento'] ?? 'a vista') == 'parcelado' ? '' : 'hide' ?>">
                    <h3>Parcelas</h3>
                    <div class="table-section" style="max-height: 50vh;">
                        <table id="installments" class="non-clickable-table"></table>
                    </div>
                    <br>
                    <span id="centavos-faltando"></span>
                </div>

                <div class="dialog-actions">
                    <button type="submit" class="btn btn-primary" id="form-submit-btn">Cadastrar</button>
                    <button type="button" class="btn btn-secondary" onclick="document.getElementById('conta-dialog-form').close()">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    <?php include '_centros.php' ?>
</dialog>

<?php $cadastrarContaJs = ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/js/cadastrarConta.js'; ?>
<script src="<?= $_ENV['BASE_URL'] ?>/js/cadastrarConta.js?v=<?= is_file($cadastrarContaJs) ? filemtime($cadastrarContaJs) : '1' ?>"></script>
<script>
    const IS_NORMAL_USER = <?= $isNormalUser ? 'true' : 'false' ?>;
    const CAN_CREATE_FUTURE_BILLS = <?= $canCreateFutureBills ? 'true' : 'false' ?>;

    function initQuickDateButtons() {
        let buttons = document.querySelectorAll('#date-quick-buttons .date-btn');
        buttons.forEach(function(btn) {
            let offset = parseInt(btn.getAttribute('data-offset'));
            let d = new Date();
            d.setDate(d.getDate() + offset);
            let dd = String(d.getDate()).padStart(2, '0');
            let mm = String(d.getMonth() + 1).padStart(2, '0');
            btn.querySelector('.date-btn-day').textContent = dd + '/' + mm;
        });
        highlightSelectedDateButton();
    }

    function highlightSelectedDateButton() {
        let currentValue = document.getElementById('dataParcelaAVista').value;
        let buttons = document.querySelectorAll('#date-quick-buttons .date-btn');
        buttons.forEach(function(btn) {
            btn.classList.remove('selected');
            let offset = parseInt(btn.getAttribute('data-offset'));
            let d = new Date();
            d.setDate(d.getDate() + offset);

            let ano = d.getFullYear();
            let mes = String(d.getMonth() + 1).padStart(2, '0');
            let dia = String(d.getDate()).padStart(2, '0');
            let val = `${ano}-${mes}-${dia}`;

            if (val === currentValue) {
                btn.classList.add('selected');
            }
        });
    }

    function setQuickDate(daysOffset) {
        let d = new Date();
        d.setDate(d.getDate() + daysOffset);

        let ano = d.getFullYear();
        let mes = String(d.getMonth() + 1).padStart(2, '0');
        let dia = String(d.getDate()).padStart(2, '0');

        document.getElementById('dataParcelaAVista').value = `${ano}-${mes}-${dia}`;

        highlightSelectedDateButton();
        checkIfShouldShowBankAccounts();
    }

    document.getElementById('conta-dialog-form').addEventListener('keydown', function(e) {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            const tag = document.activeElement?.tagName;
            if (tag === 'INPUT' || tag === 'SELECT' || tag === 'TEXTAREA') return;
            if (document.activeElement?.closest('.select2-container, .select2-dropdown')) return;
            e.preventDefault();
            const content = this.querySelector('.dialog-content');
            if (content) {
                content.scrollTop += e.key === 'ArrowDown' ? 50 : -50;
            }
        }
    });

    let contaFormMode = 'default';

    function applyContaFormMode(mode) {
        contaFormMode = mode === 'cash' ? 'cash' : 'default';
        const isCash = contaFormMode === 'cash';
        const dialog = document.getElementById('conta-dialog-form');
        const bancoSelect = document.getElementById('banco');
        const bancoWrapper = document.getElementById('input-banco');
        const labelData = document.getElementById('labelDataParcelaAVista');

        dialog.dataset.mode = contaFormMode;
        document.getElementById('form-lancamento-a-vista').value = isCash ? '1' : '0';
        document.getElementById('forma-pagamento-wrapper').classList.toggle('hide', isCash);
        document.getElementById('dialog-form-title').textContent = isCash ? 'Lançamento à vista' : 'Cadastrar Conta';
        document.getElementById('form-submit-btn').textContent = isCash ? 'Lançar e pagar' : 'Cadastrar';

        if (isCash) {
            paymentMethod('a vista');
        } else {
            bancoWrapper.classList.remove('locked');
            document.getElementById('btn-parcelado').classList.remove('locked-disabled');
            bancoSelect.value = '-1';
            if ($(bancoSelect).data('select2')) {
                $(bancoSelect).val('-1').trigger('change');
            }
        }

        checkIfShouldShowBankAccounts();

        // Fonte da verdade da visibilidade: não depende de JS externo em cache.
        bancoWrapper.classList.toggle('hide', !isCash);
        bancoWrapper.style.display = isCash ? 'flex' : 'none';
        labelData.textContent = isCash ? 'Data de pagamento:' : 'Data de vencimento:';
    }

    function openContaForm(options) {
        options = options || {};
        let d = document.getElementById('conta-dialog-form');
        const mode = options.mode || (options.bancoId || options.lockBanco ? 'cash' : 'default');
        document.getElementById('form-type').value = 'create';
        document.getElementById('form-conta-id').value = '';
        applyContaFormMode(mode);
        d.showModal();
        requestAnimationFrame(function() {
            $(d).find('[data-select2-dialog]').each(function() {
                if (!$(this).data('select2')) {
                    $(this).select2({
                        dropdownParent: $(d)
                    });
                }
            });
            if (options.bancoId) {
                let s = document.getElementById('banco');
                s.value = options.bancoId;
                if ($(s).data('select2')) {
                    $(s).val(options.bancoId).trigger('change');
                }
                if (options.lockBanco) {
                    document.getElementById('input-banco').classList.add('locked');
                    document.getElementById('input-banco').classList.remove('hide');
                    paymentMethod('a vista');
                    document.getElementById('btn-parcelado').classList.add('locked-disabled');
                }
            }
        });
    }

    function openEditContaForm(contaId) {
        fetch('<?= $_ENV['BASE_URL'] ?>/contas/detalhar/' + contaId + '?format=json')
            .then(r => r.json())
            .then(data => {
                applyContaFormMode('default');

                const hasPaid = data.parcelas.some(p => p.paid || (p.valor_pago_em_centavos || 0) > 0);
                if (hasPaid) {
                    return;
                }

                document.getElementById('form-type').value = 'update';
                document.getElementById('form-conta-id').value = data.id;
                document.getElementById('dialog-form-title').textContent = 'Editar Conta';
                document.getElementById('form-submit-btn').textContent = 'Salvar';

                document.getElementById('fornecedor').value = data.fornecedor_id ?? -1;
                document.getElementById('natureza').value = data.natureza_id;
                document.getElementById('centro').value = data.centro_de_custo_id;
                document.getElementById('historico').value = data.historico;
                document.getElementById('valor_em_reais').value = 'R$ ' + (data.valor_em_centavos / 100).toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});

                $('#fornecedor').val(data.fornecedor_id ?? -1).trigger('change');
                $('#natureza').val(data.natureza_id).trigger('change');
                document.getElementById('btnSelectCentroText').textContent = data.centro_de_custo_nome || '-- Selecione --';

                const parcelas = data.parcelas;

                if (parcelas.length === 1) {
                    paymentMethod('a vista');
                    document.getElementById('dataParcelaAVista').value = parcelas[0].data_vencimento;
                    if (typeof highlightSelectedDateButton === 'function') highlightSelectedDateButton();
                } else {
                    document.getElementById('qtd_parcela').value = parcelas.length;
                    paymentMethod('parcelado');

                    for (let i = 0; i < parcelas.length; i++) {
                        let idx = i + 1;
                        let vencInput = document.getElementById('parcela' + idx + '_vencimento');
                        let valorInput = document.getElementById('parcela' + idx + '_valor');
                        if (vencInput && parcelas[i].data_vencimento) {
                            vencInput.value = parcelas[i].data_vencimento;
                        }
                        if (valorInput) {
                            let v = parcelas[i].valor_em_centavos / 100;
                            valorInput.value = v.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        }
                    }
                    sumInstallments();
                }

                let d = document.getElementById('conta-dialog-form');
                d.showModal();
                requestAnimationFrame(function() {
                    $(d).find('[data-select2-dialog]').each(function() {
                        if (!$(this).data('select2')) {
                            $(this).select2({ dropdownParent: $(d) });
                        }
                    });
                    $('#centro').val(data.centro_de_custo_id).trigger('change');
                });
            });
    }

    function resetContaForm() {
        const d = document.getElementById('conta-dialog-form');
        const form = d.querySelector('form');

        form.reset();

        contaFormMode = 'default';
        document.getElementById('conta-dialog-form').dataset.mode = 'default';
        document.getElementById('form-lancamento-a-vista').value = '0';
        document.getElementById('forma-pagamento-wrapper').classList.remove('hide');

        document.getElementById('form-type').value = 'create';
        document.getElementById('form-conta-id').value = '';
        document.getElementById('dialog-form-title').textContent = 'Cadastrar Conta';
        document.getElementById('form-submit-btn').textContent = 'Cadastrar';

        document.getElementById('centro').value = '';
        document.getElementById('btnSelectCentroText').textContent = '-- Selecione --';
        document.getElementById('sempre-dia-x').innerHTML = 'sempre no dia __';

        if (typeof triggerSearch === 'function') {
            triggerSearch('');
        }
        if (typeof resetAvisoVencimento === 'function') {
            resetAvisoVencimento();
        }

        document.getElementById('input-banco').classList.remove('locked');
        document.getElementById('input-banco').classList.add('hide');
        document.getElementById('input-banco').style.display = 'none';
        document.getElementById('btn-parcelado').classList.remove('locked-disabled');

        $('#fornecedor').val('-1').trigger('change');
        $('#natureza').val('-1').trigger('change');
        $('#banco').val('-1').trigger('change');

        paymentMethod('a vista');

        const quickWrap = document.getElementById('date-quick-buttons');
        if (quickWrap && !quickWrap.classList.contains('hide') && typeof initQuickDateButtons === 'function') {
            initQuickDateButtons();
        }

        checkIfShouldShowBankAccounts();

        document.getElementById('input-banco').classList.add('hide');
        document.getElementById('input-banco').style.display = 'none';
        document.getElementById('labelDataParcelaAVista').textContent = 'Data de vencimento:';
    }

    document.getElementById('conta-dialog-form').addEventListener('close', resetContaForm);

    $(document).ready(function() {
        paymentMethod(document.querySelector("input[name=forma-pagamento]").value);
        checkIfShouldShowBankAccounts();
        if (IS_NORMAL_USER && !CAN_CREATE_FUTURE_BILLS) initQuickDateButtons();
        <?php if (isset($_SESSION['post_data'])): ?>
            openContaForm(<?= ($postData['lancamento_a_vista'] ?? '0') === '1' ? "{ mode: 'cash' }" : '' ?>);
        <?php endif; ?>
    });

    document.querySelector('#conta-dialog-form form').addEventListener('submit', function(e) {
        const isLancamentoAVista = document.getElementById('form-lancamento-a-vista').value === '1';

        if (isLancamentoAVista) {
            document.getElementById('forma-pagamento').value = 'a vista';
            const bancoSelect = document.getElementById('banco');
            if (!bancoSelect.value || bancoSelect.value === '-1') {
                e.preventDefault();
                alert('Selecione uma conta bancária para o lançamento à vista.');
                if ($(bancoSelect).data('select2')) {
                    $(bancoSelect).select2('open');
                } else {
                    bancoSelect.focus();
                }
                return;
            }
        }

        const formaPagamento = document.querySelector('#conta-dialog-form input[name="forma-pagamento"]').value;
        const valorInput = document.getElementById('valor_em_reais');
        const historico = document.getElementById('historico');
        const naturezaSelect = document.getElementById('natureza');
        const centroSelect = document.getElementById('centro');

        if (parseInt(valorInput.value.replaceAll(/\.|,|R\$ /g, '')) <= 0) {
            e.preventDefault();
            alert('O valor da conta deve ser maior que zero');
            valorInput.focus();
            return;
        }

        if (historico.value.length > 400) {
            e.preventDefault();
            alert('O histórico não pode exceder 400 caracteres.');
            historico.focus();
            return;
        }

        if (formaPagamento == 'parcelado') {
            let tbl = document.getElementById('installments');
            for (let i = 1; i < tbl.children.length; i++) {
                let venc = document.getElementById('parcela' + i + '_vencimento');
                if (venc && venc.value == '') {
                    e.preventDefault();
                    alert('Todas as parcelas devem ter seus vencimentos preenchidos.');
                    venc.focus();
                    return;
                }
            }
            if (avisarVencimentosForaDaJanela(formaPagamento)) {
                e.preventDefault();
                return;
            }
            if (!somaDasParcelasIgualValorTotal) {
                e.preventDefault();
                let sum = 0;
                for (let i = 1; i < tbl.children.length; i++) {
                    let valorInput = document.getElementById('parcela' + i + '_valor');
                    if (!valorInput) continue;
                    sum += parseInt(valorInput.value.replaceAll(/\.|,|R\$ /g, ""));
                }
                let total = parseInt(valorInput.value.replaceAll(/\.|,|R\$ /g, ""));
                let diff = Math.abs(total - sum);
                let diffStr = new Intl.NumberFormat("pt-BR", { minimumFractionDigits: 2 }).format(diff / 100);
                let msg = "Soma das parcelas: R$ " + new Intl.NumberFormat("pt-BR", { minimumFractionDigits: 2 }).format(sum / 100) + "\n";
                msg += "Valor total: R$ " + new Intl.NumberFormat("pt-BR", { minimumFractionDigits: 2 }).format(total / 100) + "\n\n";
                if (sum > total) {
                    msg += "Estão sobrando R$ " + diffStr + ".";
                } else {
                    msg += "Estão faltando R$ " + diffStr + ".";
                }
                msg += "\n\nDeseja atualizar o valor total para R$ " + new Intl.NumberFormat("pt-BR", { minimumFractionDigits: 2 }).format(sum / 100) + "?";

                const d = document.createElement("dialog");
                d.style.cssText = "border:none;border-radius:8px;padding:0;max-width:440px;width:90%;box-shadow:0 4px 20px rgba(0,0,0,.3);";
                const wrapper = document.createElement("div");
                wrapper.style.cssText = "padding:1.5rem;display:flex;flex-direction:column;gap:1rem;";
                const h = document.createElement("h3");
                h.textContent = "Valores não coincidem";
                h.style.margin = "0";
                wrapper.appendChild(h);
                const p = document.createElement("p");
                p.textContent = msg;
                p.style.margin = "0";
                p.style.whiteSpace = "pre-line";
                wrapper.appendChild(p);
                const btnRow = document.createElement("div");
                btnRow.style.cssText = "display:flex;gap:0.5rem;justify-content:flex-end;margin-top:0.5rem;";
                const btnYes = document.createElement("button");
                btnYes.type = "button";
                btnYes.textContent = "Sim, atualizar";
                btnYes.className = "btn btn-success";
                btnYes.addEventListener("click", () => {
                    d.close(); d.remove();
                    valorInput.value = "R$ " + new Intl.NumberFormat("pt-BR", { minimumFractionDigits: 2 }).format(sum / 100);
                    document.querySelector('#conta-dialog-form form').submit();
                });
                const btnNo = document.createElement("button");
                btnNo.type = "button";
                btnNo.textContent = "Não, manter";
                btnNo.className = "btn btn-secondary";
                btnNo.addEventListener("click", () => { d.close(); d.remove(); });
                btnRow.appendChild(btnYes);
                btnRow.appendChild(btnNo);
                wrapper.appendChild(btnRow);
                d.appendChild(wrapper);
                document.body.appendChild(d);
                d.showModal();
                return;
            }
        }

        if (formaPagamento == 'a vista') {
            if (document.getElementById('dataParcelaAVista').value == '') {
                e.preventDefault();
                alert('Você deve informar a data de pagamento desta conta');
                document.getElementById('dataParcelaAVista').focus();
                return;
            }
            if (avisarVencimentosForaDaJanela(formaPagamento)) {
                e.preventDefault();
                return;
            }
        }

        if (naturezaSelect.value == '-1') {
            e.preventDefault();
            alert('Selecione uma natureza');
            naturezaSelect.focus();
            return;
        }

        if (centroSelect.value == '') {
            e.preventDefault();
            alert('Selecione um centro de custo');
            document.getElementById('btnSelectCentro').focus();
            return;
        }
    });
</script>
<?php unset($_SESSION['post_data']); ?>

<style>
    .btn-forma-pagamento.btn {
        border: 1.5px solid black;
    }

    .btn-forma-pagamento.btn:active {
        transform: translateY(1px);
    }

    .btn-forma-pagamento.btn:hover {
        background-color: rgba(90, 122, 255, 0.3);
    }

    .btn-forma-pagamento.btn:focus {
        box-shadow: inset 0px 0px 5px 2px grey;
    }

    .btn-selected {
        background-color: rgba(54, 122, 255, 0.3);
    }

    #btn-parcelado.locked-disabled {
        opacity: 0.5;
        cursor: not-allowed;
        pointer-events: none;
    }

    #date-quick-buttons:not(.hide) {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-top: 0.5rem;
    }

    .date-btn {
        display: flex;
        flex-direction: column;
        align-items: center;
        line-height: 1.2;
        padding: 0.25rem 0.5rem;
        min-width: 4rem;
        border: 1px solid #6c757d;
        border-radius: 0.25rem;
        background: transparent;
        color: #6c757d;
        cursor: pointer;
    }

    .date-btn .date-btn-day {
        font-weight: 600;
        font-size: 0.95rem;
    }

    .date-btn .date-btn-label {
        font-size: 0.65rem;
        opacity: 0.85;
    }

    .date-btn:hover {
        color: #fff;
        background-color: #6c757d;
        border-color: #6c757d;
    }

    .date-btn.selected {
        color: #fff;
        background-color: #6c757d;
        border-color: #6c757d;
        box-shadow: 0 0 0 0.25rem rgba(130, 138, 145, 0.5);
    }

    @keyframes flash-vencimento {
        0%   { background-color: #fef08a; }
        100% { background-color: transparent; }
    }

    .flash-vencimento {
        animation: flash-vencimento 0.5s ease-out;
    }

    /* O campo de banco só existe no modo "Lançamento à vista". */
    #conta-dialog-form:not([data-mode="cash"]) #input-banco {
        display: none !important;
    }

    #input-banco.locked {
        display: block !important;
    }

    #input-banco.locked .select2-container {
        pointer-events: none;
    }

    #input-banco.locked .select2-selection {
        background-color: #e9ecef !important;
        opacity: 0.6;
    }
</style>