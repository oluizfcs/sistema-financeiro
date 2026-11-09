const installmentsDialog = document.getElementById("installments-dialog");
const installmentPayForm = installmentsDialog.querySelector("form");
const bancoSelect = installmentPayForm.querySelector("select[name=banco]");
const dateInput = installmentPayForm.querySelector("input[type=date]");
const valorInput = installmentPayForm.querySelector("input[name=valor]");
const valorParcialInput = document.getElementById("valor_parcial");
const valorParcialWrapper = document.getElementById("input-valor-parcial-wrapper");
const confirmParcialBtn = document.getElementById("confirm-parcial-btn");
const payAllBtn = document.getElementById("pay-all-btn");
const pagamentoParcialCheckbox = document.getElementById("pagamento_parcial");
const payStatus = document.getElementById("pay-status");
const inputJurosAtraso = installmentPayForm.querySelector("input[name=juros_atraso]");
const inputJurosWrapper = document.getElementById("input-juros-wrapper");

let lastClickedTableRow = null;

const editContaBtn = document.getElementById("edit-conta");
const inativarDialog = document.getElementById("inativar-dialog");

function centavosFromMask(value) {
    const digitos = String(value || "").replace(/\D/g, "");
    return digitos === "" ? 0 : parseInt(digitos, 10);
}

