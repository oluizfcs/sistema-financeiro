<?php

namespace App\Controllers;

use App\Controllers\Services\Cpf;
use App\Controllers\Services\Logger;
use App\Models\Permissions;
use App\Models\Usuario;
use Exception;

class Login
{
    public static bool $needLogin = false;
    public static bool $onlyAdmin = false;

    function __construct(string $p1 = '')
    {
        if (!empty($_POST)) {
            $this->login();
        }

        if ($p1 == 'sair') {
            $this->logout();
        }

        $this->loadView();
    }

    private function login(): void
    {
        try {
            $usuario = Usuario::getByCpf(Cpf::unmaskCpf($_POST['cpf']));

            if (!$usuario || !password_verify($_POST['senha'], $usuario['senha'])) {
                $_SESSION['message'] = ['Credenciais inválidas', 'fail'];
                $this->loadView();
                exit;
            }

            if ($usuario['enabled'] == 0) {
                $_SESSION['message'] = ['Este usuário foi inativado.', 'fail'];
                $this->loadView();
                exit;
            }

            $_SESSION['usuario_id'] = $usuario['id'];
            $_SESSION['usuario_nome'] = $usuario['nome'];
            $_SESSION['usuario_is_admin'] = (int) ((($usuario['permissions'] ?? 0) & Permissions::ADMIN) === Permissions::ADMIN);

            header('Location: ' . $_ENV['BASE_URL'] . '/dashboard');
        } catch (Exception $e) {
            $_SESSION['message'] = ['Credenciais inválidas', 'fail'];
            Logger::error('erro ao logar', ['$_POST' => $_POST]);
            $this->loadView();
            exit;
        }
    }

    private function logout(): void
    {
        session_unset();
        session_destroy();
        header('Location: ' . $_ENV['BASE_URL'] . '/login');
    }

    private function loadView(): void
    {
        $version = (require 'changelog.php')[0]['versao'];
        include '../src/Views/login.php';
    }
}
