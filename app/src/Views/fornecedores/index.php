<h1>Fornecedores<?= !$this->enabled ? ' inativados' : '' ?></h1>
<a class="btn btn-success" href="<?= $_ENV['BASE_URL'] ?>/fornecedores/cadastrar"><i class="fa-solid fa-plus"></i> Cadastrar</a>
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

    if ($fornecedores == []) {
        echo '<p> Nenhum fornecedor encontrado.</p>';
    }
    ?>

    <?php if (count($fornecedores) > 0): ?>
        <div class="table-section">
            <table class="non-clickable-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Telefone</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($fornecedores as $fornecedor): ?>
                        <tr>
                            <td><?= $fornecedor['nome'] ?></td>
                            <td><?= $fornecedor['telefone'] ?? '-' ?></td>
                            <td>
                                <a class="btn btn-secondary" title="detalhar" href="<?= $_ENV['BASE_URL'] ?>/fornecedores/detalhar/<?= $fornecedor['id'] ?>"><i class="fa-solid fa-magnifying-glass"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
</div>
<?php endif; ?>