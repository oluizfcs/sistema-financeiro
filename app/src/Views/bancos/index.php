<h1>Contas bancárias<?= !$this->enabled ? ' inativadas' : '' ?></h1>
<?php if(isAdmin()): ?>
<a class="btn btn-success" href="<?= $_ENV['BASE_URL'] ?>/bancos/cadastrar"><i class="fa-solid fa-plus"></i> Cadastrar</a>
<?php endif; ?>
<div class="report-dropdown">
    <button class="btn btn-secondary" id="toggle-report-menu" type="button" onclick="toggleReportMenu()"><i class="fa-solid fa-file"></i>
        Relatório de entradas e saídas</button>
    <div id="report-menu" class="report-menu" style="display:none;">
        <div class="report-menu-orientation">
            <span>Orientação:</span>
            <label><input type="radio" name="report_orientation" value="landscape" checked> Paisagem</label>
            <label><input type="radio" name="report_orientation" value="portrait"> Retrato</label>
        </div>
        <div class="report-menu-field">
            <label for="banco_report_id">Banco:</label>
            <select id="banco_report_id">
                <option value="all">Todos os bancos</option>
                <?php foreach ($bancosOpcoes as $b): ?>
                    <option value="<?= $b['id'] ?>"><?= $b['nome'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
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
<div class="section">
    <div class="section search-section">
        <form method="POST" action="">
            <input type="hidden" name="type" value="search">
            <input type="text" name="search" id="search" autocomplete="off" placeholder="Buscar..." value="<?= $this->search ?? '' ?>">
            <label>
                <input type="radio" name="enabled" value="1" <?= $this->enabled ? 'checked' : '' ?> onclick="form.submit()">
                ativas
            </label>
            <label>
                <input type="radio" name="enabled" value="0" <?= !$this->enabled ? 'checked' : '' ?> onclick="form.submit()">
                inativadas
            </label>
        </form>
    </div>
    <?php

    use App\Controllers\Services\Money;

    if ($bancos == []) {
        echo '<p> Nenhuma conta bancária encontrada.</p>';
    }
    ?>

    <?php if (count($bancos) > 0): ?>
        <div class="table-section">
            <table>
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Saldo (R$)</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bancos as $banco): ?>
                        <tr>
                            <td><?= $banco['nome'] ?></td>
                            <td><?= Money::centavos_para_reais($banco['saldo_em_centavos']) ?></td>
                            <td>
                                <a href="<?= $_ENV['BASE_URL'] ?>/bancos/detalhar/<?= $banco['id'] ?>" title="detalhar" class="btn btn-secondary"><i class="fa-solid fa-magnifying-glass"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>