function formatCentavos(centavos) {
    return (centavos / 100).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function hojeIso() {
    const d = new Date();
    const ano = d.getFullYear();
    const mes = String(d.getMonth() + 1).padStart(2, '0');
    const dia = String(d.getDate()).padStart(2, '0');
    return ano + '-' + mes + '-' + dia;
}

function rowIsPaid(row) {
    // "Paga" significa quitada. Uma parcela com pagamento parcial continua
    // com saldo e pode receber novos pagamentos.
    return row != null && row.dataset.parcial !== "1" && rowRestante(row) === 0;
}

function rowRestante(row) {
    return parseInt(row?.dataset.valorRestante, 10) || 0;
}

function currentFilterStatus() {
    return document.getElementById('filter_status')?.value || '';
}

function updateDialogParcelaResumo(row) {
    const resumo = document.getElementById('parcela-pagamento-resumo');
    const pagoEl = document.getElementById('parcela-valor-pago');
    const restanteEl = document.getElementById('parcela-valor-restante');

    if (!resumo || !pagoEl || !restanteEl || !row) {
        return;
    }

    const pago = Number(row.dataset.valorPago || 0);
    const mostrar = pago > 0;

    resumo.style.display = mostrar ? '' : 'none';

    if (mostrar) {
        pagoEl.textContent = 'R$ ' + formatCentavos(pago);
        restanteEl.textContent = 'R$ ' + formatCentavos(rowRestante(row));
    }
}

function rowValorPagamento(row) {
    // Em "pagas" cada linha é um lançamento: o valor exibido é o do próprio
    // pagamento. data-valor-pago (cache da parcela) só serve ao resumo.
    return Number(row?.dataset.valorPagamento ?? row?.dataset.valorPago ?? 0);
}

function updateRowValorCell(row) {
    const status = currentFilterStatus();
    const cell = row?.querySelector('td.column7');

    if (!cell) {
        return;
    }

    if (status === 'pagas') {
        cell.textContent = formatCentavos(rowValorPagamento(row));
    } else if (status === 'a pagar') {
        cell.textContent = formatCentavos(rowRestante(row));
    }
}

function updateFooterTotal() {
    const status = currentFilterStatus();

    if (status !== 'pagas' && status !== 'a pagar') {
        return;
    }

    const rows = Array.from(document.querySelectorAll('table tbody tr'));
    const totalCentavos = rows.reduce((total, row) => {
        return total + (status === 'pagas' ? rowValorPagamento(row) : rowRestante(row));
    }, 0);

    const qtdParcelasEl = document.getElementById('qtd-parcelas');
    const totalParcelasEl = document.getElementById('total-parcelas');

    if (qtdParcelasEl) {
        qtdParcelasEl.textContent = 'Lançamentos: ' + rows.length;
    }

    if (totalParcelasEl) {
        totalParcelasEl.textContent = 'Total R$: ' + (totalCentavos / 100).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
}

function setPayStatus(msg) {
    if (!payStatus) {
        return;
    }

    if (!msg) {
        payStatus.textContent = '';
        payStatus.style.display = 'none';
        return;
    }

    payStatus.textContent = msg;
    payStatus.style.display = 'block';
}

function refreshPayButtonLabel() {
    if (!payAllBtn || !lastClickedTableRow) {
        return;
    }

    if (rowIsPaid(lastClickedTableRow)) {
        payAllBtn.textContent = "Parcela paga";
        return;
    }

    const total = rowRestante(lastClickedTableRow) + centavosFromMask(inputJurosAtraso?.value);
    payAllBtn.textContent = 'Pagar R$ ' + formatCentavos(total);
}

function updatePayButtonWithJuros() {
    refreshPayButtonLabel();
}

function setPagamentoParcialAtivo(ativo) {
    // Pagamento parcial é a exceção: por padrão o botão paga o restante todo.
    if (!lastClickedTableRow || rowIsPaid(lastClickedTableRow)) {
        return;
    }

    if (pagamentoParcialCheckbox) {
        pagamentoParcialCheckbox.checked = Boolean(ativo);
    }
    if (valorParcialWrapper) {
        valorParcialWrapper.style.display = ativo ? 'block' : 'none';
    }
    if (payAllBtn) {
        payAllBtn.style.display = ativo ? 'none' : '';
    }
    if (!ativo && valorParcialInput) {
        valorParcialInput.value = '';
    }
    if (ativo && valorParcialInput) {
        valorParcialInput.focus();
    }
}

if (pagamentoParcialCheckbox) {
    pagamentoParcialCheckbox.addEventListener("change", function () {
        setPagamentoParcialAtivo(pagamentoParcialCheckbox.checked);
    });
}

if (inputJurosAtraso) {
    inputJurosAtraso.addEventListener("keyup", updatePayButtonWithJuros);
    inputJurosAtraso.addEventListener("change", updatePayButtonWithJuros);
}

function resetPaymentFormState() {
    setPayStatus('');
    setPagamentoParcialAtivo(false);

    if (valorParcialInput) {
        valorParcialInput.value = '';
    }
    if (valorInput) {
        valorInput.value = '';
    }
    if (inputJurosAtraso) {
        inputJurosAtraso.value = '';
    }

    if (inputJurosWrapper && inputJurosAtraso && lastClickedTableRow) {
        const dataVencimento = new Date(lastClickedTableRow.dataset.vencimento + 'T00:00');
        const hoje = new Date();
        hoje.setHours(0, 0, 0, 0);

        inputJurosWrapper.style.display = hoje > dataVencimento ? "block" : "none";
    }
}

function validatePayment() {
    if (bancoSelect.value == 0) {
        alert("Por favor informe a conta bancária onde o valor será descontado");
        bancoSelect.focus();
        return false;
    }

    if (dateInput.value == "") {
        alert("Por favor informe a data de pagamento");
        dateInput.focus();
        return false;
    }

    const dataSelecionada = new Date(dateInput.value + 'T00:00');
    const hoje = new Date();
    hoje.setHours(0, 0, 0, 0);

    const tresDiasAtras = new Date();
    tresDiasAtras.setDate(hoje.getDate() - 3);
    tresDiasAtras.setHours(0, 0, 0, 0);

    const isNormalUser = typeof IS_NORMAL_USER !== 'undefined' ? IS_NORMAL_USER : false;

    if (isNormalUser && (dataSelecionada > hoje || dataSelecionada < tresDiasAtras)) {
        alert("Só é possível informar datas de pagamentos de hoje até três dias atrás");
        dateInput.focus();
        return false;
    }

    if (!lastClickedTableRow) {
        return false;
    }

    return true;
}

let paymentSubmitting = false;

function setSubmitting(submitting) {
    paymentSubmitting = submitting;

    if (payAllBtn) {
        payAllBtn.disabled = submitting;
    }
    if (pagamentoParcialCheckbox) {
        pagamentoParcialCheckbox.disabled = submitting;
    }
    if (confirmParcialBtn) {
        confirmParcialBtn.disabled = submitting;
    }
}

async function submitPayment() {
    if (!lastClickedTableRow || paymentSubmitting) {
        return;
    }

    paymentSubmitting = true;
    setPayStatus('');
    setSubmitting(true);

    const formData = new FormData(installmentPayForm);

    try {
        const response = await fetch('/contas', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (!response.ok || data.status !== 'success') {
            alert(data.msg || 'Erro ao pagar parcela.');
            setSubmitting(false);
            return;
        }

        applyPaymentResult(data.parcela, data.msg);
    } catch (e) {
        alert('Erro ao pagar parcela.');
        setSubmitting(false);
    }
}

function refreshListAfterPayment(parcela) {
    // Em "pagas" cada linha é um lançamento: depois de pagar, recarregue a
    // lista para o novo pagamento entrar como linha própria.
    const status = currentFilterStatus();
    if (status !== 'pagas' || typeof fetchParcelas !== 'function') {
        return;
    }

    const parcelaId = String(parcela.id);
    const refetch = fetchParcelas();

    if (refetch && typeof refetch.then === 'function') {
        refetch.then(function () { repointToFreshestRow(parcelaId); }).catch(function () {});
    }
}

function repointToFreshestRow(parcelaId) {
    const rows = Array.from(document.querySelectorAll('table tbody tr'))
        .filter(function (row) { return row.dataset.parcelaId === parcelaId; });

    if (rows.length === 0) {
        return;
    }

    lastClickedTableRow = rows.sort(function (a, b) {
        return String(b.dataset.pagamento || '').localeCompare(String(a.dataset.pagamento || ''));
    })[0];

    applyDialogPaymentFormStyleBasedOnInstallmentPaymentStatus();
}

function applyPaymentResult(parcela, msg) {
    const row = lastClickedTableRow;

    row.dataset.valorPago = String(parcela.valor_pago_em_centavos);
    row.dataset.valorPagamento = String(parcela.total_pago_em_centavos);
    row.dataset.valorRestante = String(parcela.valor_restante_em_centavos);
    row.dataset.parcial = parcela.parcial ? '1' : '0';
    row.dataset.bancoId = parcela.banco_id != null ? String(parcela.banco_id) : '';

    if (parcela.data_pagamento) {
        row.dataset.pagamento = parcela.data_pagamento;

        const statusAtual = document.getElementById('filter_status')?.value;
        const dateCell = row.querySelector('td.column1');
        if (statusAtual === 'pagas' && dateCell) {
            const [ano, mes, dia] = parcela.data_pagamento.split('-');
            dateCell.textContent = `${dia}/${mes}/${ano}`;
        }
    }

    if (parcela.paid) {
        row.classList.add('paid');
    }

    updateRowParcelaCell(row);
    updateRowValorCell(row);
    updateFooterTotal();
    setSubmitting(false);
    applyDialogPaymentFormStyleBasedOnInstallmentPaymentStatus();
    refreshListAfterPayment(parcela);

    if (parcela.paid) {
        return;
    }

    setPagamentoParcialAtivo(false);
    if (valorParcialInput) {
        valorParcialInput.value = '';
    }
    if (valorInput) {
        valorInput.value = '';
    }
    if (inputJurosAtraso) {
        inputJurosAtraso.value = '';
    }

    setPayStatus((msg || 'Pagamento parcial registrado.') + ' Restam R$ ' + formatCentavos(parcela.valor_restante_em_centavos) + '.');
    bancoSelect.value = 0;
    refreshPayButtonLabel();
}

function updateRowParcelaCell(row) {
    const cell = row.querySelector('td.column6');
    if (!cell) {
        return;
    }

    const qtd = row.dataset.qtdParcela || '';
    const parcial = row.dataset.parcial === '1';

    cell.innerHTML = qtd + (parcial
        ? ' <span class="parcial-badge" title="Pagamento parcial">P</span>'
        : '');
}

function pagarTudo() {
    if (!validatePayment()) {
        return;
    }

    // Sem valor informado o servidor entende "pagar o restante da parcela".
    if (valorParcialInput) {
        valorParcialInput.value = '';
    }
    valorInput.value = '';
    submitPayment();
}

function confirmarPagamentoParcial() {
    if (!lastClickedTableRow) {
        return;
    }

    const centavos = centavosFromMask(valorParcialInput?.value);
    const restante = rowRestante(lastClickedTableRow);

    if (centavos <= 0) {
        alert("Informe um valor maior que zero.");
        valorParcialInput?.focus();
        return;
    }

    if (centavos > restante) {
        alert("O valor não pode ser maior que o restante da parcela (R$ " + formatCentavos(restante) + ").");
        valorParcialInput?.focus();
        return;
    }

    if (!validatePayment()) {
        return;
    }

    // Money::reais_para_centavos() no servidor só usa os dígitos, então enviar
    // "1234.56" resulta exatamente em 123456 centavos.
    valorInput.value = (centavos / 100).toFixed(2);
    submitPayment();
}

if (installmentPayForm) {
    installmentPayForm.addEventListener("submit", function (e) {
        e.preventDefault();

        if (pagamentoParcialCheckbox && pagamentoParcialCheckbox.checked) {
            confirmarPagamentoParcial();
            return;
        }

        pagarTudo();
    });
}

if (editContaBtn) {
    editContaBtn.addEventListener("click", function () {
        const contaId = installmentsDialog.querySelector("input[name=conta_id]").value;
        installmentsDialog.close();

        fetch('/contas/detalhar/' + contaId + '?format=json')
            .then(r => r.json())
            .then(data => {
                const parcelasComPagamento = data.parcelas.filter(p => (p.valor_pago_em_centavos || 0) > 0);
                if (parcelasComPagamento.length === 0) {
                    if (typeof openEditContaForm === "function") {
                        openEditContaForm(contaId);
                    }
                    return;
                }

                const pagamentos = parcelasComPagamento.flatMap(p => p.pagamentos || []);

                let total = 0;
                let bancos = [];

                if (pagamentos.length > 0) {
                    total = pagamentos.reduce((s, p) => s + (p.total_em_centavos ?? 0), 0);
                    bancos = [...new Set(pagamentos.map(p => p.banco_nome).filter(Boolean))];
                } else {
                    // Compatibilidade com o formato antigo: sem o livro caixa no JSON.
                    total = parcelasComPagamento.reduce((s, p) =>
                        s + (p.valor_pago_em_centavos || (p.valor_em_centavos + (p.juros_em_centavos || 0))), 0);
                    bancos = [...new Set(parcelasComPagamento.map(p => p.banco_nome).filter(Boolean))];
                }

                const valorFormatado = (total / 100).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

                const count = parcelasComPagamento.length;
                const parcelaLabel = count === 1 ? "parcela com pagamento" : "parcelas com pagamento";
                const vinculadaParcela = count === 1 ? "Vinculada" : "Vinculadas";
                const bancoLabel = bancos.length === 1 ? "ao banco" : "aos bancos";
                const vinculadaLabel = vinculadaParcela + " " + bancoLabel;

                document.getElementById("inativar-dialog-details").innerHTML =
                    "<strong>" + count + " " + parcelaLabel + ":</strong> " + valorFormatado +
                    "<br>" + vinculadaLabel + ": " + (bancos.join(", ") || '-');

                inativarDialog.showModal();

                const estornoBtn = document.getElementById("estorno-btn");
                const newEstornoBtn = estornoBtn.cloneNode(true);
                estornoBtn.parentNode.replaceChild(newEstornoBtn, estornoBtn);

                newEstornoBtn.addEventListener("click", function () {
                    const formData = new FormData();
                    formData.append("type", "estorno");
                    formData.append("conta_id", contaId);

                    fetch('/contas', { method: 'POST', body: formData })
                        .then(r => r.json())
                        .then(result => {
                            if (!result.ok) {
                                alert(result.msg);
                                return;
                            }
                            inativarDialog.close();
                            openEditContaForm(contaId);
                        })
                        .catch(() => alert("Erro ao estornar os pagamentos."));
                });
            })
            .catch(() => alert("Erro ao carregar os pagamentos da conta."));
    });
}

document.querySelector("table tbody")?.addEventListener("click", (e) => {
    const row = e.target.closest("tr");
    const dataset = row?.dataset;

    if (!dataset || dataset.parcelaId == null || dataset.contaId == null) {
        return;
    }

    lastClickedTableRow = row;

    installmentsDialog.querySelector("input[name=conta_id]").value = dataset.contaId;
    installmentsDialog.querySelector("input[name=numero_parcela]").value = dataset.numeroParcela;
    installmentsDialog.querySelector("a").href = "/contas/detalhar/" + dataset.contaId;

    dateInput.value = rowIsPaid(row) ? (dataset.pagamento || hojeIso()) : hojeIso();

    document.getElementById("qtd-parcela-dialog").textContent = "Parcela " + dataset.qtdParcela + " (R$ " + dataset.valor + ")";
    document.getElementById("parcela-historico").textContent = dataset.historico;

    resetPaymentFormState();
    applyDialogPaymentFormStyleBasedOnInstallmentPaymentStatus();

    installmentsDialog.showModal();

    if (typeof highlightSelectedPayDateButton === 'function') {
        highlightSelectedPayDateButton();
    }
});

function applyDialogPaymentFormStyleBasedOnInstallmentPaymentStatus() {
    const payQuickButtons = document.querySelectorAll('#pay-date-quick-buttons .date-btn');

    if (!lastClickedTableRow) {
        return;
    }

    updateDialogParcelaResumo(lastClickedTableRow);

    if (rowIsPaid(lastClickedTableRow)) {
        dateInput.disabled = true;
        bancoSelect.disabled = true;
        bancoSelect.value = lastClickedTableRow.dataset.bancoId;
        payQuickButtons.forEach(function (btn) { btn.disabled = true; });

        if (payAllBtn) {
            payAllBtn.disabled = true;
            payAllBtn.classList.remove("btn-primary");
            payAllBtn.classList.add("btn-success");
            payAllBtn.textContent = "Parcela paga com sucesso!";
            payAllBtn.style.display = '';
        }

        if (pagamentoParcialCheckbox) {
            pagamentoParcialCheckbox.checked = false;
            pagamentoParcialCheckbox.disabled = true;
        }

        if (inputJurosWrapper) {
            inputJurosWrapper.style.display = "none";
        }
        if (inputJurosAtraso) {
            inputJurosAtraso.disabled = true;
        }
        if (valorParcialWrapper) {
            valorParcialWrapper.style.display = "none";
        }
        return;
    }

    dateInput.disabled = false;
    bancoSelect.disabled = false;
    if (inputJurosAtraso) {
        inputJurosAtraso.disabled = false;
    }
    payQuickButtons.forEach(function (btn) { btn.disabled = false; });

    if (pagamentoParcialCheckbox) {
        pagamentoParcialCheckbox.disabled = false;
        pagamentoParcialCheckbox.checked = false;
    }
    if (valorParcialWrapper) {
        valorParcialWrapper.style.display = 'none';
    }

    if (payAllBtn) {
        payAllBtn.disabled = false;
        payAllBtn.classList.remove("btn-success");
        payAllBtn.classList.add("btn-primary");
        payAllBtn.style.display = '';
    }

    refreshPayButtonLabel();
}
