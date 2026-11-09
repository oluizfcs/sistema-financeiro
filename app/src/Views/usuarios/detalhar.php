<?php

use App\Models\Permissions;

$data_criacao = DateTime::createFromFormat("Y-m-d H:i:s", $usuario->getData_criacao());
if ($usuario->getData_edicao() != null) {
    $data_edicao = DateTime::createFromFormat("Y-m-d H:i:s", $usuario->getData_edicao());
    $data_edicao = $data_edicao->format("d/m/Y \à\s H:i");
} else {
    $data_edicao = 'nunca';
}
?>

<h1>Usuario: <?= $usuario->getNome() ?></h1>
CPF: <?= $usuario->getMaskedCpf() ?> <br>
Permissões: <?= $usuario->getPermissions() != 0 ? implode(',', Permissions::getLabels($usuario->getPermissions())) : 'nenhuma.' ?> <br>
<br>
<?php if(isAdmin()): ?>
    <a class="btn btn-secondary" href="<?= $_ENV['BASE_URL'] ?>/usuarios">Voltar</a>
<?php endif; ?>

<?php if ($_SESSION['usuario_id'] == $usuario->getId() || (isAdmin() && $usuario->getId() != 1)): ?>
    <a class="btn btn-secondary" href="<?= $_ENV['BASE_URL'] ?>/usuarios/atualizar/<?= $usuario->getId() ?>"><i class="fa-solid fa-pen-to-square"></i> Atualizar</a>
<?php endif; ?>

<?php if (isAdmin() && $usuario->getId() != 1): ?>
    <form method="POST" action="">
        <input type="hidden" name="usuario_id" value="<?= $usuario->getId() ?>">
        <?php if ($usuario->isEnabled()): ?>
            <button class="btn btn-secondary" name="type" value="unable" onclick="return confirm('Realmente deseja inativar este usuário?')">
                <i class="fa-solid fa-box-archive"></i> Inativar
            </button>
        <?php else: ?>
            <button class="btn btn-secondary" name="type" value="enable" onclick="return confirm('Realmente deseja ativar este usuario?')">
                <i class="fa-solid fa-box-open"></i> Ativar
            </button>
        <?php endif; ?>
    </form>
<?php endif; ?>

<?php include '../src/templates/auditoria.php'; ?>