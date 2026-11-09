<?php

use App\Models\Permissions;

$type = 'create';
$href = $_ENV['BASE_URL'] . '/usuarios';

if ($view == 'atualizar') {
    $type = 'update';
    $href = $_ENV['BASE_URL'] . '/usuarios/detalhar/' . $usuario->getId();
}
?>
<div class="form-section">
    <div class="section">
        <h1><?= ucfirst($view) ?> Usuário</h1>
        <form action="" method="post" onsubmit="return check()" style="width: 100%;">
            <?php if ($view == 'atualizar'): ?>
                <input type="hidden" name="entity_id" value="<?= $usuario->getId() ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-main">
                <label for="nome">Nome do usuário:</label>
                <div class="input-wrapper">
                    <input
                        type="text"
                        id="nome"
                        name="nome"
                        required maxlength="45"
                        <?= $view == 'atualizar' ? "value=\"" . $usuario->getNome() . "\" readonly" : "value=\"" . ($_POST['nome'] ?? '') . "\"" ?>
                        autocomplete="off"
                        <?= $view == 'atualizar' ? '' : 'autofocus ' ?>
                        <?= $view == 'atualizar' ? 'style="background-color: lightgrey;"' : '' ?>>
                </div>

                <label for="cpf">CPF:</label>
                <div class="input-wrapper">
                    <input
                        type="text"
                        id="cpf"
                        name="cpf"
                        required
                        <?= $view == 'atualizar' ? "value=\"" . $usuario->getMaskedCpf() . "\" readonly" : "value=\"" . ($_POST['cpf'] ?? '') . "\"" ?>
                        autocomplete="off"
                        <?= $view == 'atualizar' ? 'style="background-color: lightgrey;"' : '' ?>>
                </div>

                <label for="senha">Senha<?= $view == 'atualizar' ? ' (opcional)' : '' ?>:</label>
                <div class="input-wrapper">
                    <input
                        type="password"
                        id="senha"
                        name="senha"
                        <?= $view == 'atualizar' ? '' : 'required ' ?>maxlength="100"
                        value="<?= $_POST['senha'] ?? '' ?>"
                        placeholder="<?= $view == 'atualizar' ? 'Deixe em branco para manter a atual' : '' ?>">
                </div>

                <label for="senha">Confirmar senha:</label>
                <div class="input-wrapper">
                    <input
                        type="password"
                        id="confirmarsenha"
                        <?= $view == 'atualizar' ? '' : 'required ' ?>maxlength="100">
                </div>
            </div>

            <?php if ($_SESSION['usuario_id'] == 1 && ($view == 'cadastrar' || $usuario->getId() != 1)): ?>
                <aside class="permissions-panel">
                    <h3>Permissões</h3>
                    <?php foreach(Permissions::LABELS as $bit => $label): ?>
                        <?php $isAdmin = $bit === Permissions::ADMIN; ?>
                        <div class="permission-item <?= $isAdmin ? 'admin-item' : '' ?>">
                            <input
                                type="checkbox"
                                id="perm-<?= $bit ?>"
                                name="permissions[]"
                                value="<?= $bit ?>"
                                <?= $view == 'atualizar' && Permissions::userCan($usuario->getPermissions(), $bit) ? 'checked' : '' ?>
                            >
                            <label for="perm-<?= $bit ?>">
                                <?= $isAdmin ? '<strong>' : '' ?><?= $label ?><?= $isAdmin ? '</strong>' : '' ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </aside>
            <?php endif; ?>
        </div>

        <div class="form-actions">
            <button class="btn btn-primary" type="submit" name="type" value="<?= $type ?>"><?= ucfirst($view) ?></button>
            <a class="btn btn-secondary" href="<?= $href ?>">Cancelar</a>
        </div>
        </form>
    </div>
</div>

<script src="<?= $_ENV['BASE_URL'] ?>/js/imask.js"></script>
<script>
    const cpf = document.getElementById("cpf");
    const maskOptions = {
        mask: '000.000.000-00'
    }

    IMask(cpf, maskOptions);

    function check() {
        let senha = document.getElementById("senha").value;
        let confirmarsenha = document.getElementById("confirmarsenha").value;
        if (senha != confirmarsenha) {
            alert("As senhas não coincidem.");
            return false;
        }
        return true;
    }

    // Admin permission controls other permissions
    const adminCheckbox = document.querySelector('.admin-item input[type="checkbox"]');
    const otherCheckboxes = document.querySelectorAll('.permission-item:not(.admin-item) input[type="checkbox"]');

    if (adminCheckbox) {
        // Initialize visual state on load (don't change checked state)
        updateOtherPermissions(adminCheckbox.checked, false);

        adminCheckbox.addEventListener('change', function() {
            updateOtherPermissions(this.checked, true);
        });
    }

    function updateOtherPermissions(adminChecked, isUserAction = false) {
        otherCheckboxes.forEach(cb => {
            if (isUserAction) {
                cb.checked = adminChecked;
            }
            cb.disabled = adminChecked;
            const label = cb.closest('label');
            if (label) {
                label.style.opacity = adminChecked ? '0.5' : '1';
                label.style.cursor = adminChecked ? 'not-allowed' : 'pointer';
            }
        });
    }
</script>

<style>
    .form-grid {
        display: grid;
        grid-template-columns: 1fr 22rem;
        gap: 1.5rem;
        align-items: start;
    }

    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
        .permissions-panel {
            order: 3;
            margin-top: 1rem;
        }
    }

    .form-main {
        min-width: 0;
    }

    .permissions-panel {
        background: #fafafa;
        border: 1px solid #f0f0f0;
        border-radius: 10px;
        padding: 1.25rem;
        height: fit-content;
    }

    .permissions-panel h3 {
        margin: 0 0 1rem;
        font-size: 0.9rem;
        font-weight: 600;
        color: #374151;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .permission-item {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        padding: 0.5rem 0;
        transition: opacity 0.2s, background 0.2s;
        border-radius: 6px;
        margin: 0 -0.5rem;
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }

    .permission-item:hover {
        background: rgba(5, 41, 94, 0.04);
    }

    .permission-item.admin-item {
        border-bottom: 1px solid #e5e7eb;
        margin-bottom: 0.5rem;
        padding-bottom: 0.75rem;
    }

    .permission-item input[type="checkbox"] {
        width: 1.1rem;
        height: 1.1rem;
        accent-color: var(--clr-dark-blue);
        flex-shrink: 0;
        cursor: pointer;
    }

    .permission-item input[type="checkbox"]:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .permission-item label {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
        font-size: 0.85rem;
        color: #374151;
        transition: opacity 0.2s;
    }

    .permission-item input:disabled + label {
        opacity: 0.5;
        cursor: not-allowed;
    }

    .permission-item.admin-item label {
        font-weight: 600;
        color: var(--clr-dark-blue);
    }

    .permission-item label strong {
        font-weight: 700;
    }

    @media (max-width: 768px) {
        .permissions-panel {
            margin-top: 1rem;
        }
    }

    .form-actions {
        display: flex;
        justify-content: flex-start;
        gap: 0.75rem;
        padding-top: 1.5rem;
        margin-top: 1rem;
        border-top: 1px solid #f0f0f0;
    }
</style>
