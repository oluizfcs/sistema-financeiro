<?php
$type = 'create';
$href = $_ENV['BASE_URL'] . '/centros-de-custo';

if ($view == 'atualizar') {
    $type = 'update';
    $href = $_ENV['BASE_URL'] . '/centros-de-custo/detalhar/' . $centro_de_custo->getId();
}
?>

<div class="form-section">
    <div class="section">
        <h1><?= ucfirst($view) ?> centro de custo</h1>
        <form action="" method="post">

            <?php if ($view == 'atualizar'): ?>
                <input type="hidden" name="entity_id" value="<?= $centro_de_custo->getId() ?>">
                <input type="hidden" name="old_nome" value="<?= $centro_de_custo->getNome() ?>">
            <?php endif; ?>

            <label for="nome">Nome:</label>
            <div class="input-wrapper">
                <input
                    type="text"
                    id="nome"
                    name="nome"
                    required maxlength="45"
                    <?= $view == 'atualizar' ? "value=\"" . $centro_de_custo->getNome() . "\"" : '' ?>
                    autofocus
                    autocomplete="off">
            </div>

            <?php if ($view == 'atualizar' && ($centro_de_custo->getCategoriaId() != null)): ?>
                <label for="categoria_id">Centro de custo superior (opcional):</label>
                <select name="categoria_id" class="select2" style="width: 100%">
                    <?php foreach ($categorias as $cat): ?>
                        <?php
                        $selected = '';
                        if ($cat['id'] == $centro_de_custo->getCategoriaId()) {
                            $selected = 'selected';
                        }
                        ?>
                        <option value="<?= $cat['id'] ?>" <?= $selected ?>><?= $cat['nome'] ?></option>
                    <?php endforeach; ?>
                </select>
                <br><br>
            <?php endif; ?>

            <button class="btn btn-primary" type="submit" name="type" value="<?= $type ?>"><?= ucfirst($view) ?></button>
            <a class="btn btn-secondary" href="<?= $href ?>">Cancelar</a>
        </form>
    </div>
</div>