const installments = document.getElementById("installments");
const qtd = document.getElementById("qtd_parcela");
const aCadaX = document.getElementById("a-cada-x");
const inputValorTotalConta = document.getElementById("valor_em_reais");
const initialDateAutofill = document.getElementById("initial-date");
let somaDasParcelasIgualValorTotal = false;
inputValorTotalConta.addEventListener("keyup", generateInstallments);
document.getElementById("qtd_parcela").addEventListener("keyup", generateInstallments);

function checkIfShouldShowBankAccounts() {
    const isLancamentoAVista = document.getElementById("form-lancamento-a-vista")?.value === "1";
    const bancoWrapper = document.getElementById("input-banco");
    const labelData = document.getElementById("labelDataParcelaAVista");

    if (isLancamentoAVista) {
        bancoWrapper.classList.remove("hide");
        labelData.textContent = "Data de pagamento:";
    } else {
        bancoWrapper.classList.add("hide");
        labelData.textContent = "Data de vencimento:";
    }
}

function paymentMethod(method) {
    if (document.getElementById("form-lancamento-a-vista").value === "1") {
        method = "a vista";
    }

    document.getElementById("forma-pagamento").value = method;

    if (method == "a vista") {
        document.getElementById("quantidade-parcelas").classList.add("hide");
        document.getElementById("secao-parcelas").classList.add("hide");
        document.getElementById("input-parcela-a-vista").classList.remove("hide");
        document.getElementById("qtd_parcela").value = 1;
        generateInstallments();
        // document.getElementById("dataParcelaAVista").focus();
        document.getElementById("check-avista").style.color = "black";
        document.getElementById("check-parcelado").style.color = "white";
        document.getElementById("btn-parcelado").classList.remove("btn-selected");
        document.getElementById("btn-avista").classList.add("btn-selected");
        checkIfShouldShowBankAccounts();
    }

    if (method == "parcelado") {
        document.getElementById("quantidade-parcelas").classList.remove("hide");
        document.getElementById("secao-parcelas").classList.remove("hide");
        document.getElementById("input-parcela-a-vista").classList.add("hide");
        document.getElementById("input-banco").classList.add("hide");
        generateInstallments();
        qtd.focus();
        document.getElementById("check-parcelado").style.color = "black";
        document.getElementById("check-avista").style.color = "white";
        document.getElementById("btn-avista").classList.remove("btn-selected");
        document.getElementById("btn-parcelado").classList.add("btn-selected");
    }
}

function updateButtonSempreDiaX() {
    let input = initialDateAutofill;
    let btn = document.getElementById("sempre-dia-x");

    dateFromInput = input.value.split("-");
    year = dateFromInput[0];
    month = dateFromInput[1];
    day = dateFromInput[2];

    date = new Date(year, month - 1, day).toISOString().split("T")[0];
    btn.innerHTML = "sempre no dia " + day;
}

function dateAutoFill(n, unit = "dia") {
    if (initialDateAutofill.value == "") {
        alert("É necessário preencher a data da primeira parcela antes de utilizar o preenchedor automático de datas.");
        initialDateAutofill.focus();
        return;
    }

    let dias = 0;
    let meses = 0;
    let anos = 0;

    switch (unit) {
        case "dia":
            dias = n;
            break;
        case "mes":
            meses = n;
            break;
        case "ano":
            anos = n;
            break;
        case "alwaysOnDayX":
            meses = 1;
            break;
    }

    let parcelas = document.getElementById("installments").children;

    let date = initialDateAutofill.value.split("-");

    for (let i = 0; i < parcelas.length - 1; i++) {

        let input = document.getElementById("parcela" + (i + 1) + "_vencimento");
        if (!input) continue;

        let year = parseInt(date[0]) + (anos * i);
        let month = parseInt(date[1]) - 1 + (meses * i);
        let day = parseInt(date[2]) + (dias * i);

        d = new Date(year, month, day).toISOString().split("T")[0];
        input.value = d;
        input.classList.remove("flash-vencimento");
        void input.offsetWidth;
        input.classList.add("flash-vencimento");
    }
}

