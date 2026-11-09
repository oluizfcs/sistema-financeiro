<?php $isNormalUser = !isAdmin(); $qtdParcelas = count($parcelas); ?>
<h1 id="page-title">Contas <?= $filters['filter_status'] ?></h1>
<button class="btn btn-success" onclick="openContaForm()"><i class="fa-solid fa-plus"></i>
    Cadastrar</button>
<?php $temBancos = !empty($bancos); ?>
<button class="btn btn-success" onclick="openContaForm({ mode: 'cash' })"
    <?= $temBancos ? '' : 'disabled title="Cadastre uma conta bancária ativa para usar o lançamento à vista" style="opacity:.55;cursor:not-allowed;"' ?>>
    <i class="fa-solid fa-money-bill"></i> Lançamento à vista</button>
<div class="report-dropdown">
    <button class="btn btn-secondary" id="toggle-report-menu" type="button" onclick="toggleReportMenu()"><i class="fa-solid fa-file"></i>
        Gerar relatório</button>
    <div id="report-menu" class="report-menu" style="display:none;">
        <div class="report-menu-orientation">
            <span>Orientação:</span>
            <label><input type="radio" name="report_orientation" value="landscape" checked> Paisagem</label>
            <label><input type="radio" name="report_orientation" value="portrait"> Retrato</label>
        </div>
        <button type="button" onclick="gerarRelatorio('analitico')">Relatório Analítico</button>
        <button type="button" onclick="gerarRelatorio('semanal')">Relatório Agrupado por semana</button>
        <button type="button" onclick="gerarRelatorio('sintetico')">Relatório Sintético</button>
        <button type="button" onclick="gerarRelatorio('sintetico', true)">Relatório Sintético com sub-centros</button>
        <button type="button" onclick="gerarRelatorio('sintetico_geral')">Relatório Sintético Geral</button>
    </div>
