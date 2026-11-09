<?php use App\Controllers\Services\Money; ?>
<h1>Dashboard</h1>

<?php $temAtrasadas = $contasAtrasadas['quantidade'] > 0; ?>
<?php $temVencimentosHoje = $vencimentosHoje['quantidade'] > 0; ?>
<?php $temVencimentosSemana = $vencimentosSemana['quantidade'] > 0; ?>
<?php $tagAtrasadas = $temAtrasadas ? 'a' : 'div'; ?>
<?php $hrefAtrasadas = $temAtrasadas ? ' href="' . $_ENV['BASE_URL'] . '/contas/atrasadas"' : ''; ?>
<?php $tagHoje = $temVencimentosHoje ? 'a' : 'div'; ?>
<?php $hrefHoje = $temVencimentosHoje ? ' href="' . $_ENV['BASE_URL'] . '/contas/hoje"' : ''; ?>
<?php $tagSemana = $temVencimentosSemana ? 'a' : 'div'; ?>
<?php $hrefSemana = $temVencimentosSemana ? ' href="' . $_ENV['BASE_URL'] . '/contas/semana"' : ''; ?>
<section class="welcome-panel">
    <div class="welcome-header">
        <div>
            <h2>Seja bem-vindo(a), <?= htmlspecialchars($_SESSION['usuario_nome']) ?>!</h2>
            <p class="welcome-subtitle">Aqui está o resumo das contas da empresa.</p>
        </div>
        <span class="welcome-clock" title="Data e hora do servidor">
            <i class="fa-solid fa-clock"></i>
            <span id="server-datetime"><?= date('d/m/Y H:i:s') ?></span>
        </span>
    </div>

    <div class="stats-grid">
        <<?= $tagAtrasadas ?> class="stat-card <?= $temAtrasadas ? 'stat-danger' : 'stat-success' ?>"<?= $hrefAtrasadas ?>>
            <span class="stat-icon">
                <i class="fa-solid <?= $temAtrasadas ? 'fa-triangle-exclamation' : 'fa-circle-check' ?>"></i>
            </span>
            <span class="stat-info">
                <span class="stat-label">Contas atrasadas</span>
                <span class="stat-value"><?= $contasAtrasadas['quantidade'] ?></span>
                <span class="stat-amount">R$ <?= Money::centavos_para_reais($contasAtrasadas['total']) ?><?= $temAtrasadas ? ' em aberto' : ' — tudo em dia' ?></span>
            </span>
        </<?= $tagAtrasadas ?>>
        <<?= $tagHoje ?> class="stat-card <?= $temVencimentosHoje ? 'stat-warning' : 'stat-success' ?>"<?= $hrefHoje ?>>
            <span class="stat-icon">
                <i class="fa-solid <?= $temVencimentosHoje ? 'fa-calendar-day' : 'fa-circle-check' ?>"></i>
            </span>
            <span class="stat-info">
                <span class="stat-label">Vencimentos hoje</span>
                <span class="stat-value"><?= $vencimentosHoje['quantidade'] ?></span>
                <span class="stat-amount">R$ <?= Money::centavos_para_reais($vencimentosHoje['total']) ?><?= $temVencimentosHoje ? ' vencem hoje' : ' — nada para hoje' ?></span>
            </span>
        </<?= $tagHoje ?>>
        <<?= $tagSemana ?> class="stat-card <?= $temVencimentosSemana ? 'stat-primary' : 'stat-success' ?>"<?= $hrefSemana ?>>
            <span class="stat-icon">
                <i class="fa-solid <?= $temVencimentosSemana ? 'fa-calendar-days' : 'fa-circle-check' ?>"></i>
            </span>
            <span class="stat-info">
                <span class="stat-label">Próximos 7 dias</span>
                <span class="stat-value"><?= $vencimentosSemana['quantidade'] ?></span>
                <span class="stat-amount">R$ <?= Money::centavos_para_reais($vencimentosSemana['total']) ?><?= $temVencimentosSemana ? ' a vencer' : ' — nada a vencer' ?></span>
            </span>
        </<?= $tagSemana ?>>
    </div>
