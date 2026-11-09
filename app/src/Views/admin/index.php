<h1>Administração</h1>
<div class="section">
    <h2><i class="fa-solid fa-scroll"></i> Auditoria</h2>
    <div class="section search-section">
        <form onsubmit="return false;" style="width: 100%; display: flex; gap: 0.5em; flex-wrap: wrap; align-items: flex-end;">
            <div>
                <input type="text" name="search" id="search" autocomplete="off" placeholder="Buscar..." value="<?= htmlspecialchars($this->search ?? '') ?>">
            </div>
            <div>
                <label for="entity">Entidade:</label><br>
                <select name="entity" id="entity">
                    <option value="todas" <?= $this->entity == 'todas' ? 'selected' : '' ?>>Todas</option>
                    <?php foreach ($entidades as $chave => $info): ?>
                        <option value="<?= $chave ?>" <?= $this->entity == $chave ? 'selected' : '' ?>><?= $info['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div style="width: 130px;">
                <label for="filter_user">Usuário:</label><br>
                <select name="user" id="filter_user">
                    <option value="todos" <?= $this->user == 'todos' ? 'selected' : '' ?>>Todos</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $this->user == $u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($u['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filter_start_date">Data inicial:</label><br>
                <input type="date" name="filter_start_date" id="filter_start_date" value="<?= $this->startDate ?>">
            </div>
            <div>
                <label for="filter_end_date" style="margin-left: 0.5em;">Data final:</label><br>
                <input type="date" name="filter_end_date" id="filter_end_date" value="<?= $this->endDate ?>">
            </div>
        </form>
    </div>

    <?php
    $nomesUsuarios = [];
    foreach ($users as $u) {
        $nomesUsuarios[$u['id']] = $u['nome'];
    }
    $temLogs = count($logs) > 0;
    ?>

    <span id="no-logs" style="<?= $temLogs ? 'display:none;' : '' ?>">Nenhum log encontrado para o período informado.</span>

    <div id="logs-section" class="table-section" style="<?= $temLogs ? '' : 'display:none;' ?>">
        <table id="logs-table" class="logs-table">
            <thead>
                <tr>
                    <th>Data e Hora</th>
                    <th>Usuário</th>
                    <th>Entidade</th>
                    <th>Campo</th>
                    <th>Antigo</th>
                    <th>Novo</th>
                </tr>
            </thead>
            <tbody id="logs-tbody">
                <?php
                if ($temLogs) {
                    include 'partials/_rows.php';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<div class="section">
    <h2><i class="fa-solid fa-cloud-arrow-down"></i> Histórico de atualizações</h2>
    <div class="table-section">
        <table>
            <thead>
                <tr>
                    <th>Data e Hora</th>
                    <th>Versão</th>
                    <th>Mudanças</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($changelog as $index => $atualizacao):
                    $data = DateTime::createFromFormat('Y-m-d H:i', $atualizacao['data']);
                    $dataFormatada = $data ? $data->format('d/m/Y H:i') : $atualizacao['data'];
                    $temDetalhes = !empty($atualizacao['novidades']) || !empty($atualizacao['correcoes']);
                ?>
                    <tr>
                        <td><?= htmlspecialchars($dataFormatada) ?></td>
                        <td><?= htmlspecialchars($atualizacao['versao']) ?></td>
                        <td>
                            <?= htmlspecialchars($atualizacao['resumo']) ?>
                            <?php if ($temDetalhes): ?>
                                <a href="#" class="changelog-link" onclick="openChangelog(<?= $index ?>); return false;">ver mudanças</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="changelogModal" class="modal" style="display:none;">
    <div class="modal-content changelog-modal">
        <span class="changelog-close" onclick="closeChangelog()">&times;</span>
        <?php foreach ($changelog as $index => $atualizacao):
            $dt = DateTime::createFromFormat('Y-m-d H:i', $atualizacao['data']);
            $dtFormatada = $dt ? $dt->format('d/m/Y H:i') : $atualizacao['data'];
        ?>
            <div id="chg-<?= $index ?>" class="changelog-detail" style="display:none;">
                <div class="changelog-head">
                    <span class="changelog-version">Atualização <?= htmlspecialchars($atualizacao['versao']) ?></span>
                    <span class="changelog-date"><?= htmlspecialchars($dtFormatada) ?></span>
                </div>
                <p class="changelog-resumo"><?= htmlspecialchars($atualizacao['resumo']) ?></p>

                <?php if (!empty($atualizacao['novidades'])): ?>
                    <div class="changelog-section">
                        <h3><i class="fa-solid fa-star"></i> Novidades</h3>
                        <ul>
                            <?php foreach ($atualizacao['novidades'] as $item): ?>
                                <li><?= htmlspecialchars($item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if (!empty($atualizacao['correcoes'])): ?>
                    <div class="changelog-section">
                        <h3><i class="fa-solid fa-wrench"></i> Correções</h3>
                        <ul>
                            <?php foreach ($atualizacao['correcoes'] as $item): ?>
                                <li><?= htmlspecialchars($item) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<style>
    .logs-table tr[data-href] {
        cursor: pointer;
    }

    .logs-table tr[data-href]:hover td {
        background-color: rgba(90, 122, 255, 0.15);
    }

    .changelog-link {
        color: #5a7aff;
        cursor: pointer;
        text-decoration: underline;
        margin-left: 0.4em;
        white-space: nowrap;
    }

    #changelogModal {
        position: fixed;
        inset: 0;
        z-index: 1100;
        overflow-y: auto;
        background-color: rgba(15, 23, 42, 0.55);
        display: flex;
        align-items: flex-start;
        justify-content: center;
    }

    .changelog-modal {
        margin: auto;
        width: min(640px, 92%);
        background: #fff;
        border-radius: 14px;
        box-shadow: 0 20px 45px rgba(0, 0, 0, 0.3);
        padding: 1.6rem 1.8rem 1.8rem;
        position: relative;
    }

    .changelog-close {
        position: absolute;
        top: 12px;
        right: 16px;
        font-size: 26px;
        line-height: 1;
        color: #6b7280;
        background: none;
        border: none;
        cursor: pointer;
    }

    .changelog-close:hover {
        color: #111;
    }

    .changelog-head {
        display: flex;
        justify-content: space-between;
        align-items: baseline;
        gap: 0.5rem;
        flex-wrap: wrap;
    }

    .changelog-version {
        font-weight: 700;
        font-size: 1.15rem;
        color: var(--clr-dark-blue);
    }

    .changelog-date {
        color: #6b7280;
        font-size: 0.8rem;
    }

    .changelog-resumo {
        margin: 0.3rem 0 0.2rem;
        color: #374151;
    }

    .changelog-section h3 {
        font-size: 1rem;
        margin: 1.1rem 0 0.4rem;
        color: var(--clr-dark-blue);
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 0.3rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .changelog-section ul {
        margin: 0;
        padding-left: 1.3rem;
        color: #1f2937;
    }

    .changelog-section li {
        margin-bottom: 0.35rem;
        text-align: left;
    }
</style>

<script>
    const LOGS_BASE_URL = '<?= $_ENV['BASE_URL'] ?>';

    function initLogsTable() {
        const tbody = document.getElementById('logs-tbody');

        tbody.querySelectorAll('tr[data-href]').forEach(function(tr) {
            tr.addEventListener('click', function() {
                const href = this.dataset.href;
                if (href) {
                    window.location.href = href;
                }
            });
        });

        tbody.querySelectorAll('td').forEach(function(td) {
            if (td.tagName != 'TD' || td.textContent.length <= 50) {
                return;
            }

            let savedText = td.textContent;
            const a = document.createElement('a');
            a.textContent = a.textContent.slice(0, 50);
            a.classList.add('clickable-text');
            td.appendChild(a);

            a.addEventListener('click', function(event) {
                event.stopPropagation();
                td.innerHTML = savedText;
            });
        });
    }

    let adminFilterTimeout;

    function fetchLogs() {
        clearTimeout(adminFilterTimeout);

        const formData = new FormData();
        formData.append('type', 'search_json');
        formData.append('search', document.getElementById('search').value);
        formData.append('entity', document.getElementById('entity').value);
        formData.append('user', document.getElementById('filter_user').value);
        formData.append('filter_start_date', document.getElementById('filter_start_date').value);
        formData.append('filter_end_date', document.getElementById('filter_end_date').value);

        fetch(LOGS_BASE_URL + '/admin', {
                method: 'POST',
                body: formData
            })
            .then(function(r) {
                return r.json();
            })
            .then(function(data) {
                if (!data.ok) {
                    return;
                }
                const tbody = document.getElementById('logs-tbody');
                const hasRows = data.html.trim().length > 0;
                tbody.innerHTML = data.html;
                document.getElementById('logs-section').style.display = hasRows ? '' : 'none';
                document.getElementById('no-logs').style.display = hasRows ? 'none' : '';
                initLogsTable();
            });
    }

    document.getElementById('search').addEventListener('input', function() {
        clearTimeout(adminFilterTimeout);
        adminFilterTimeout = setTimeout(fetchLogs, 300);
    });
    document.getElementById('entity').addEventListener('change', fetchLogs);
    document.getElementById('filter_user').addEventListener('change', fetchLogs);
    document.getElementById('filter_start_date').addEventListener('change', fetchLogs);
    document.getElementById('filter_end_date').addEventListener('change', fetchLogs);

    function openChangelog(index) {
        document.querySelectorAll('.changelog-detail').forEach(function(div) {
            div.style.display = 'none';
        });
        document.getElementById('chg-' + index).style.display = 'block';
        document.getElementById('changelogModal').style.display = 'flex';
    }

    function closeChangelog() {
        document.getElementById('changelogModal').style.display = 'none';
    }

    window.addEventListener('click', function(event) {
        if (event.target === document.getElementById('changelogModal')) {
            closeChangelog();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            closeChangelog();
        }
    });

    initLogsTable();
</script>