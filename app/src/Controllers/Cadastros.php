<?php

namespace App\Controllers;

class Cadastros
{
    public static bool $needLogin = true;
    public static bool $onlyAdmin = true;
    private array $views = ['index'];

    function __construct(string $view = 'index', string $param = '')
    {
        $this->loadView($view);
    }

    private function loadView(string $view): void
    {
        if (!in_array($view, $this->views)) {
            include '../src/404.php';
            exit;
        }

        include '../src/templates/header.php';
        include "../src/Views/cadastros/$view.php";
        include '../src/templates/footer.php';
    }
}
