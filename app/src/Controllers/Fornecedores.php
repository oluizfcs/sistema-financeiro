<?php

namespace App\Controllers;

use App\Controllers\Services\Logger;
use App\Models\Database;
use App\Models\Fornecedor;
use App\Models\Conta;

class Fornecedores
{
    public static bool $needLogin = true;
    public static bool $onlyAdmin = true;
    private array $views = ['index', 'cadastrar', 'detalhar', 'atualizar'];
    private int $id;
    private string $search = '';
    private bool $enabled = true;

    function __construct(string $view = 'index', string $param = '')
    {
        if (strlen($param) > 0) {
            $id = filter_var($param, FILTER_VALIDATE_INT);
            if ($id) {
                $this->id = $id;
            } else {
                $_SESSION['message'] = ['Fornecedor não encontrado', 'fail'];
                header('Location: ' . $_ENV['BASE_URL'] . '/fornecedores');
                exit;
            }
        }

        if (!empty($_POST)) {
            switch ($_POST['type']) {
                case 'create':
                    $this->create();
                    break;
                case 'update':
                    $this->update();
                    break;
                case 'unable':
                    $this->unable();
                    break;
                case 'enable':
                    $this->enable();
                    break;
                case 'search':
                    $_SESSION['fornecedores_filters'] = [
                        'search' => $_POST['search'],
                        'enabled' => $_POST['enabled']
                    ];
                    header('Location: ' . $_ENV['BASE_URL'] . '/fornecedores');
                    exit;
                    break;
            }
        }

        $this->loadView($view);
    }

    private function create(): void
    {
        $telefone = $_POST['telefone'];
        if (trim($telefone) == '') {
            $telefone = null;
        }

        $f = new Fornecedor(0, $_POST['nome'], $telefone, '', null, 0);

        if ($f->save()) {
            $_SESSION['message'] = ['Fornecedor cadastrado com sucesso!', 'success'];
            header('Location: ' . $_ENV['BASE_URL'] . '/fornecedores');
            exit;
        }
    }

    private function update(): void
    {
        $f = Fornecedor::getById($_POST['entity_id']);
        $f->setNome($_POST['nome']);
        $telefone = $_POST['telefone'];
        if (trim($telefone) == '') {
            $telefone = null;
        }
        $f->setTelefone($telefone);

        if ($f->save()) {
            $_SESSION['message'] = ['Fornecedor atualizado com sucesso!', 'success'];
            header('Location: ' . $_ENV['BASE_URL'] . '/fornecedores/detalhar/' . $_POST['entity_id']);
            exit;
        }
    }

    private function unable(): void
    {
        $f = Fornecedor::getById($_POST['fornecedor_id']);
        if (!$f->setEnabled(0)) {
            $_SESSION['message'] = ['Não foi possível inativar o fornecedor pois ele possui contas a pagar.', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/fornecedores/detalhar/' . $f->getId());
            exit;
        }

        if ($f->save()) {
            Logger::log_unable(Fornecedor::$tableName, $f->getId(), $_SESSION['usuario_id']);
            $_SESSION['message'] = ['Fornecedor inativado com sucesso!', 'success'];
            header('Location: ' . $_ENV['BASE_URL'] . '/fornecedores/detalhar/' . $f->getId());
            exit;
        }
    }

    private function enable(): void
    {
        $f = Fornecedor::getById($_POST['fornecedor_id']);
        $f->setEnabled(1);

        if ($f->save()) {
            Logger::log_enable(Fornecedor::$tableName, $f->getId(), $_SESSION['usuario_id']);
            $_SESSION['message'] = ['Fornecedor ativado com sucesso!', 'success'];
            header('Location: ' . $_ENV['BASE_URL'] . '/fornecedores/detalhar/' . $f->getId());
            exit;
        }
    }

    private function loadView(string $view): void
    {
        if (!in_array($view, $this->views)) {
            include '../src/404.php';
            exit;
        }

        if (isset($this->id)) {
            $fornecedor = Fornecedor::getById($this->id);

            if ($view == 'detalhar') {
                $contas = Conta::getByForeignKey(Fornecedor::$tableName, $this->id);
                $logs = Database::getLog(Fornecedor::$tableName, $this->id);
            }
        }

        if ($view == 'index') {

            $this->search = $_SESSION['fornecedores_filters']['search'] ?? $this->search;
            $this->enabled = $_SESSION['fornecedores_filters']['enabled'] ?? $this->enabled;

            unset($_SESSION['fornecedores_filters']);

            $fornecedores = Fornecedor::getAll($this->enabled, $this->search);
        }

        include '../src/templates/header.php';
        if ($view == 'cadastrar' || $view == 'atualizar') {
            include '../src/Views/fornecedores/form.php';
        } elseif ($view == 'index') {
            include '../src/Views/cadastros/index.php';
            include '../src/Views/fornecedores/index.php';
        } else {
            include "../src/Views/fornecedores/$view.php";
        }
        include '../src/templates/footer.php';
    }
}
