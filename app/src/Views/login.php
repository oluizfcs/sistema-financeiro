<!DOCTYPE html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Financeiro</title>
</head>
<link rel="stylesheet" href="<?= $_ENV['BASE_URL'] ?>/css/login.css">
<link rel="shortcut icon" href="<?= $_ENV['BASE_URL'] ?>/images/favicon.ico" type="image/x-icon">

<div id="login">
    <?php include '../src/templates/message.php'; ?>
    <form action="" method="post">
        <div id="form">
            <div>
                <label for="cpf">CPF</label>
                <input type="text" inputmode="numeric" id="cpf" name="cpf" required value="<?= $_POST['cpf'] ?? '' ?>">
            </div>
            <div>
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" required value="<?= $_POST['senha'] ?? '' ?>">
            </div>
            <input id="submit" type="submit" value="Entrar">
        </div>
    </form>
    <p style="text-align: center; margin: 0;">v<?= $version ?></p>
</div>

<script src="<?= $_ENV['BASE_URL'] . '/js/imask.js' ?>"></script>
<script>
    const cpf = document.getElementById("cpf");
    const maskOptions = {
        mask: '000.000.000-00'
    }

    IMask(cpf, maskOptions);

    cpf.focus();
</script>