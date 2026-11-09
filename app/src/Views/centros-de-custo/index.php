<h1>Centros de custo<?= !$this->enabled ? ' inativados' : '' ?></h1>
<a class="btn btn-success" href="<?= $_ENV['BASE_URL'] ?>/centros-de-custo/cadastrar"><i class="fa-solid fa-plus"></i> Cadastrar</a>
<div class="section">
    <div class="section search-section">
        <form method="POST" action="">
            <input type="hidden" name="type" value="search">
            <input type="text" name="search" id="search" autocomplete="off" placeholder="Buscar..." value="<?= $this->search ?? '' ?>">
            <label>
                <input type="radio" name="enabled" value="1" <?= $this->enabled ? 'checked' : '' ?> onclick="form.submit()">
                ativos
            </label>
            <label>
                <input type="radio" name="enabled" value="0" <?= !$this->enabled ? 'checked' : '' ?> onclick="form.submit()">
                inativados
            </label>
        </form>
    </div>
    <?php

    if ($centrosDeCusto == []) {
        echo '<p> Nenhum centro de custo encontrado.</p>';
    }
    ?>

    <?php if (count($centrosDeCusto) > 0): ?>
        <div class="table-section">
            <table class="non-clickable-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($centrosDeCusto as $centro): ?>
                        <tr style="background-color: #87b9fa;">
                            <td style="position: relative; <?= (count($centro['children']) > 0) ? 'cursor: pointer;' : '' ?>" onclick="event.stopPropagation(); toggleChildren(document.getElementById('btn-<?= $centro['id'] ?>'), 'child-of-<?= $centro['id'] ?>')">
                                <?php if (count($centro['children']) > 0): ?>
                                    <i id="btn-<?= $centro['id'] ?>" class="fa-solid fa-chevron-right toggle-btn"
                                        style="
                                            position: absolute;
                                            left: 1px; top: 50%;
                                            width: 26px;
                                            transform: translateY(-50%);"></i>
                                <?php endif; ?>
                                <?= $centro['nome'] ?>
                            </td>
                            <td>
                                <a class="btn btn-secondary" title="detalhar" href="<?= $_ENV['BASE_URL'] ?>/centros-de-custo/detalhar/<?= $centro['id'] ?>"><i class="fa-solid fa-magnifying-glass"></i></a>
                                <?php if ($this->enabled): ?>
                                    <button type="button" class="btn btn-success" title="cadastrar sub-centro" onclick="openSubcostCenterModal(<?= $centro['id'] ?>)"><i class="fa-solid fa-plus"></i></button>
                                <?php endif; ?>
                            </td>
                        </tr>

                        <?php if (count($centro['children']) > 0): ?>
                            <?php foreach ($centro['children'] as $child): ?>
                                <tr class="child-of-<?= $centro['id'] ?>" style="display: none; background-color: #f9f9f9;">
                                    <td style="position: relative;">
                                        <i class="fa-solid fa-turn-up fa-rotate-90" style="font-size: 0.6em; color: #888; position: absolute; left: 10px; top: 50%;"></i>
                                        <?= $child['nome'] ?>
                                    </td>
                                    <td>
                                        <a href="<?= $_ENV['BASE_URL'] ?>/centros-de-custo/detalhar/<?= $child['id'] ?>" class="btn btn-secondary"><i class="fa-solid fa-magnifying-glass"></i></a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<script>
    function toggleChildren(btn, className) {
        const rows = document.getElementsByClassName(className);
        let isHidden = true;

        for (let row of rows) {
            if (row.style.display === 'none') {
                row.style.display = 'table-row';
                isHidden = false;
            } else {
                row.style.display = 'none';
            }
        }

        if (isHidden) {
            btn.classList.remove('fa-chevron-down');
            btn.classList.add('fa-chevron-right');
        } else {
            btn.classList.remove('fa-chevron-right');
            btn.classList.add('fa-chevron-down');
        }
    }
</script>
<?php include '_subcenter_form.php' ?>