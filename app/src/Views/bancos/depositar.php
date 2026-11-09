<?php
$href = $_ENV['BASE_URL'] . '/bancos/detalhar/' . $banco->getId();
$isNormalUser = !isAdmin();
?>
<div class="form-section">
    <div class="section">
        <h1>Depositar em <?= $banco->getNome() ?></h1>
        <form action="" method="post" onsubmit="return confirm('Tem certeza que estes dados estão corretos? Só administradores poderão corrigir depois.')">
            <input type="hidden" name="banco_id" value="<?= $banco->getId() ?>">
            <input type="hidden" id="today" value="<?= (new DateTime())->format('Y-m-d') ?>">

            <label for="valor">Valor:</label>
            <div class="input-wrapper">
                <input
                    type="text"
                    id="valor"
                    name="valor"
                    class="money"
                    required
                    maxlength="15"
                    autocomplete="off"
                    autofocus>
            </div>

            <label for="historico">Histórico:</label>
            <div class="input-wrapper">
                <input
                    type="text"
                    id="historico"
                    name="historico"
                    required
                    maxlength="255"
                    autocomplete="off">
            </div>

            <label for="data">Data:</label>
            <div class="input-wrapper">
                <input type="date" id="data" name="data"
                    class="<?= $isNormalUser ? 'hide' : '' ?>"
                    value="<?= (new DateTime())->format('Y-m-d') ?>">
                <div id="deposit-date-quick-buttons" class="<?= $isNormalUser ? '' : 'hide' ?>">
                    <button type="button" class="date-btn" data-offset="0" onclick="setDepositQuickDate(0)">
                        <span class="date-btn-day"></span>
                        <span class="date-btn-label">(hoje)</span>
                    </button>
                    <button type="button" class="date-btn" data-offset="-1" onclick="setDepositQuickDate(-1)">
                        <span class="date-btn-day"></span>
                        <span class="date-btn-label">(ontem)</span>
                    </button>
                    <button type="button" class="date-btn" data-offset="-2" onclick="setDepositQuickDate(-2)">
                        <span class="date-btn-day"></span>
                        <span class="date-btn-label">(2 dias)</span>
                    </button>
                    <button type="button" class="date-btn" data-offset="-3" onclick="setDepositQuickDate(-3)">
                        <span class="date-btn-day"></span>
                        <span class="date-btn-label">(3 dias)</span>
                    </button>
                </div>
            </div>

            <button class="btn btn-primary" type="submit" name="type" value="depositar">Depositar</button>
            <a class="btn btn-secondary" href="<?= $href ?>">Cancelar</a>
        </form>
    </div>
</div>

<script>
    const IS_NORMAL_USER_DEPOSIT = <?= $isNormalUser ? 'true' : 'false' ?>;

    function getDepositYMD(offset) {
        let d = new Date();
        if (offset !== 0) {
            d.setDate(d.getDate() + parseInt(offset));
        }
        let ano = d.getFullYear();
        let mes = String(d.getMonth() + 1).padStart(2, '0');
        let dia = String(d.getDate()).padStart(2, '0');
        return ano + '-' + mes + '-' + dia;
    }

    function initDepositQuickDateButtons() {
        let buttons = document.querySelectorAll('#deposit-date-quick-buttons .date-btn');
        buttons.forEach(function(btn) {
            let offset = parseInt(btn.getAttribute('data-offset'));
            let d = new Date();
            d.setDate(d.getDate() + offset);
            let dd = String(d.getDate()).padStart(2, '0');
            let mm = String(d.getMonth() + 1).padStart(2, '0');
            btn.querySelector('.date-btn-day').textContent = dd + '/' + mm;
        });
        highlightSelectedDepositDateButton();
    }

    function highlightSelectedDepositDateButton() {
        let currentValue = document.getElementById('data').value;
        let buttons = document.querySelectorAll('#deposit-date-quick-buttons .date-btn');
        buttons.forEach(function(btn) {
            btn.classList.remove('selected');
            let offset = parseInt(btn.getAttribute('data-offset'));
            let val = getDepositYMD(offset);
            if (val === currentValue) {
                btn.classList.add('selected');
            }
        });
    }

    function setDepositQuickDate(daysOffset) {
        document.getElementById('data').value = getDepositYMD(daysOffset);
        highlightSelectedDepositDateButton();
    }

    if (IS_NORMAL_USER_DEPOSIT) {
        document.addEventListener('DOMContentLoaded', function() {
            initDepositQuickDateButtons();
        });
    }
</script>

<style>
    #deposit-date-quick-buttons:not(.hide) {
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
</style>