function sumInstallments() {

    let sum = 0;

    $('.money').maskMoney({
        prefix: 'R$ '
    }).trigger('mask.maskMoney');

    for (let i = 1; i < installments.children.length; i++) {
        let valorInput = document.getElementById("parcela" + i + "_valor");
        if (!valorInput) continue;

        let maskedMoney = valorInput.value;

        let unmaskedCents = maskedMoney.replaceAll(/\.|,|R\$ /g, "");

        sum += parseInt(unmaskedCents);
    }

    const valorTotal = document.getElementById("valor_em_reais").value.replaceAll(/\.|,|R\$ /g, "");
    let diferenca = valorTotal - sum;
    let str = "";
    somaDasParcelasIgualValorTotal = false;
    if (diferenca > 0) {
        str = "&nbsp; &nbsp; (R$ " + new Intl.NumberFormat("pt-BR", { minimumFractionDigits: 2 }).format(diferenca / 100) + " faltando)";
    } else if (diferenca < 0) {
        str = "&nbsp; &nbsp; (R$ " + new Intl.NumberFormat("pt-BR", { minimumFractionDigits: 2 }).format(Math.abs(diferenca) / 100) + " sobrando)";
    } else {
        somaDasParcelasIgualValorTotal = true;
    }

    document.getElementById("centavos-faltando").innerHTML = "Soma das parcelas: R$ " + new Intl.NumberFormat("pt-BR", { minimumFractionDigits: 2 }).format(sum / 100) + "<span style='color: #f00'>" + str + "</span>";

    $('.money').maskMoney({
        prefix: 'R$ '
    }).trigger('mask.maskMoney');
}

function getMinVencimentoNormalUser() {
    if (typeof IS_NORMAL_USER === "undefined" || !IS_NORMAL_USER) return "";

    const hoje = document.getElementById("today")?.value;
    if (!hoje) return "";

    const [ano, mes, dia] = hoje.split("-").map(Number);
    const limite = new Date(ano, mes - 1, dia - 3);
    const limiteAno = limite.getFullYear();
    const limiteMes = String(limite.getMonth() + 1).padStart(2, "0");
    const limiteDia = String(limite.getDate()).padStart(2, "0");

    return `${limiteAno}-${limiteMes}-${limiteDia}`;
}

function generateInstallments() {

    if (qtd.value > 1) {
        document.getElementById("auto-dates").style.display = "block";
    } else {
        document.getElementById("auto-dates").style.display = "none";
    }

    let total = parseInt(qtd.value) || 1;

    if (total > 300) {
        alert("O limite de parcelas é 300");
        qtd.value = 300;
        total = 300;
    }

    const valorTotalCentavos = parseInt(document.getElementById("valor_em_reais").value.replaceAll(/\.|,|R\$ /g, ""));
    const parcelaBase = Math.floor(valorTotalCentavos / total);
    const resto = valorTotalCentavos - parcelaBase * total;
    const valorParcela = (parcelaBase / 100).toFixed(2);
    const primeiraParcela = ((parcelaBase + resto) / 100).toFixed(2);
    const minVencimento = getMinVencimentoNormalUser();

    let dataMap = {};
    for (let i = 1; i < installments.children.length; i++) {
        let input = document.getElementById("parcela" + i + "_vencimento");
        if (input && input.value) {
            dataMap[i] = input.value;
        }
    }

    let head = document.createElement("tr");
    let parcela = document.createElement("th");
    let vencimento = document.createElement("th");
    let valor = document.createElement("th");

    parcela.textContent = "#";
    vencimento.textContent = "Vencimento";
    valor.textContent = "Valor";

    head.appendChild(parcela);
    head.appendChild(vencimento);
    head.appendChild(valor);

    const novasParcelas = [head];

    for (let i = 1; i <= total; i++) {
        let installment = document.createElement("tr");
        let v = (i === 1) ? primeiraParcela : valorParcela;

        installment.innerHTML = `
            <td>${i}ª</td>
            <td>
                <input type="date" id="parcela${i}_vencimento" name="parcela${i}_vencimento" value="${dataMap[i] || ''}"${minVencimento ? ` min="${minVencimento}"` : ''}>
            </td>
            <td>
                <input
                    type="text"
                    id="parcela${i}_valor"
                    name="parcela${i}_valor"
                    value="${v}"
                    class="money"
                >
            </td>
        `;

        installment.id = "parcela" + i;
        installment.querySelector('input[type="text"]').addEventListener("keyup", sumInstallments);
        novasParcelas[i] = installment;
    }

    installments.replaceChildren(...novasParcelas);

    sumInstallments();
}

const DIAS_JANELA_PAGAMENTO = 3;
let vencimentoForaDaJanelaConfirmado = false;

function getHojeIso() {
    return document.getElementById("today").value;
}

function isoParaUtc(iso) {
    const [ano, mes, dia] = iso.split('-').map(Number);
    return Date.UTC(ano, mes - 1, dia);
}

function diasDeAtras(iso) {
    return Math.round((isoParaUtc(getHojeIso()) - isoParaUtc(iso)) / 86400000);
}

function formatarDataBr(iso) {
    return iso.split('-').reverse().join('/');
}

function getVencimentosForaDaJanela(formaPagamento) {
    const fora = [];

    if (formaPagamento === 'a vista') {
        const input = document.getElementById("dataParcelaAVista");
        if (input && input.value && diasDeAtras(input.value) > DIAS_JANELA_PAGAMENTO) {
            fora.push({ rotulo: input.id, data: input.value, input: input });
        }
        return fora;
    }

    for (let i = 1; i < installments.children.length; i++) {
        const input = document.getElementById("parcela" + i + "_vencimento");
        if (!input || !input.value) continue;
        if (diasDeAtras(input.value) > DIAS_JANELA_PAGAMENTO) {
            fora.push({ rotulo: i + "ª parcela", data: input.value, input: input });
        }
    }

    return fora;
}

