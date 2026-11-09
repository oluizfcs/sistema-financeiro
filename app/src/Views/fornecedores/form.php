<?php
$type = 'create';
$href = $_ENV['BASE_URL'] . '/fornecedores';

if ($view == 'atualizar') {
    $type = 'update';
    $href = $_ENV['BASE_URL'] . '/fornecedores/detalhar/' . $fornecedor->getId();
}
?>


<div class="form-section">
    <div class="section">
        <h1><?= ucfirst($view) ?> fornecedor</h1>
        <form action="" method="post">
            <?php if ($view == 'atualizar'): ?>
                <input type="hidden" name="entity_id" value="<?= $fornecedor->getId() ?>">
                <input type="hidden" name="old_nome" value="<?= $fornecedor->getNome() ?>">
                <input type="hidden" name="old_telefone" value="<?= $fornecedor->getTelefone() ?>">
            <?php endif; ?>

            <label for="nome">Nome do fornecedor:</label>
            <div class="input-wrapper">
                <input
                    type="text"
                    id="nome"
                    name="nome"
                    required maxlength="55"
                    <?= $view == 'atualizar' ? "value=\"" . $fornecedor->getNome() . "\"" : '' ?>
                    autofocus
                    autocomplete=off>
            </div>

            <label for="telefone">Telefone:</label>
            <div class="input-wrapper">
                <input
                    type="text"
                    id="telefone"
                    name="telefone"
                    maxlength="45"
                    <?= $view == 'atualizar' ? "value=\"" . $fornecedor->getTelefone() . "\"" : '' ?>
                    autocomplete=off>
            </div>

            <button class="btn btn-primary" type="submit" name="type" value="<?= $type ?>"><?= ucfirst($view) ?></button>
            <a class="btn btn-secondary" href="<?= $href ?>">Cancelar</a>
        </form>
    </div>
</div>