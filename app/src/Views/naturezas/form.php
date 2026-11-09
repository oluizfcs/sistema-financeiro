<?php
$type = 'create';
$href = $_ENV['BASE_URL'] . '/naturezas';

if ($view == 'atualizar') {
    $type = 'update';
    $href = $_ENV['BASE_URL'] . '/naturezas/detalhar/' . $natureza->getId();
}
?>

<div class="form-section">
    <div class="section">
        <h1><?= ucfirst($view) ?> natureza</h1>
        <form action="" method="post">
            <?php if ($view == 'atualizar'): ?>
                <input type="hidden" name="entity_id" value="<?= $natureza->getId() ?>">
                <input type="hidden" name="old_nome" value="<?= $natureza->getNome() ?>">
            <?php endif; ?>

            <label for="nome">Nome da natureza:</label>
            <div class="input-wrapper">
                <input
                    type="text"
                    id="nome"
                    name="nome"
                    required maxlength="55"
                    <?= $view == 'atualizar' ? "value=\"" . $natureza->getNome() . "\"" : '' ?>
                    autofocus
                    autocomplete=off>
            </div>

            <button class="btn btn-primary" type="submit" name="type" value="<?= $type ?>"><?= ucfirst($view) ?></button>
            <a class="btn btn-secondary" href="<?= $href ?>">Cancelar</a>
        </form>
    </div>
</div>