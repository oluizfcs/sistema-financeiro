<?php
$type = 'create';
$href = $_ENV['BASE_URL'] . '/bancos';

if ($view == 'atualizar') {
    $type = 'update';
    $href = $_ENV['BASE_URL'] . '/bancos/detalhar/' . $banco->getId();
}
?>

<div class="form-section">
    <div class="section">
        <h1><?= ucfirst($view) ?> Conta bancária</h1>
        <form action="" method="post">

            <?php if ($view == 'atualizar'): ?>
                <input type="hidden" name="entity_id" value="<?= $banco->getId() ?>">
                <input type="hidden" name="old_nome" value="<?= $banco->getNome() ?>">
            <?php endif; ?>

            <label for="nome">Nome da Conta/Banco:</label>
            <div class="input-wrapper">
                <input
                    type="text"
                    id="nome"
                    name="nome"
                    required maxlength="55"
                    autocomplete="off"
                    autofocus
                    <?= $view == 'atualizar' ? "value=\"" . $banco->getNome() . "\"" : '' ?>>
            </div>

            <?php if ($view != 'atualizar'): ?>
                <label for="saldo-abertura">Saldo de abertura:</label>
                <div class="input-wrapper">
                    <input
                        type="text"
                        class="money"
                        id="saldo-abertura"
                        name="saldo-abertura"
                        maxlength="22"
                        autocomplete="off">
                </div>
            <?php endif; ?>

            <button class="btn btn-primary" type="submit" name="type" value="<?= $type ?>"><?= ucfirst($view) ?></button>
            <a class="btn btn-secondary" href="<?= $href ?>">Cancelar</a>
        </form>
    </div>
</div>