</div>
<div class="section">
    <div class="section search-section">
        <form onsubmit="return false;" style="width: 100%; display: flex; gap: 0.5em; flex-wrap: wrap;">
            <input type="hidden" name="report_source" value="contas">
            <div>
                <label for="filter_start_date">Data inicial:</label>
                <input type="date" name="filter_start_date" id="filter_start_date" value="<?= $filters['filter_start_date'] ?>">
            </div>
            <div>
                <label for="filter_end_date" style="margin-left: 0.5em;">Data final:</label>
                <input type="date" name="filter_end_date" id="filter_end_date" value="<?= $filters['filter_end_date'] ?>">
            </div>
            <div>
                <label for="filter_status">Situação:</label>
                <select id="filter_status" name="filter_status">
                    <?php
                    $options = ['a pagar', 'pagas', 'inativadas'];

                    foreach ($options as $option) {
                        $selected = $filters['filter_status'] == $option ? 'selected' : '';
                        echo "<option value='$option' $selected>" . ucfirst($option) . '</option>';
                    }
                    ?>
                </select>
            </div>
            <div>
                <label for="filter_fornecedor">Fornecedor:</label><br>
                <select name="filter_fornecedor" id="filter_fornecedor" class="select2">
                    <option value="all">Todos</option>
                    <?php foreach ($availableFilterOptions['fornecedores'] as $fornecedor): ?>
                        <?php
                        $selected = $fornecedor['id'] == $filters['filter_fornecedor'] ? 'selected' : '';
                        ?>
                        <option <?= $selected ?> value="<?= $fornecedor['id'] ?>"><?= $fornecedor['nome'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <br>
            <div>
                <label for="filter_natureza">Natureza:</label>
                <select name="filter_natureza" id="filter_natureza">
                    <option value="all">Todas</option>
                    <?php foreach ($availableFilterOptions['naturezas'] as $natureza): ?>
                        <?php
                        $selected = $natureza['id'] == $filters['filter_natureza'] ? 'selected' : '';
                        ?>
                        <option <?= $selected ?> value="<?= $natureza['id'] ?>"><?= $natureza['nome'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <br>
            <div>
                <label for="filter_centro">Centro de custo:</label><br>
                <select name="filter_centro" id="filter_centro" class="select2">
                    <option value="all">Todos</option>
                    <?php foreach ($availableFilterOptions['centros'] as $centro): ?>
                        <?php
                        $selected = $centro['id'] == $filters['filter_centro'] ? 'selected' : '';
                        $isCategoria = $centro['categoria_id'] === null;
                        ?>
                        <option <?= $selected ?> value="<?= $centro['id'] ?>" data-is-categoria="<?= $isCategoria ? '1' : '0' ?>"><?= $centro['nome'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
    </div>
    <?php

    use App\Controllers\Services\Money;

    ?>
    <span id="no-results" style="<?= $qtdParcelas > 0 ? 'display:none;' : '' ?>">
        Nenhuma conta encontrada.
    </span>
    <span id="clear-filters" style="<?= ($filters['filter_natureza'] != 'all' || $filters['filter_centro'] != 'all' || $filters['filter_fornecedor'] != 'all') && $qtdParcelas === 0 ? '' : 'display:none;' ?>">limpar filtros</span>
    <div id="hidden-columns" style="display: flex; justify-content: center; gap: 10px; margin: -1em 1em 1em 1em; text-align: center; <?= $qtdParcelas > 0 ? '' : 'display:none;' ?>"></div>
    <div id="table-container" class="table-section" style="<?= $qtdParcelas > 0 ? '' : 'display:none;' ?>">
        <table>
            <thead>
                <tr>
                    <td class="column column1" data-col="1"><?= $filters['filter_status'] == 'pagas' ? 'Pago em' : 'Vencimento' ?></td>
                    <td class="column column2" data-col="2">Fornecedor</td>
                    <td class="column column3" data-col="3">Natureza</td>
                    <td class="column column4" data-col="4">Centro de custo</td>
                    <td class="column column5" data-col="5">Histórico</td>
                    <td class="column column6" data-col="6">Parcela</td>
                    <td class="column column7" data-col="7">Valor (R$)</td>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($parcelas as $parcela): ?>
                    <?php
                    $valorExibido = match ($filters['filter_status']) {
                        'pagas' => $parcela['pagamento_total_em_centavos'] ?? $parcela['valor_pago_em_centavos'],
                        'a pagar' => $parcela['valor_restante_em_centavos'],
                        default => $parcela['valor_total_em_centavos'],
                    };
                    ?>
                    <tr <?= $filters['filter_status'] == 'pagas' ? 'class="paid"' : '' ?> data-parcela-id="<?= $parcela['id'] ?>" data-conta-id="<?= $parcela['conta_id'] ?>" data-qtd-parcela="<?= $parcela['numero_parcela'] . '/' . $parcela['total_parcelas'] ?>" data-numero-parcela="<?= $parcela['numero_parcela'] ?>" data-historico="<?= $parcela['historico'] ?>" data-banco-id="<?= $parcela['banco_id'] ?>" data-valor="<?= Money::centavos_para_reais($parcela['valor_em_centavos']) ?>" data-valor-total="<?= Money::centavos_para_reais($parcela['valor_total_em_centavos']) ?>" data-valor-pago="<?= $parcela['valor_pago_em_centavos'] ?>" data-valor-pagamento="<?= $parcela['pagamento_total_em_centavos'] ?? $parcela['valor_pago_em_centavos'] ?>" data-valor-restante="<?= $parcela['valor_restante_em_centavos'] ?>" data-parcial="<?= (!$parcela['paid'] && $parcela['valor_pago_em_centavos'] > 0) ? '1' : '0' ?>" data-origem="<?= $parcela['origem'] ?? 'parcela' ?>" data-pagamento-id="<?= $parcela['pagamento_id'] ?? '' ?>" data-vencimento="<?= $parcela['data_vencimento'] ?>" data-pagamento="<?= $parcela['data_pagamento'] ?? '' ?>">
                        <?php if ($filters['filter_status'] == 'pagas'): ?>
                            <td class="column column1 nextInstallment"><?= $parcela['data_pagamento'] ? (new DateTime($parcela['data_pagamento']))->format('d/m/Y') : '-' ?></td>
                        <?php else: ?>
                            <td class="column column1 nextInstallment"><?= new DateTime($parcela['data_vencimento'])->format('d/m/Y') ?></td>
                        <?php endif; ?>
                        <td class="column column2"><?= $parcela['fornecedor'] ?? '-' ?></td>
                        <td class="column column3"><?= $parcela['natureza'] ?></td>
                        <td class="column column4"><?= $parcela['centro'] ?></td>
                        <td class="column column5"><?= $parcela['historico'] ?></td>
                        <td class="column column6">
                            <?= $parcela['numero_parcela'] . '/' . $parcela['total_parcelas'] ?>
                            <?php if (!$parcela['paid'] && $parcela['valor_pago_em_centavos'] > 0): ?>
                                <span class="parcial-badge" title="Pagamento parcial">P</span>
                            <?php endif; ?>
                        </td>
                        <td class="column column7"><?= Money::centavos_para_reais($valorExibido) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    $totalParcelas = 0;
    foreach ($parcelas as $parcela) {
        $totalParcelas += match ($filters['filter_status']) {
            'pagas' => $parcela['pagamento_total_em_centavos'] ?? $parcela['valor_pago_em_centavos'],
            'a pagar' => $parcela['valor_restante_em_centavos'],
            default => $parcela['valor_total_em_centavos'],
        };
    }
    ?>
    <div id="parcelas-resumo" style="justify-content: space-between; <?= $qtdParcelas > 0 ? 'display: flex;' : 'display: none;' ?>">
        <span id="qtd-parcelas">Lançamentos: <?= $qtdParcelas ?></span>
        <span id="total-parcelas">Total R$: <?= Money::centavos_para_reais($totalParcelas) ?></span>
    </div>
    <dialog closedby="any" id="installments-dialog" style="max-width: 360px;">
        <span id="qtd-parcela-dialog"></span>
        <div id="parcela-pagamento-resumo" class="parcela-pagamento-resumo" style="display: none;">
            <span>Já foi pago: <strong id="parcela-valor-pago"></strong></span>
            <span>Restante: <strong id="parcela-valor-restante"></strong></span>
        </div>
        <div style="border-radius: 8px; background-color: #dadada; padding: 5px; margin-bottom: 10px;">
            <span id="parcela-historico"></span>
        </div>
        <a class="btn btn-secondary" href="<?= $_ENV['BASE_URL'] ?>/contas"><i class="fa-solid fa-magnifying-glass"></i> Ver conta</a>
        <button id="edit-conta" class="btn btn-secondary" type="button"><i class="fa-solid fa-pen-to-square"></i> Editar conta</button>
        <br>
        <form id="pay-installment-form" action="" method="post" style="width: 100%">
            <hr>
            <input type="hidden" name="usuario_id" value="<?= $_SESSION['usuario_id'] ?>">
            <input type="hidden" name="paid" value="0">
            <input type="hidden" name="numero_parcela">
            <input type="hidden" name="conta_id">
            <input type="hidden" name="request_type" value="js">
            <input type="hidden" name="type" value="cobrar">
            <div class="input-wrapper">
                <label for="bancos">Conta bancária <span style="font-weight: 200; font-size: 13px;">(Onde será descontado)</span></label>
                <select name="banco" id="bancos">
                    <option selected disabled value="0">-- Selecione --</option>
                    <?php foreach ($bancos as $banco): ?>
                        <option value="<?= $banco['id'] ?>"><?= $banco['nome'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="input-wrapper">
                <label for="data">Data de pagamento:</label>
                <input type="date" name="data" id="data" value="<?= date('Y-m-d') ?>" class="<?= $isNormalUser ? 'hide' : '' ?>">
                <div id="pay-date-quick-buttons" class="<?= $isNormalUser ? '' : 'hide' ?>">
                    <button type="button" class="date-btn" data-offset="0" onclick="setPayQuickDate(0)">
                        <span class="date-btn-day"></span>
                        <span class="date-btn-label">(hoje)</span>
                    </button>
                    <button type="button" class="date-btn" data-offset="-1" onclick="setPayQuickDate(-1)">
                        <span class="date-btn-day"></span>
                        <span class="date-btn-label">(ontem)</span>
                    </button>
                    <button type="button" class="date-btn" data-offset="-2" onclick="setPayQuickDate(-2)">
                        <span class="date-btn-day"></span>
                        <span class="date-btn-label">(2 dias)</span>
                    </button>
                    <button type="button" class="date-btn" data-offset="-3" onclick="setPayQuickDate(-3)">
                        <span class="date-btn-day"></span>
                        <span class="date-btn-label">(3 dias)</span>
                    </button>
                </div>
            </div>
            <div id="input-juros-wrapper" class="input-wrapper" style="display: none;">
                <label for="juros_atraso">Houve juros pelo atraso?</label>
                <input type="text" class="money" maxlength="18" name="juros_atraso" id="juros_atraso">
            </div>
            <input type="hidden" name="valor">
            <div class="input-wrapper" id="input-pagamento-parcial-wrapper">
                <label for="pagamento_parcial">
                    <input type="checkbox" name="pagamento_parcial" id="pagamento_parcial" value="1">
                    Pagamento parcial
                </label>
            </div>
            <div class="input-wrapper" id="input-valor-parcial-wrapper" style="display: none;">
                <label for="valor_parcial">Valor a pagar:</label>
                <input type="text" class="money" maxlength="18" name="valor_parcial" id="valor_parcial">
                <button type="button" class="btn btn-primary" id="confirm-parcial-btn" onclick="confirmarPagamentoParcial()">Pagar</button>
            </div>
            <p id="pay-status" class="pay-status" style="display: none;"></p>
            <button type="button" class="btn btn-primary" id="pay-all-btn" onclick="pagarTudo()">Pagar</button>
            <button type="button" commandfor="installments-dialog" command="close" class="btn btn-secondary">Fechar</button>
        </form>
    </dialog>

    <dialog closedby="any" id="inativar-dialog" style="max-width: 420px; padding:0;">
        <div class="dialog-wrapper">
            <div class="dialog-header">
                <h2>Estornar Conta</h2>
            </div>
            <div class="dialog-content">
                <p>Esta conta possui pagamentos e não pode ser editada.</p>
                <p>Para editar, é necessário estornar todos os pagamentos. As cobranças serão removidas das contas bancárias vinculadas.</p>
                <div id="inativar-dialog-details" style="background-color: #dadada; border-radius: 8px; padding: 10px; margin-bottom: 10px; font-size: 0.9em;"></div>
                <div style="display:flex;gap:8px;">
                    <button id="estorno-btn" class="btn btn-danger">Estornar</button>
                    <button type="button" commandfor="inativar-dialog" command="close" class="btn btn-secondary">Cancelar</button>
                </div>
            </div>
        </div>
    </dialog>

    <?php include 'partials/_form.php' ?>
</div>

<style>
    .red {
        background-color: hsla(0, 100%, 85%, 0.8);
    }

    .yellow {
        background-color: hsla(60, 100%, 85%, 0.8);
    }

    #clear-filters {
        color: blue;
        text-decoration: underline;
        cursor: pointer;
    }

    #pay-date-quick-buttons:not(.hide) {
        display: flex;
        gap: 0.5rem;
        flex-wrap: wrap;
        margin-top: 0.5rem;
    }

    .report-dropdown {
        position: relative;
        display: inline-block;
    }

    .report-menu {
        position: absolute;
        left: 0;
        top: calc(100% + 2px);
        background: #fff;
        border: 1px solid #ccc;
        border-radius: 6px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        z-index: 100;
        min-width: 300px;
        padding: 6px 0;
    }

    .report-menu-orientation {
        display: flex;
        gap: 0.75rem;
        align-items: center;
        padding: 0 12px 8px 12px;
        margin-bottom: 6px;
        border-bottom: 1px solid #eee;
        color: #333;
        font-size: 0.9em;
    }

    .report-menu-orientation span {
        font-weight: 600;
    }

    .report-menu-orientation label {
        display: flex;
        align-items: center;
        gap: 3px;
        cursor: pointer;
    }

    .report-menu button {
        display: block;
        width: 100%;
        padding: 8px 12px;
        border: none;
        background: transparent;
        color: #333;
        text-align: left;
        font-size: 0.95em;
        cursor: pointer;
    }

    .report-menu button:hover {
        background-color: #e8ebf5;
        color: #1a1a1a;
    }

    #input-pagamento-parcial-wrapper label {
        display: flex;
        align-items: center;
        gap: 6px;
        cursor: pointer;
    }

    .parcial-badge {
        display: inline-block;
        font-size: 0.62rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding: 0.1rem 0.4rem;
        border-radius: 8px;
        background: #f59e0b;
        color: #000;
        border: 2.5px solid black;
    }

    .parcela-pagamento-resumo {
        display: flex;
        flex-wrap: wrap;
        gap: 4px 16px;
        margin-bottom: 10px;
        font-size: 0.9em;
    }

    .parcela-pagamento-resumo strong {
        color: #92400e;
    }

    #input-valor-parcial-wrapper {
        margin-top: 0.75rem;
    }

    .pay-status {
        color: #15803d;
        background: #dcfce7;
        border-radius: 6px;
        padding: 6px 8px;
        margin: 0.5rem 0;
        font-size: 0.9em;
    }

    thead tr td:hover {
        background-color: rgb(151, 4, 4);
        cursor: pointer;
    }

    table.filter1 td.column1 {
        display: none;
    }

    table.filter2 td.column2 {
        display: none;
    }

    table.filter3 td.column3 {
        display: none;
    }

    table.filter4 td.column4 {
        display: none;
    }

    table.filter5 td.column5 {
        display: none;
    }

    table.filter6 td.column6 {
        display: none;
    }

    table.filter7 td.column7 {
        display: none;
    }
