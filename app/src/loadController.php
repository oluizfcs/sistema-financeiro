<?php

$controllers = [
    'Dashboard', 'Contas', 'Usuarios',
    'Fornecedores', 'Bancos', 'CentrosDeCusto',
    'Login', 'Relatorios', 'Naturezas',
    'Cadastros', 'Admin'
];

$url = explode('/', rtrim(filter_input(INPUT_GET, 'url', FILTER_DEFAULT), '/'), 5);

$controller = str_replace(' ', '', ucwords(str_replace('-', ' ', strtolower(array_shift($url)))));

if (!in_array($controller, $controllers)) {
    include '../src/404.php';
    exit;
}

$c = 'App\\Controllers\\' . $controller;

if ($c::$needLogin && !isset($_SESSION['usuario_id'])) {
    $_SESSION['message'] = ['É necessário fazer login novamente pois sua sessão expirou.', 'fail'];
    header('Location: ' . $_ENV['BASE_URL'] . '/login');
    exit;
}

if (isset($_SESSION['usuario_id'])) {
    if ($c::$onlyAdmin && !isAdmin()) {
        if ($controller == "Usuarios") {
            if (count($url) == 0 || $url[1] != $_SESSION['usuario_id']) {
                $url[0] = 'detalhar';
                $url[1] = $_SESSION['usuario_id'];
            }
        } else {
            $_SESSION['message'] = ['Você não tem permissão para acessar essa página.', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/dashboard');
            exit;
        }
    }
}

new ($c)(...$url);