function confirmar(titulo, mensagem, rotuloSim, onSim, rotuloNao) {
    const d = document.createElement("dialog");
    d.style.cssText = "border:none;border-radius:8px;padding:0;max-width:440px;width:90%;box-shadow:0 4px 20px rgba(0,0,0,.3);";
    const wrapper = document.createElement("div");
    wrapper.style.cssText = "padding:1.5rem;display:flex;flex-direction:column;gap:1rem;";
    const h = document.createElement("h3");
    h.textContent = titulo;
    h.style.margin = "0";
    wrapper.appendChild(h);
    const p = document.createElement("p");
    p.textContent = mensagem;
    p.style.margin = "0";
    p.style.whiteSpace = "pre-line";
    wrapper.appendChild(p);
    const btnRow = document.createElement("div");
    btnRow.style.cssText = "display:flex;gap:.5rem;justify-content:flex-end;margin-top:.5rem;";
    const btnYes = document.createElement("button");
    btnYes.type = "button";
    btnYes.textContent = rotuloSim;
    btnYes.className = "btn btn-success";
    btnYes.addEventListener("click", () => { d.close(); d.remove(); onSim(); });
    const btnNo = document.createElement("button");
    btnNo.type = "button";
    btnNo.textContent = rotuloNao;
    btnNo.className = "btn btn-secondary";
    btnNo.addEventListener("click", () => { d.close(); d.remove(); });
    btnRow.appendChild(btnYes);
    btnRow.appendChild(btnNo);
    wrapper.appendChild(btnRow);
    d.appendChild(wrapper);
    document.body.appendChild(d);
    d.showModal();
}

function avisarVencimentosForaDaJanela(formaPagamento) {
    if (vencimentoForaDaJanelaConfirmado) return false;

    const fora = getVencimentosForaDaJanela(formaPagamento);
    if (fora.length === 0) return false;

    fora.forEach(v => {
        v.input.classList.remove("flash-vencimento");
        void v.input.offsetWidth;
        v.input.classList.add("flash-vencimento");
    });

    const maisAntiga = fora.reduce((a, b) => a.data < b.data ? a : b);
    const lista = fora.slice(0, 5).map(v => `• ${v.rotulo}: ${formatarDataBr(v.data)}`).join('\n');
    const resto = fora.length > 5 ? `\n• e mais ${fora.length - 5}.` : '';
    const total = installments.children.length - 1;
    const rotulo = document.getElementById("labelDataParcelaAVista").textContent.replace(':', '').trim().toLowerCase();

    let mensagem;
    if (formaPagamento === 'a vista') {
        mensagem = `A ${rotulo} (${formatarDataBr(maisAntiga.data)}) está mais de ${DIAS_JANELA_PAGAMENTO} dias no passado.`;
    } else {
        mensagem = `${fora.length} das ${total} parcelas estão com vencimento mais de ${DIAS_JANELA_PAGAMENTO} dias no passado.\n\n${lista}${resto}\n\nA mais antiga é ${formatarDataBr(maisAntiga.data)}.`;
    }

    if (typeof IS_NORMAL_USER !== 'undefined' && IS_NORMAL_USER) {
        alert(mensagem + `\n\nSó é possível informar datas de hoje até ${DIAS_JANELA_PAGAMENTO} dias atrás.`);
        return true;
    }

    mensagem += `\n\nDeseja continuar mesmo assim?`;

    confirmar('Vencimento no passado', mensagem, 'Sim, continuar', () => {
        vencimentoForaDaJanelaConfirmado = true;
        document.querySelector('#conta-dialog-form form').requestSubmit();
    }, 'Não, corrigir');

    return true;
}

function resetAvisoVencimento() {
    vencimentoForaDaJanelaConfirmado = false;
}

aCadaX.addEventListener("keyup", function () {
    if (this.value > 1) {
        document.getElementById("opt-dia").setAttribute("label", "dias");
        document.getElementById("opt-mes").setAttribute("label", "meses");
        document.getElementById("opt-ano").setAttribute("label", "anos");
    } else if (this.value <= 1) {
        document.getElementById("opt-dia").setAttribute("label", "dia");
        document.getElementById("opt-mes").setAttribute("label", "mês");
        document.getElementById("opt-ano").setAttribute("label", "ano");
    }
});

document.getElementById("customPace").addEventListener("click", () => {
    dateAutoFill(aCadaX.value, document.getElementById("unit").value);
});


$(document).ready(function () {
    paymentMethod(document.querySelector('input[name="forma-pagamento"]').value);
});