</section>

<style>
    .welcome-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.75rem 1.5rem;
    }

    .welcome-subtitle {
        margin: 0.25rem 0 0;
        color: #64748b;
        font-size: 0.9rem;
    }

    .welcome-clock {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.45rem 1rem;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 999px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
        font-size: 0.85rem;
        font-weight: 600;
        color: #334155;
        font-variant-numeric: tabular-nums;
    }

    .welcome-clock i {
        color: var(--clr-dark-blue);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 1rem;
        margin-top: 1.25rem;
    }

    .stat-card {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.1rem 1.25rem;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
        text-decoration: none;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }

    .stat-card[href]:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 16px rgba(0, 0, 0, 0.12);
    }

    .stat-icon {
        display: grid;
        place-items: center;
        width: 52px;
        height: 52px;
        border-radius: 12px;
        font-size: 1.4rem;
        flex-shrink: 0;
    }

    .stat-info {
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .stat-label {
        font-size: 0.75rem;
        font-weight: 500;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: 0.03em;
    }

    .stat-value {
        font-size: 1.6rem;
        font-weight: 700;
        line-height: 1.15;
        color: #111827;
    }

    .stat-amount {
        font-size: 0.8rem;
        color: #64748b;
    }

    .stat-danger .stat-icon { background: #fee2e2; color: #dc2626; }
    .stat-warning .stat-icon { background: #fef3c7; color: #d97706; }
    .stat-primary .stat-icon { background: hsl(214, 77%, 93%); color: hsl(214, 77%, 38%); }
    .stat-success .stat-icon { background: #dcfce7; color: #16a34a; }

    @media (max-width: 750px) {
        .stat-value {
            font-size: 1.3rem;
        }
    }
</style>

<script>
    const serverEpochMs = <?= time() ?> * 1000;
    const serverClockOffset = serverEpochMs - Date.now();
    const pad = value => String(value).padStart(2, '0');

    const serverDatetimeEl = document.getElementById('server-datetime');

    function updateServerDatetime() {
        const now = new Date(Date.now() + serverClockOffset);
        serverDatetimeEl.textContent =
            `${pad(now.getDate())}/${pad(now.getMonth() + 1)}/${now.getFullYear()} ` +
            `${pad(now.getHours())}:${pad(now.getMinutes())}:${pad(now.getSeconds())}`;
    }

    updateServerDatetime();
    setInterval(updateServerDatetime, 1000);
</script>

<?php
    if (!isAdmin())
        exit;
?>
<form method="POST" action="" class="mb-4" style="display: flex; gap: 1rem; align-items: flex-end;">
    <div class="section" style="width: 100%; display: flex; gap: 20px; flex-wrap: wrap;">
        <div>
            <label for="start_date">Data inicial: </label>
            <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>"
                class="form-control" style="margin-bottom: 0;">
        </div>
        <div>
            <label for="end_date">Data final: </label>
            <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>"
                class="form-control" style="margin-bottom: 0;">
        </div>
        <div>
            <label for="status">Situação: </label>
            <select name="status" id="status" class="form-control" style="margin-bottom: 0;">
                <option value="todas" <?= $status === 'todas' ? 'selected' : '' ?>>Todas</option>
                <option value="pagas" <?= $status === 'pagas' ? 'selected' : '' ?>>Pagas</option>
                <option value="a_pagar" <?= $status === 'a_pagar' ? 'selected' : '' ?>>A pagar</option>
            </select>
        </div>
        <div>
            <label for="natureza">Natureza: </label>
            <select name="natureza" id="natureza" class="form-control" style="margin-bottom: 0;">
            <option value="all">Todas</option>
            <?php foreach ($naturezas as $natureza): ?>
                    <?php
                    $selected = $natureza['id'] == $naturezaId ? 'selected' : '';
                    ?>
                    <option <?= $selected ?> value="<?= $natureza['id'] ?>"><?= $natureza['nome'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div style="display: flex; align-items: center; margin-bottom: 0rem;">
            <input type="checkbox" id="hide_zeros" name="hide_zeros" <?= $hideZeros ? 'checked' : '' ?>
                style="margin-right: 0.5rem; margin-bottom: 0;">
            <label for="hide_zeros" style="margin-bottom: 0;">Esconder zeros</label>
        </div>
        <div style="margin-bottom: -0.5rem; display: flex; align-items: center;">
            <button type="submit" class="btn btn-primary">Filtrar</button>
        </div>
    </div>
</form>

<?php if (count($chartData['daily']) <= 0): ?>
    <br>
    <p>Nenhuma conta encontrada
        para o período selecionado.</p>
    <?php exit; ?>
<?php endif; ?>

<div class="dashboard-grid">
    <div class="card full-width">
        <h3>Despesas por dia <?= $naturezaId != 'all' ? "($naturezasTotals)" : '' ?></h3>
        <div class="chart-container">
            <canvas id="dailyChart"></canvas>
        </div>
    </div>
    <?php if ($naturezaId == 'all'): ?>
        <div class="card full-width">
            <h3>Naturezas</h3>
            <div class="div" style="display: flex; justify-content: center; width: 100%;">
                <div class="chart-container" style="width: min(100%, 1000px);">
                    <canvas id="naturezasChart"></canvas>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <div class="card">
        <h3>Fornecedores</h3>
        <div class="chart-container">
            <canvas id="suppliersChart"></canvas>
        </div>
    </div>
    <div class="card">
        <h3>Centros de custo</h3>
        <div style="text-align: center;">
            <input type="checkbox" id="agrupado" checked name="agrupado" style="margin-right: 5px;">
            <label for="agrupado">Agrupado</label>
        </div>
        <div class="chart-container" style="position: relative; height: 396px;">
            <canvas id="costCenterChart"></canvas>
        </div>
    </div>
</div>

<div id="subCostCenterModal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); justify-content: center; align-items: center;">
    <div class="card" style="width: 90%; max-width: 600px; position: relative;">
        <span id="closeSubModal" style="position: absolute; top: -5px; right: 15px; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
        <h3 id="subModalTitle" style="margin-top: 5px;">Sub-centros</h3>
        <div class="chart-container" style="position: relative; height: 396px;">
            <canvas id="subCostCenterChart"></canvas>
        </div>
    </div>
</div>

<style>
    .dashboard-grid {
        display: grid;
        grid-template-columns: 1fr;
        gap: 1.5rem;
        margin: 1rem 0;
    }

    @media (min-width: 992px) {
        .dashboard-grid {
            grid-template-columns: 1fr 1fr;
        }

        .full-width {
            grid-column: 1 / -1;
        }
    }

    h3 {
        text-align: center;
    }

    .card {
        background: #fff;
        padding: 1.5rem;
        border-radius: 8px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
    }

    .chart-container {
        position: relative;
        height: 60vh;
        min-height: 300px;
        max-height: 400px;
        width: 100%;
    }
</style>

<script src="js/chart.js"></script>
<script>
    const startDate = document.getElementById('start_date')
    const endDate = document.getElementById('end_date')
    const startDateFormated = startDate.value.split('-').reverse().join('/');
    const endDateFormated = endDate.value.split('-').reverse().join('/');

    const chartData = <?= json_encode($chartData) ?>;

    const dailyCtx = document.getElementById('dailyChart');
    new Chart(dailyCtx, {
        type: 'line',
        data: {
            labels: chartData.daily.map(item => item.date.split('-').reverse().slice(0, 2).join('/')),
            datasets: [{
                label: 'Total R$ <?= Money::centavos_para_reais($sumDailyTotals) ?>',
                data: chartData.daily.map(item => item.total / 100),
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                fill: true,
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'R$ ' + value.toFixed(2);
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    onClick: null
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return 'R$ ' + context.formattedValue;
                        }
                    }
                }
            }
        }
    });

    const suppliersCtx = document.getElementById('suppliersChart');
    new Chart(suppliersCtx, {
        type: 'pie',
        data: {
            labels: chartData.suppliers.map(item => item.nome),
            datasets: [{
                label: 'Custo Total (R$)',
                data: chartData.suppliers.map(item => item.total / 100),
                backgroundColor: [
                    '#ffc107', '#17a2b8', '#dc3545', '#6610f2', '#fd7e14', '#20c997'
                ],
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    onClick: null
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = '';
                            const total = context.dataset.data.reduce((sum, current) => sum + current, 0);
                            if (context.parsed !== null) {
                                label += new Intl.NumberFormat('pt-BR', {
                                    style: 'currency',
                                    currency: 'BRL'
                                }).format(context.parsed) + ' — ' + ((100 * context.parsed) / total).toFixed(2) + '%';
                            }
                            return label;
                        }
                    }
                },
                title: {
                    display: true,
                    position: 'bottom',
                    text: `${startDateFormated}  -  ${endDateFormated} <?= $naturezaId != 'all' ? "— Natureza: $naturezasTotals" : '' ?>`,
                    color: "#0000dd",
                    font: {
                        weight: 'normal'
                    }
                }
            }
        }
    });

    const agrupadoEl = document.getElementById('agrupado');

    function isGrouped() {
        return agrupadoEl && agrupadoEl.checked;
    }

    const costCtx = document.getElementById('costCenterChart');
    const costChart = new Chart(costCtx, {
        type: 'pie',
        data: {
            labels: chartData.costCentersGrouped.map(item => item.nome),
            datasets: [{
                data: chartData.costCentersGrouped.map(item => item.total / 100),
                backgroundColor: [
                    '#ffc107', '#17a2b8', '#dc3545', '#6610f2', '#fd7e14', '#20c997'
                ],
            }]
        },
        options: {
            onClick: function(event, activeElements) {
                if (!activeElements || activeElements.length === 0) return;

                const index = activeElements[0].index;

                const datasetSource = isGrouped() ? chartData.costCentersGrouped : chartData.costCenters;
                const clickedItem = datasetSource[index];

                if (!clickedItem || !clickedItem.id) return;

                const clickedId = clickedItem.id;

                const subCenters = chartData.costCenters.filter(item => item.categoria_id == clickedId);

                if (subCenters.length === 0) return;

                let modalData = [...subCenters];

                const parentDirect = chartData.costCenters.find(item => item.id == clickedId && item.categoria_id != clickedId);
                if (parentDirect) {
                    modalData.push({
                        nome: parentDirect.nome + ' (Direto)',
                        total: parentDirect.total
                    });
                }

                showSubCostCenterModal(clickedItem.nome, modalData);
            },
            onHover: function(event, activeElements) {
                if (isGrouped()) {
                    event.native.target.style.cursor = activeElements.length ? 'pointer' : 'default';
                } else {
                    event.native.target.style.cursor = 'default';
                }
            },
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    onClick: null
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = '';
                            const total = context.dataset.data.reduce((sum, current) => sum + current, 0);
                            if (context.parsed !== null) {
                                label += new Intl.NumberFormat('pt-BR', {
                                    style: 'currency',
                                    currency: 'BRL'
                                }).format(context.parsed) + ' — ' + ((100 * context.parsed) / total).toFixed(2) + '%';
                            }
                            return label;
                        }
                    }
                },
                title: {
                    display: true,
                    position: 'bottom',
                    text: `${startDateFormated}  -  ${endDateFormated} <?= $naturezaId != 'all' ? "— Natureza: $naturezasTotals" : '' ?>`,
                    color: "#0000dd",
                    font: {
                        weight: 'normal'
                    }
                }
            }
        }
    });

    let subChartInstance = null;
    const subModal = document.getElementById('subCostCenterModal');
    const closeSubModal = document.getElementById('closeSubModal');
    const subModalTitle = document.getElementById('subModalTitle');
    const subCtx = document.getElementById('subCostCenterChart');

    if (closeSubModal) {
        closeSubModal.addEventListener('click', () => {
            subModal.style.display = 'none';
        });
        subModal.addEventListener('click', (e) => {
            if (e.target === subModal) subModal.style.display = 'none';
        });
    }

    function showSubCostCenterModal(title, data) {
        subModalTitle.textContent = 'Sub-centros de: ' + title;
        subModal.style.display = 'flex';

        if (subChartInstance) {
            subChartInstance.destroy();
        }

        subChartInstance = new Chart(subCtx, {
            type: 'pie',
            data: {
                labels: data.map(item => item.nome),
                datasets: [{
                    data: data.map(item => item.total / 100),
                    backgroundColor: [
                        '#e83e8c', '#6f42c1', '#28a745', '#007bff', '#ffc107', '#17a2b8', '#dc3545', '#6610f2', '#fd7e14', '#20c997'
                    ],
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        onClick: null
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = '';
                                const total = context.dataset.data.reduce((sum, current) => sum + current, 0);
                                if (context.parsed !== null) {
                                    label += new Intl.NumberFormat('pt-BR', {
                                        style: 'currency',
                                        currency: 'BRL'
                                    }).format(context.parsed) + ' — ' + ((100 * context.parsed) / total).toFixed(2) + '%';
                                }
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }

    const agrupadoCheckbox = document.getElementById('agrupado');
    if (agrupadoCheckbox) {
        agrupadoCheckbox.addEventListener('change', function() {
            if (this.checked) {
                costChart.data.labels = chartData.costCentersGrouped.map(item => item.nome);
                costChart.data.datasets[0].data = chartData.costCentersGrouped.map(item => item.total / 100);
            } else {
                costChart.data.labels = chartData.costCenters.map(item => item.nome);
                costChart.data.datasets[0].data = chartData.costCenters.map(item => item.total / 100);
            }
            costChart.update();
        });
    }

    document.forms[0].addEventListener('submit', function(event) {

        if (startDate.value == "") {
            event.preventDefault();
            startDate.focus();
            return;
        }

        if (endDate.value == "") {
            event.preventDefault();
            endDate.focus();
            return;
        }

        if (new Date(startDate.value) > new Date(endDate.value)) {
            let tmp = startDate.value;

            startDate.value = endDate.value;
            endDate.value = tmp;
        }
    });
</script>

<?php if ($naturezaId == 'all'): ?>
    <script>
        const naturezasCtx = document.getElementById('naturezasChart');

        new Chart(naturezasCtx, {
            type: 'bar',
            data: {
                labels: chartData.naturezas.map(item => item.nome),
                datasets: [{
                    label: 'Total R$ <?= Money::centavos_para_reais($sumDailyTotals) ?>',
                    data: chartData.naturezas.map(item => item.total / 100),
                    backgroundColor: '#409cff',
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'R$ ' + value.toFixed(2);
                            }
                        },
                        title: {
                            display: true,
                            text: `${startDateFormated}  -  ${endDateFormated}`,
                            color: "#0000dd"
                        }
                    }
                },
                plugins: {
                    legend: {
                        onClick: null
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = '';
                                const total = context.dataset.data.reduce((sum, current) => sum + current, 0);
                                if (context.parsed.y !== null) {
                                    label += new Intl.NumberFormat('pt-BR', {
                                        style: 'currency',
                                        currency: 'BRL'
                                    }).format(context.parsed.x) + ' — ' + ((100 * context.parsed.x) / total).toFixed(2) + '%';
                                }
                                return label
                            }
                        }
                    }
                }
            }
        });
    </script>
<?php endif; ?>