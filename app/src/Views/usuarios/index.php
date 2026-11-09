<h1>Usuários<?= $this->mostrar == ' inativados' ? $this->mostrar : '' ?></h1>
<?php if (isAdmin()): ?>
    <a class="btn btn-success" href="<?= $_ENV['BASE_URL'] ?>/usuarios/cadastrar"><i class="fa-solid fa-plus"></i> Cadastrar</a>
<?php endif; ?>
<div class="section">
    <div class="section search-section">

        <form method="POST" action="">
            <input type="hidden" name="type" value="search">
            <input type="hidden" name="mostrar" id="mostrar">
            <input type="text" name="search" id="search" autocomplete="off" placeholder="Buscar..." value="<?= $this->search ?? '' ?>">
            <label>
                <input type="radio" name="mostrar" value="todos" <?= $this->mostrar == 'todos' ? 'checked' : '' ?> onclick="form.submit()">
                ativos
            </label>

            <label>
                <input type="radio" name="mostrar" value="inativados" <?= $this->mostrar == 'inativados' ? 'checked' : '' ?> onclick="form.submit()">
                inativados
            </label>
        </form>
    </div>
    <?php

    use App\Controllers\Services\Cpf;

    if ($usuarios == []) {
        echo '<p> Nenhum usuario encontrado.</p>';
    }
    ?>

    <?php if (count($usuarios) > 0): ?>
        <div class="table-section">
            <table class="non-clickable-table">
                <tr>
                    <th>Nome</th>
                    <th class="sorttable_nosort">CPF</th>
                    <th>Ações</th>
                </tr>
                <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td><?= $usuario['nome'] ?></td>
                        <td><?= Cpf::maskCpf($usuario['cpf']) ?></td>
                        <td>
                            <a href="<?= $_ENV['BASE_URL'] ?>/usuarios/detalhar/<?= $usuario['id'] ?>" class="btn btn-secondary"><i class="fa-solid fa-magnifying-glass"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>
    <?php endif; ?>
</div>