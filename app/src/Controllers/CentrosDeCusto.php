<?php

namespace App\Controllers;

use App\Controllers\Services\Logger;
use App\Models\Database;
use App\Models\CentroDeCusto;
use App\Models\Conta;
use RuntimeException;

class CentrosDeCusto
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
                $_SESSION['message'] = ['Centro de custo não encontrado', 'fail'];
                header('Location: ' . $_ENV['BASE_URL'] . '/centros-de-custo');
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
                    $_SESSION['centrosDeCusto_filters'] = [
                        'search' => $_POST['search'],
                        'enabled' => $_POST['enabled']
                    ];
                    header('Location: ' . $_ENV['BASE_URL'] . '/centros-de-custo');
                    exit;
                    break;
            }
        }

        $this->loadView($view);
    }

    private function create(): void
    {
        try {
            $categoria_id = $this->resolveCategoriaId(null, 0);
        } catch (RuntimeException $e) {
            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
                exit;
            }

            $_SESSION['message'] = [$e->getMessage(), 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/centros-de-custo/cadastrar');
            exit;
        }

        $cc = new CentroDeCusto(0, $_POST['nome'], '', null, 0, $categoria_id);

        if ($cc->save()) {
            if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                echo json_encode(['success' => true, 'id' => $cc->getId(), 'nome' => $cc->getNome()]);
                $_SESSION['message'] = ['Centro de custo cadastrado com sucesso!', 'success'];
                exit;
            }

            $_SESSION['message'] = ['Centro de custo cadastrado com sucesso!', 'success'];
            header('Location: ' . $_ENV['BASE_URL'] . '/centros-de-custo');
            exit;
        } else {
             if (isset($_POST['ajax']) && $_POST['ajax'] == 'true') {
                echo json_encode(['success' => false, 'message' => 'Erro ao salvar']);
                exit;
            }
        }
    }

    private function update(): void
    {
        $cc = CentroDeCusto::getById($_POST['entity_id']);

        try {
            $categoria_id = $this->resolveCategoriaId($cc->getCategoriaId(), $cc->getId());
        } catch (RuntimeException $e) {
            $_SESSION['message'] = [$e->getMessage(), 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/centros-de-custo/atualizar/' . $cc->getId());
            exit;
        }

        $cc->setNome($_POST['nome']);
        $cc->setCategoriaId($categoria_id);

        if ($cc->save()) {
            $_SESSION['message'] = ['Centro de custo atualizado com sucesso!', 'success'];
            header('Location: ' . $_ENV['BASE_URL'] . '/centros-de-custo/detalhar/' . $_POST['entity_id']);
            exit;
        }
    }

    /**
     * Resolve o centro de custo superior informado no POST. Aceita apenas um centro de
     * custo raiz, ativo e diferente da propria entidade, para nunca gravar uma
     * hierarquia invalida (por exemplo um sub-centro apontando para si mesmo).
     * Campo ausente no POST mantem o valor atual, assim renomear um centro de custo
     * nunca o move na hierarquia.
     *
     * @param int|null $categoriaIdAtual valor atual, usado quando o campo nao veio no POST
     * @param int $idDaEntidade id do centro de custo sendo salvo, 0 ao criar
     * @return int|null null quando nao ha centro de custo superior
     * @throws RuntimeException quando o valor informado nao e um centro de custo raiz valido
     */
    private function resolveCategoriaId($categoriaIdAtual, int $idDaEntidade): int|null
    {
        if (!array_key_exists('categoria_id', $_POST)) {
            return $categoriaIdAtual;
        }

        $categoriaId = filter_var($_POST['categoria_id'], FILTER_VALIDATE_INT);

        if (!$categoriaId) {
            throw new RuntimeException('Centro de custo superior inválido');
        }

        if ($categoriaId === $idDaEntidade) {
            throw new RuntimeException('Um centro de custo não pode ser o centro de custo superior de si mesmo');
        }

        foreach (CentroDeCusto::getRoots() as $raiz) {
            if ((int)$raiz['id'] === $categoriaId) {
                return $categoriaId;
            }
        }

        throw new RuntimeException('O centro de custo superior informado não existe, está inativo ou não é um centro de custo raiz');
    }

    private function unable(): void
    {
        $cc = CentroDeCusto::getById($_POST['centro_de_custo_id']);
        if (!$cc->setEnabled(0)) {
            $_SESSION['message'] = ['Não foi possível inativar o centro de custo pois ele possui contas a pagar.', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/centros-de-custo/detalhar/' . $cc->getId());
            exit;
        }

        if ($cc->save()) {
            Logger::log_unable(CentroDeCusto::$tableName, $cc->getId(), $_SESSION['usuario_id']);
            $_SESSION['message'] = ['Centro de custo inativado com sucesso!', 'success'];
            header('Location: ' . $_ENV['BASE_URL'] . '/centros-de-custo/detalhar/' . $cc->getId());
            exit;
        }
    }

    private function enable(): void
    {
        $cc = CentroDeCusto::getById($_POST['centro_de_custo_id']);
        $cc->setEnabled(1);

        if ($cc->save()) {
            Logger::log_enable(CentroDeCusto::$tableName, $cc->getId(), $_SESSION['usuario_id']);
            $_SESSION['message'] = ['Centro de custo ativado com sucesso!', 'success'];
            header('Location: ' . $_ENV['BASE_URL'] . '/centros-de-custo/detalhar/' . $cc->getId());
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
            $centro_de_custo = CentroDeCusto::getById($this->id);

            if ($view == 'detalhar') {
                $contas = Conta::getByForeignKey(CentroDeCusto::$tableName, $this->id);
                $logs = Database::getLog(CentroDeCusto::$tableName, $this->id);
                $subCentros = $centro_de_custo->getSubCentros();
            }
        }

        if ($view == 'index') {

            $filters = $_SESSION['centrosDeCusto_filters'] ?? [
                'search' => '',
                'orderby' => 'total',
                'mostrar' => ''
            ];

            $this->search = $_SESSION['centrosDeCusto_filters']['search'] ?? $this->search;
            $this->enabled = $_SESSION['centrosDeCusto_filters']['enabled'] ?? $this->enabled;

            unset($_SESSION['centrosDeCusto_filters']);

            $centrosDeCusto = CentroDeCusto::getAll($this->enabled, $this->search);
        }

        include '../src/templates/header.php';
        if ($view == 'cadastrar' || $view == 'atualizar') {
            $categorias = CentroDeCusto::getRoots();
            include "../src/Views/centros-de-custo/form.php";
        } elseif ($view == 'index') {
            include '../src/Views/cadastros/index.php';
            include '../src/Views/centros-de-custo/index.php';
        } else {
            include "../src/Views/centros-de-custo/$view.php";
        }
        include '../src/templates/footer.php';
    }
}
