<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Sistema Financeiro</title>
    <link rel="shortcut icon" href="<?= $_ENV['BASE_URL'] ?>/images/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="<?= $_ENV['BASE_URL'] ?>/css/reset.css">
    <link rel="stylesheet" href="<?= $_ENV['BASE_URL'] ?>/css/style.css">
    <link rel="stylesheet" href="<?= $_ENV['BASE_URL'] ?>/css/bsbuttons.css">
    <link rel="stylesheet" href="<?= $_ENV['BASE_URL'] ?>/css/solid.min.css">
    <script src="<?= $_ENV['BASE_URL'] ?>/js/fontawesome.min.js"></script>
    <script src="<?= $_ENV['BASE_URL'] ?>/js/solid.min.js"></script>
    <script src="<?= $_ENV['BASE_URL'] ?>/js/jquery.min.js" type="text/javascript"></script>
    <script src="<?= $_ENV['BASE_URL'] ?>/js/jquery.maskMoney.js" type="text/javascript"></script>
    <link href="<?= $_ENV['BASE_URL'] ?>/css/select2.min.css" rel="stylesheet" />
    <script src="<?= $_ENV['BASE_URL'] ?>/js/select2.min.js"></script>
</head>

<body data-baseurl="<?= $_ENV["BASE_URL"] ?>">
    <button type="button" id="toggle-sidebar" onclick="document.getElementById('sidebar').classList.toggle('sidebar-overlay');"><i class="fa-solid fa-bars"></i></button>
    <div id="main-layout">
        <div id="sidebar">
            <?php
            $controller = explode('/', $_SERVER['QUERY_STRING'])[0];
            $controller = str_replace('url=', '', $controller);
            (function () {
                global $controller;
                $controller = strtolower($controller);
                
                $menuItems = [
                    'dashboard' => ['icon' => 'fas fa-tachometer-alt', 'label' => 'Dashboard', 'admin' => false],
                    'contas' => ['icon' => 'fa-solid fa-receipt', 'label' => 'Contas', 'admin' => false],
                    'bancos' => ['icon' => 'fa-solid fa-building-columns', 'label' => 'Financeiro', 'admin' => false],
                    'cadastros' => ['icon' => 'fa-solid fa-plus', 'label' => 'Cadastros', 'admin' => true],
                    'admin' => ['icon' => 'fa-solid fa-cog', 'label' => 'Administração', 'admin' => true]
                ];

                foreach ($menuItems as $item => $info) {

                    if ($info['admin'] && !isAdmin()) {
                        continue;
                    }

                    $selected = $controller == $item ? "style='background-color: hsl(214, 77%, 30%)'" : '';

                    if ($item == 'cadastros' && in_array($controller, ['fornecedores', 'centrosdecusto', 'naturezas', 'usuarios'])) {
                        $selected = "style='background-color: hsl(214, 77%, 30%)'";
                    }

                    echo "<a href='" . $_ENV['BASE_URL'] . "/$item' $selected><i class='{$info['icon']}'></i><span class='link-text'>{$info['label']}</span></a>";
                }
            })();
            ?>

            <div id="sidebar-actions">
                <a id="user" href="<?= $_ENV['BASE_URL'] ?>/usuarios/detalhar/<?= $_SESSION['usuario_id'] ?>"><i class="fa-solid fa-user"></i><?= $_SESSION['usuario_nome'] ?></a>
                <a id="logout" href="<?= $_ENV['BASE_URL'] ?>/login/sair"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
            </div>
        </div>

        <script>
            /* force icon render */
            if (window.FontAwesome) {
                FontAwesome.dom.i2svg({
                    node: document.getElementById('sidebar')
                });
                FontAwesome.dom.i2svg({
                    node: document.getElementById('toggle-sidebar')
                });
            }
        </script>

        <div id="content">
            <?php include '../src/templates/message.php'; ?>