</style>

<script>
    const BASE_URL = '<?= $_ENV['BASE_URL'] ?>';

    document.addEventListener('click', function(e) {
        const dropdown = document.querySelector('.report-dropdown');
        if (dropdown && !dropdown.contains(e.target)) {
            document.getElementById('report-menu').style.display = 'none';
        }
    });

    function getFilterParams() {
        return {
            filter_start_date: document.getElementById('filter_start_date').value,
            filter_end_date: document.getElementById('filter_end_date').value,
            filter_status: document.getElementById('filter_status').value,
            filter_fornecedor: document.getElementById('filter_fornecedor').value,
            filter_natureza: document.getElementById('filter_natureza').value,
            filter_centro: document.getElementById('filter_centro').value,
        };
    }

    function buildRow(p, status) {
        const isPagas = status === 'pagas';
        const dateStr = isPagas
            ? (p.data_pagamento || '-')
            : (p.data_vencimento || '-');
        const formattedDate = new Date(dateStr + 'T03:00').toLocaleDateString('pt-BR');
        const rowClass = isPagas ? ' class="paid"' : '';
        const isParcial = !p.paid && p.valor_pago_em_centavos > 0;
        const parcelaCell = p.numero_parcela + '/' + p.total_parcelas +
            (isParcial
                ? ' <span class="parcial-badge" title="Pagamento parcial">P</span>'
                : '');
        const valorExibido = isPagas
            ? (p.pagamento_total_reais ?? p.valor_pago_reais)
            : (status === 'a pagar' ? p.valor_restante_reais : p.valor_total_reais);

        return `<tr${rowClass} data-parcela-id="${p.id}" data-vencimento="${p.data_vencimento}" data-pagamento="${p.data_pagamento || ''}" data-conta-id="${p.conta_id}" data-qtd-parcela="${p.numero_parcela}/${p.total_parcelas}" data-numero-parcela="${p.numero_parcela}" data-historico="${p.historico}" data-banco-id="${p.banco_id || ''}" data-valor="${p.valor_reais}" data-valor-total="${p.valor_total_reais}" data-valor-pago="${p.valor_pago_em_centavos}" data-valor-pagamento="${p.pagamento_total_em_centavos ?? p.valor_pago_em_centavos}" data-valor-restante="${p.valor_restante_em_centavos}" data-parcial="${isParcial ? '1' : '0'}" data-origem="${p.origem || 'parcela'}" data-pagamento-id="${p.pagamento_id ?? ''}">` +
            `<td class="column column1 nextInstallment">${formattedDate}</td>` +
            `<td class="column column2">${p.fornecedor || '-'}</td>` +
            `<td class="column column3">${p.natureza}</td>` +
            `<td class="column column4">${p.centro}</td>` +
            `<td class="column column5">${p.historico}</td>` +
            `<td class="column column6">${parcelaCell}</td>` +
            `<td class="column column7">${valorExibido}</td>` +
            `</tr>`;
    }

    function applyRowColoring() {
        const status = document.getElementById('filter_status').value;
        if (status !== 'a pagar') return;

        document.querySelectorAll('.nextInstallment').forEach(td => {
            let date = td.textContent.trimStart().slice(0, 10);
            date = new Date(date.split("/").reverse().join("-") + 'T03:00');
            const today = new Date();
            const diffInDays = (today - date) / (1000 * 60 * 60 * 24);
            const row = td.parentElement;
            row.classList.remove('red', 'yellow');
            if (diffInDays >= 0) {
                row.classList.add('red');
            } else if (diffInDays >= -5) {
                row.classList.add('yellow');
            }
        });
    }

    function renderTable(parcelas, status) {
        const tableContainer = document.getElementById('table-container');
        const noResults = document.getElementById('no-results');
        const hiddenColumnsDiv = document.getElementById('hidden-columns');
        const qtdParcelasEl = document.getElementById('qtd-parcelas');
        const totalParcelasEl = document.getElementById('total-parcelas');
        const resumoEl = document.getElementById('parcelas-resumo');
        const tbody = document.querySelector('table tbody');
        const theadTd = document.querySelector('thead td.column1');

        const hasResults = parcelas.length > 0;
        const totalCentavos = parcelas.reduce((total, p) => {
            const valor = status === 'pagas'
                ? Number(p.pagamento_total_em_centavos ?? p.valor_pago_em_centavos ?? 0)
                : (status === 'a pagar'
                    ? Number(p.valor_restante_em_centavos || 0)
                    : Number(p.valor_total_em_centavos || 0));
            return total + valor;
        }, 0);

        qtdParcelasEl.textContent = 'Lançamentos: ' + parcelas.length;
        totalParcelasEl.textContent = 'Total R$: ' + (totalCentavos / 100).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        tableContainer.style.display = hasResults ? '' : 'none';
        hiddenColumnsDiv.style.display = hasResults ? '' : 'none';
        resumoEl.style.display = hasResults ? 'flex' : 'none';
        qtdParcelasEl.style.display = hasResults ? '' : 'none';
        noResults.style.display = hasResults ? 'none' : '';

        if (!hasResults) {
            return;
        }

        theadTd.textContent = status === 'pagas' ? 'Pago em' : 'Vencimento';

        tbody.innerHTML = parcelas.map(p => buildRow(p, status)).join('');

    applyRowColoring();

    $('#filter_centro').select2({
        language: {
            noResults: function () {
                return "Nenhuma opção encontrada."
            }
        },
        templateResult: function(option) {
            if (!option.element) return option.text;
            var isCategoria = $(option.element).data('is-categoria') == 1;
            if (isCategoria) {
                return $('<span>' + option.text + ' <span style="color:red">*</span></span>');
            }
            return option.text;
        }
    });
    }

    function updateClearFiltersVisibility(parcelas) {
        const params = getFilterParams();
        const hasFilters = params.filter_natureza !== 'all' || params.filter_centro !== 'all' || params.filter_fornecedor !== 'all';
        document.getElementById('clear-filters').style.display = (hasFilters && parcelas.length === 0) ? '' : 'none';
    }

    function fetchParcelas() {
        const formData = new FormData();
        formData.append('type', 'search_json');
        const params = getFilterParams();
        for (const [k, v] of Object.entries(params)) {
            formData.append(k, v);
        }

        return fetch(BASE_URL + '/contas', { method: 'POST', body: formData })
            .then(r => r.json())
            .then(data => {
                if (!data.ok) return;
                document.getElementById('page-title').textContent = 'Contas ' + data.status;
                renderTable(data.parcelas, data.status);
                updateClearFiltersVisibility(data.parcelas);
            });
    }

    let filterTimeout;
    function debounceFetchFilters() {
        clearTimeout(filterTimeout);
        filterTimeout = setTimeout(fetchParcelas, 300);
    }

    document.getElementById('filter_start_date').addEventListener('change', debounceFetchFilters);
    document.getElementById('filter_end_date').addEventListener('change', debounceFetchFilters);
    document.getElementById('filter_status').addEventListener('change', fetchParcelas);
    $('#filter_fornecedor').on('change', fetchParcelas);
    $('#filter_natureza').on('change', fetchParcelas);
    $('#filter_centro').on('change', fetchParcelas);

    const clearFilters = document.getElementById("clear-filters");
    if (clearFilters) {
        clearFilters.addEventListener("click", () => {
            $('#filter_natureza').val('all').trigger('change');
            $('#filter_centro').val('all').trigger('change');
            $('#filter_fornecedor').val('all').trigger('change');
            fetchParcelas();
        });
    }

    applyRowColoring();

    function initPayDateQuickButtons() {
        document.querySelectorAll('#pay-date-quick-buttons .date-btn').forEach(function(btn) {
            let offset = parseInt(btn.getAttribute('data-offset'));
            let d = new Date();
            d.setDate(d.getDate() + offset);
            let dd = String(d.getDate()).padStart(2, '0');
            let mm = String(d.getMonth() + 1).padStart(2, '0');
            btn.querySelector('.date-btn-day').textContent = dd + '/' + mm;
        });
        highlightSelectedPayDateButton();
    }

    function highlightSelectedPayDateButton() {
        let currentValue = document.getElementById('data').value;
        document.querySelectorAll('#pay-date-quick-buttons .date-btn').forEach(function(btn) {
            btn.classList.remove('selected');
            let offset = parseInt(btn.getAttribute('data-offset'));
            let d = new Date();
            d.setDate(d.getDate() + offset);

            let ano = d.getFullYear();
            let mes = String(d.getMonth() + 1).padStart(2, '0');
            let dia = String(d.getDate()).padStart(2, '0');
            let val = ano + '-' + mes + '-' + dia;

            if (val === currentValue) {
                btn.classList.add('selected');
            }
        });
    }

    function setPayQuickDate(daysOffset) {
        let d = new Date();
        d.setDate(d.getDate() + daysOffset);

        let ano = d.getFullYear();
        let mes = String(d.getMonth() + 1).padStart(2, '0');
        let dia = String(d.getDate()).padStart(2, '0');

        document.getElementById('data').value = ano + '-' + mes + '-' + dia;

        highlightSelectedPayDateButton();
    }

    if (<?= $isNormalUser ? 'true' : 'false' ?>) {
        initPayDateQuickButtons();
    }

    const hiddenColumns = document.getElementById("hidden-columns");
    const tableEl = document.querySelector("table");
    if (tableEl) {
        tableEl.querySelector("thead").addEventListener("click", (e) => {
            if (e.target.classList.contains("column")) {
                const col = e.target.getAttribute('data-col');
                tableEl.classList.add("filter" + col);
                const revertBtn = document.createElement("button");
                revertBtn.classList.add("btn", "btn-secondary");
                revertBtn.textContent = e.target.textContent;
                revertBtn.addEventListener("click", function() {
                    tableEl.classList.remove("filter" + col);
                    this.remove();
                });
                hiddenColumns.append(revertBtn);
            }
        });
    }
</script>

<style>
    .paid {
        background-color: #a1ebc5;
    }
</style>

<script src="<?= $_ENV['BASE_URL'] ?>/js/indexContasInstallmentsPay.js"></script>