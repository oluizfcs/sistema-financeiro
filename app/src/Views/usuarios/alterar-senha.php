<div class="form-section">
    <div class="section">
        <h1>Alterar Senha</h1>
        <form action="" method="post" onsubmit="return check()">
            <input type="hidden" name="entity_id" value="<?= $usuario->getId() ?>">
            <p>Usuário: <?= $usuario->getNome() ?></p>
            <p>CPF: <?= $usuario->getMaskedCpf() ?></p>
            <label for="senha">Senha:</label>
            <div class="input-wrapper">
                <input
                    type="password"
                    id="senha"
                    name="senha"
                    required maxlength="100"
                    autofocus>
            </div>

            <label for="senha">Confirmar senha:</label>
            <div class="input-wrapper">
                <input
                    type="password"
                    id="confirmarsenha"
                    required maxlength="100">
            </div>

            <button class="btn btn-primary" type="submit" name="type" value="update">Alterar Senha</button>
            <a class="btn btn-secondary" href="<?= $_ENV['BASE_URL'] ?>/usuarios/detalhar/<?= $usuario->getId() ?>">Cancelar</a>
        </form>
    </div>
</div>

<script>
    function check() {
        let senha = document.getElementById("senha").value;
        let confirmarsenha = document.getElementById("confirmarsenha").value;
        if (senha != confirmarsenha) {
            alert("As senhas não coincidem.");
            return false;
        }
        return true;
    }
</script>