<?php

namespace App\Controllers;

use App\Controllers\Services\Logger;
use App\Models\Database;
use App\Models\Natureza;
use App\Models\Conta;

class Naturezas
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
                $_SESSION['message'] = ['Natureza não encontrada', 'fail'];
                header('Location: ' . $_ENV['BASE_URL'] . '/naturezas');
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
                    $_SESSION['naturezas_filters'] = [
                        'search' => $_POST['search'],
                        'enabled' => $_POST['enabled']
                    ];
                    header('Location: ' . $_ENV['BASE_URL'] . '/naturezas');
                    exit;
                    break;
            }
        }

        $this->loadView($view);
    }

    private function create(): void
    {
        $nome = $_POST['nome'];
        if (trim($nome) == '') {
            $_SESSION['message'] = ['O nome não pode ser vazio', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/naturezas/cadastrar');
            exit;
        }

        $n = new Natureza(0, $nome);

        if ($n->save()) {
            $_SESSION['message'] = ['Natureza cadastrada com sucesso!', 'success'];
            header('Location: ' . $_ENV['BASE_URL'] . '/naturezas');
            exit;
        }
    }

    private function update(): void
    {
        $n = Natureza::getById($_POST['entity_id']);

        $nome = $_POST['nome'];

        if (trim($nome) == '') {
            $_SESSION['message'] = ['O nome não pode ser vazio', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/naturezas/cadastrar');
            exit;
        }
        $n->setNome($_POST['nome']);
        
        if ($n->save()) {
            $_SESSION['message'] = ['Natureza atualizada com sucesso!', 'success'];
            header('Location: ' . $_ENV['BASE_URL'] . '/naturezas/detalhar/' . $_POST['entity_id']);
            exit;
        }
    }

    private function unable(): void
    {
        $n = Natureza::getById($_POST['natureza_id']);
        if (!$n->setEnabled(0)) {
            $_SESSION['message'] = ['Não foi possível inativar a natureza pois ela possui contas a pagar.', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/naturezas/detalhar/' . $n->getId());
            exit;
        }

        if ($n->save()) {
            Logger::log_unable(Natureza::$tableName, $n->getId(), $_SESSION['usuario_id']);
            $_SESSION['message'] = ['Natureza inativada com sucesso!', 'success'];
            header('Location: ' . $_ENV['BASE_URL'] . '/naturezas/detalhar/' . $n->getId());
            exit;
        }
    }

    private function enable(): void
    {
        $n = Natureza::getById($_POST['natureza_id']);
        $n->setEnabled(1);

        if ($n->save()) {
            Logger::log_enable(Natureza::$tableName, $n->getId(), $_SESSION['usuario_id']);
            $_SESSION['message'] = ['Natureza ativada com sucesso!', 'success'];
            header('Location: ' . $_ENV['BASE_URL'] . '/naturezas/detalhar/' . $n->getId());
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
            $natureza = Natureza::getById($this->id);

            if ($view == 'detalhar') {
                $contas = Conta::getByForeignKey(Natureza::$tableName, $this->id);
                $logs = Database::getLog(Natureza::$tableName, $this->id);
            }
        }

        if ($view == 'index') {

            $this->search = $_SESSION['naturezas_filters']['search'] ?? $this->search;
            $this->enabled = $_SESSION['naturezas_filters']['enabled'] ?? $this->enabled;

            unset($_SESSION['naturezas_filters']);

            $naturezas = Natureza::getAll($this->enabled, $this->search);
        }

        include '../src/templates/header.php';
        if ($view == 'cadastrar' || $view == 'atualizar') {
            include '../src/Views/naturezas/form.php';
        } elseif ($view == 'index') {
            include '../src/Views/cadastros/index.php';
            include '../src/Views/naturezas/index.php';
        } else {
            include "../src/Views/naturezas/$view.php";
        }
        include '../src/templates/footer.php';
    }
}
