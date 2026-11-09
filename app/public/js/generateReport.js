const REPORT_STAGES = [
    "Consultando dados…",
    "Aplicando filtros…",
    "Montando a tabela…",
    "Ordenando linhas…",
    "Calculando total…",
    "Quase lá…"
];

let reportTimer = null;
let reportStageTimer = null;
let reportStartTime = 0;

function openModal() {
    document.getElementById("generatingPdfModal").style.display = "block";

    reportStartTime = Date.now();
    updateElapsed();

    if (reportTimer) clearInterval(reportTimer);
    reportTimer = setInterval(updateElapsed, 1200);

    const stage = document.getElementById("reportStage");
    if (stage) stage.textContent = REPORT_STAGES[0];

    if (reportStageTimer) clearInterval(reportStageTimer);
    let i = 0;
    reportStageTimer = setInterval(() => {
        i = (i + 1) % REPORT_STAGES.length;
        if (stage) stage.textContent = REPORT_STAGES[i];
    }, 3000);
}

function updateElapsed() {
    const el = document.getElementById("reportElapsed");
    if (!el) return;
    const seconds = Math.max(1, Math.floor((Date.now() - reportStartTime) / 1000));
    el.textContent = "Gerando… " + seconds + "s";
}

function closeModal() {
    document.getElementById("generatingPdfModal").style.display = "none";

    if (reportTimer) clearInterval(reportTimer);
    if (reportStageTimer) clearInterval(reportStageTimer);
    reportTimer = null;
    reportStageTimer = null;

    const el = document.getElementById("reportElapsed");
    if (el) el.textContent = "";
}

function toggleReportMenu() {
    const menu = document.getElementById('report-menu');
    menu.style.display = menu.style.display === 'none' ? '' : 'none';
}

function closeReportMenu() {
    const menu = document.getElementById('report-menu');
    if (menu) {
        menu.style.display = 'none';
    }
}

async function gerarRelatorioBancario() {
    closeReportMenu();

    const bancoEl = document.getElementById('banco_report_id');
    if (!bancoEl) return;

    openModal();

    const formData = new FormData();
    formData.append('report_type', 'bancoMovimentos');
    formData.append('banco_id', bancoEl.value);
    formData.append('start_date', document.getElementById('banco_start_date').value);
    formData.append('end_date', document.getElementById('banco_end_date').value);

    const orientationRadio = document.querySelector('input[name="report_orientation"]:checked');
    formData.append('orientation', orientationRadio ? orientationRadio.value : 'landscape');

    try {
        const response = await fetch(document.body.dataset.baseurl + '/relatorios', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });

        if (!response.ok) {
            const err = await response.json().catch(() => ({error: 'Erro ao gerar relatório'}));
            alert(err.error || "Não foi possível gerar o relatório.");
            return;
        }

        const buffer = await response.arrayBuffer();
        const isPdf = String.fromCharCode.apply(null, new Uint8Array(buffer, 0, 5)).trim() === '%PDF-';

        console.log(isPdf);
        if (!isPdf) {
            // Try to parse as error JSON
            const text = new TextDecoder().decode(buffer);
            try {
                const err = JSON.parse(text);
                alert(err.error || "Não foi possível gerar o relatório.");
            } catch {
                alert("Não foi possível gerar o relatório. Tente um período menor ou ajuste os filtros.");
            }
            return;
        }

        const blob = new Blob([buffer], { type: "application/pdf" });

        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = "extrato_" + formData.get("start_date") + "_" + formData.get("end_date");
        link.target = '_blank';

        document.body.append(link);
        link.click();
        link.remove();

        setTimeout(() => URL.revokeObjectURL(link.href), 100);
    } catch (error) {
        alert("Houve um erro ao gerar PDF. " + error.message);
    } finally {
        closeModal();
    }
}

async function gerarRelatorio(tipo = 'analitico', mostrarSubcentros = false) {
    closeReportMenu();

    if (document.getElementById('filter_status').value === 'inativadas') {
        alert("Não é possível gerar relatório de contas inativadas.");
        return;
    }

    openModal();

    const formData = new FormData();
    formData.append('report_style', tipo);
    formData.append('start_date', document.getElementById('filter_start_date').value);
    formData.append('end_date', document.getElementById('filter_end_date').value);
    formData.append('status', document.getElementById('filter_status').value);
    formData.append('natureza', document.getElementById('filter_natureza').value);
    formData.append('centro', document.getElementById('filter_centro').value);
    formData.append('fornecedor', document.getElementById('filter_fornecedor').value);

    if (mostrarSubcentros) {
        formData.append('show_subcentros', '1');
    }

    const orientationRadio = document.querySelector('input[name="report_orientation"]:checked');
    formData.append('orientation', orientationRadio ? orientationRadio.value : 'landscape');

    const reportSource = document.querySelector('[name="report_source"]');
    if (reportSource) {
        formData.append('report_source', reportSource.value);
    }

    const table = document.querySelector("table");
    if (table) {
        ['filter1', 'filter2', 'filter3', 'filter4', 'filter5', 'filter6', 'filter7'].forEach(c => {
            if (table.classList.contains(c)) {
                formData.append("hidden_columns[]", c.replace('filter', 'column'));
            }
        });
    }

    try {
        const response = await fetch(document.body.dataset.baseurl + '/relatorios', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        });

        if (!response.ok) {
            const err = await response.json().catch(() => ({error: 'Erro ao gerar relatório'}));
            alert(err.error || "Não foi possível gerar o relatório.");
            return;
        }

        const buffer = await response.arrayBuffer();
        const isPdf = String.fromCharCode.apply(null, new Uint8Array(buffer, 0, 5)).trim() === '%PDF-';

        if (!isPdf) {
            const text = new TextDecoder().decode(buffer);
            try {
                const err = JSON.parse(text);
                alert(err.error || "Não foi possível gerar o relatório.");
            } catch {
                alert("Não foi possível gerar o relatório. Tente um período menor ou ajuste os filtros.");
            }
            return;
        }

        const blob = new Blob([buffer], { type: "application/pdf" });

        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        const prefixos = { sintetico: 'relatorio_sintetico', sintetico_geral: 'relatorio_sintetico_geral' };
        const prefixo = prefixos[tipo] || 'relatorio';
        link.download = prefixo + "_" + formData.get("start_date") + "_" + formData.get("end_date");
        link.target = '_blank';

        document.body.append(link);
        link.click();
        link.remove();

        setTimeout(() => URL.revokeObjectURL(link.href), 100);
    } catch (error) {
        alert("Houve um erro ao gerar PDF. " + error.message);
    } finally {
        closeModal();
    }
}