<?php

namespace App\Controllers;

use App\Controllers\Services\Cpf;
use App\Controllers\Services\Logger;
use App\Models\Database;
use App\Models\Permissions;
use App\Models\Usuario;
use Exception;

class Usuarios
{
    public static bool $needLogin = true;
    public static bool $onlyAdmin = true;
    private array $views = ['index', 'cadastrar', 'atualizar', 'detalhar'];
    private int $id;
    private string $mostrar = 'todos';
    private string $search = '';

    function __construct(string $view = 'index', string $param = '')
    {
        if (strlen($param) > 0) {
            $id = filter_var($param, FILTER_VALIDATE_INT);
            if ($id) {
                $this->id = $id;
            } else {
                $_SESSION['message'] = ['Usuario não encontrado', 'fail'];
                header('Location: ' . $_ENV['BASE_URL'] . '/usuarios');
                exit;
            }
        }

        if (!empty($_POST)) {
            switch ($_POST['type']) {
                case 'create':
                    onlyAdmin();
                    $this->create();
                    break;
                case 'update':
                    $this->update();
                    break;
                case 'unable':
                    onlyAdmin();
                    $this->unable();
                    break;
                case 'enable':
                    onlyAdmin();
                    $this->enable();
                    break;
                case 'search':
                    $_SESSION['usuarios_filters'] = [
                        'search' => $_POST['search'] ?? '',
                        'mostrar' => $_POST['mostrar'] ?? ''
                    ];
                    header('Location: ' . $_ENV['BASE_URL'] . '/usuarios');
                    exit;
                    break;
            }
        }

        $this->loadView($view);
    }

    private function create(): void
    {
        $nome = $_POST['nome'];
        $cpf = $_POST['cpf'];
        $senha = $_POST['senha'];

        if (strlen($nome) < 3) {
            $_SESSION['message'] = ['Nome muito curto!', 'fail'];
            $this->loadView('cadastrar');
            exit;
        }
        if (strlen($senha) < 3) {
            $_SESSION['message'] = ['Senha muito curta!', 'fail'];
            $this->loadView('cadastrar');
            exit;
        }

        try {
            $permissions = 0;
            if (($_SESSION['usuario_id'] ?? 0) == 1 && !empty($_POST['permissions'])) {
                $permissionsArray = array_map(function($n) { return (int)$n; }, $_POST['permissions']);
                $permissions = array_sum($permissionsArray);
            }

            $usuario = new Usuario(0, Cpf::unmaskCpf($cpf), $nome, password_hash($senha, PASSWORD_DEFAULT), '', null, 0, $permissions);

            if ($usuario->save()) {
                $_SESSION['message'] = ['Usuário cadastrado com sucesso!', 'success'];
                header('Location: ' . $_ENV['BASE_URL'] . '/usuarios');
                exit;
            }
        } catch (Exception $e) {
            $_SESSION['message'] = ['CPF inválido!', 'fail'];
            $this->loadView('cadastrar');
            exit;
        }
    }

    private function update(): void
    {
        $entityId = filter_var($_POST['entity_id'] ?? 0, FILTER_VALIDATE_INT);

        if (
            !$entityId ||
            ($_SESSION['usuario_id'] != $entityId && !(isAdmin() && $entityId != 1))
        ) {
            include '../src/404.php';
            exit;
        }

        $usuario = Usuario::getById($entityId);

        if (!empty($_POST['senha'])) {
            if (strlen($_POST['senha']) < 3) {
                $_SESSION['message'] = ['Senha muito curta!', 'fail'];
                $this->loadView('atualizar');
                exit;
            }
            $usuario->setSenha(password_hash($_POST['senha'], PASSWORD_DEFAULT));
        }

        if (($_SESSION['usuario_id'] == 1) && $entityId != 1) {
            $permissionsSum = array_sum(array_map(function($n) { return (int)$n; }, $_POST['permissions'] ?? [0]));
            $usuario->setPermissions($permissionsSum % 2 == 1 ? 1 : $permissionsSum);
        }

        if ($usuario->save()) {
            $_SESSION['message'] = ['Usuário atualizado com sucesso!', 'success'];
            header('Location: ' . $_ENV['BASE_URL'] . '/usuarios/detalhar/' . $entityId);
            exit;
        }
    }

    private function unable(): void
    {
        $u = Usuario::getById($_POST['usuario_id']);
        if ($u->getId() == 1) {
            include '../src/404.php';
            exit;
        }

        $u->setEnabled(0);

        if ($u->save()) {
            Logger::log_unable(Usuario::$tableName, $u->getId(), $_SESSION['usuario_id']);
            $_SESSION['message'] = ['Usuário inativado com sucesso!', 'success'];
            header('Location: ' . $_ENV['BASE_URL'] . '/usuarios/detalhar/' . $u->getId());
            exit;
        }
    }

    private function enable(): void
    {
        $u = Usuario::getById($_POST['usuario_id']);
        if ($u->getId() == 1) {
            include '../src/404.php';
            exit;
        }

        $u->setEnabled(1);

        if ($u->save()) {
            Logger::log_enable(Usuario::$tableName, $u->getId(), $_SESSION['usuario_id']);
            $_SESSION['message'] = ['Usuario ativado com sucesso!', 'success'];
            header('Location: ' . $_ENV['BASE_URL'] . '/usuarios/detalhar/' . $u->getId());
            exit;
        }
    }

    private function loadView(string $view): void
    {
        $filters = $_SESSION['usuarios_filters'] ?? [
            'search' => '',
            'mostrar' => 'todos'
        ];

        $this->search = $filters['search'];
        $this->mostrar = $filters['mostrar'];

        unset($_SESSION['usuarios_filters']);

        if (!in_array($view, $this->views)) {
            include '../src/404.php';
            exit;
        }

        if (isset($this->id)) {
            $usuario = Usuario::getById($this->id);

            if ($view == 'detalhar') {
                $logs = Database::getLog(Usuario::$tableName, $this->id);
            }
        }

        if ($view == 'index') {

            $enabled = $this->mostrar != 'inativados';

            $usuarios = Usuario::getAll($enabled, $this->search);
        }

        if ($view == 'cadastrar' && !isAdmin()) {
            include '../src/404.php';
            exit;
        }

        if (
            $view == 'atualizar' && (
                !isset($this->id) ||
                ($_SESSION['usuario_id'] != $usuario->getId() &&
                    !(isAdmin() && $usuario->getId() != 1))
            )
        ) {
            include '../src/404.php';
            exit;
        }

        include '../src/templates/header.php';
        if ($view == 'cadastrar' || $view == 'atualizar') {
            include '../src/Views/usuarios/form.php';
        } elseif ($view == 'index') {
            include '../src/Views/cadastros/index.php';
            include '../src/Views/usuarios/index.php';
        } else {
            include "../src/Views/usuarios/$view.php";
        }
        include '../src/templates/footer.php';
    }
}
