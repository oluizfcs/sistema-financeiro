<?php

namespace App\Controllers;

use App\Controllers\Services\Logger;
use App\Models\Database;
use PDO;
use PDOException;

class Admin
{
    public static bool $needLogin = true;
    public static bool $onlyAdmin = true;
    private array $views = ['index'];
    private string $search = '';
    private string $entity = 'todas';
    private string $user = 'todos';
    private string $startDate = '';
    private string $endDate = '';

    private const ENTITIES = [
        'usuario' => ['label' => 'Usuário', 'route' => 'usuarios'],
        'banco' => ['label' => 'Banco', 'route' => 'bancos'],
        'fornecedor' => ['label' => 'Fornecedor', 'route' => 'fornecedores'],
        'natureza' => ['label' => 'Natureza', 'route' => 'naturezas'],
        'centro_de_custo' => ['label' => 'Centro de custo', 'route' => 'centros-de-custo'],
        'conta' => ['label' => 'Conta', 'route' => 'contas'],
        'deposito' => ['label' => 'Depósito', 'route' => 'bancos'],
    ];

    function __construct(string $view = 'index', string $param = '')
    {
        if (!empty($_POST)) {
            switch ($_POST['type']) {
                case 'search_json':
                    $this->searchJson();
                    break;
                case 'search':
                    $filters = [
                        'search' => trim($_POST['search'] ?? ''),
                        'entity' => array_key_exists($_POST['entity'] ?? '', self::ENTITIES) ? $_POST['entity'] : 'todas',
                        'user' => filter_var($_POST['user'] ?? '', FILTER_VALIDATE_INT) !== false ? (string)(int)$_POST['user'] : 'todos',
                        'filter_start_date' => $_POST['filter_start_date'] ?? date('Y-m-01'),
                        'filter_end_date' => $_POST['filter_end_date'] ?? date('Y-m-t')
                    ];

                    if (isset($_POST['clean']) && $_POST['clean'] == '1') {
                        $filters = [
                            'search' => '',
                            'entity' => 'todas',
                            'user' => 'todos',
                            'filter_start_date' => date('Y-m-01'),
                            'filter_end_date' => date('Y-m-t')
                        ];
                    }

                    $_SESSION['admin_filters'] = $filters;

                    header('Location: ' . $_ENV['BASE_URL'] . '/admin');
                    exit;
                default:
                    break;
            }
        }

        $this->loadView($view);
    }

    private function loadView(string $view): void
    {
        if (!in_array($view, $this->views)) {
            include '../src/404.php';
            exit;
        }

        if ($view == 'index') {
            $filters = $_SESSION['admin_filters'] ?? [
                'search' => '',
                'entity' => 'todas',
                'user' => 'todos',
                'filter_start_date' => date('Y-m-01'),
                'filter_end_date' => date('Y-m-t')
            ];

            unset($_SESSION['admin_filters']);

            $this->search = $filters['search'] ?? '';
            $this->entity = array_key_exists($filters['entity'] ?? '', self::ENTITIES) ? $filters['entity'] : 'todas';
            $this->user = filter_var($filters['user'] ?? '', FILTER_VALIDATE_INT) !== false ? (string)(int)$filters['user'] : 'todos';
            $this->startDate = $this->validDate($filters['filter_start_date'] ?? '') ? $filters['filter_start_date'] : date('Y-m-01');
            $this->endDate = $this->validDate($filters['filter_end_date'] ?? '') ? $filters['filter_end_date'] : date('Y-m-t');

            $logs = $this->getLogs(
                $this->search,
                $this->entity,
                $this->user,
                $this->startDate,
                $this->endDate
            );

            $nomesReferencias = $this->nomesReferencias($logs);

            $users = Database::getConnection()->query('SELECT id, nome FROM usuario ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
            $entidades = self::ENTITIES;
            $changelog = require 'changelog.php';
        }

        include '../src/templates/header.php';
        include "../src/Views/admin/$view.php";
        include '../src/templates/footer.php';
    }

    private function searchJson(): void
    {
        header('Content-Type: application/json');

        $filters = [
            'search' => trim($_POST['search'] ?? ''),
            'entity' => array_key_exists($_POST['entity'] ?? '', self::ENTITIES) ? $_POST['entity'] : 'todas',
            'user' => filter_var($_POST['user'] ?? '', FILTER_VALIDATE_INT) !== false ? (string)(int)$_POST['user'] : 'todos',
            'filter_start_date' => $_POST['filter_start_date'] ?? date('Y-m-01'),
            'filter_end_date' => $_POST['filter_end_date'] ?? date('Y-m-t')
        ];

        $startDate = $this->validDate($filters['filter_start_date']) ? $filters['filter_start_date'] : date('Y-m-01');
        $endDate = $this->validDate($filters['filter_end_date']) ? $filters['filter_end_date'] : date('Y-m-t');

        $logs = $this->getLogs($filters['search'], $filters['entity'], $filters['user'], $startDate, $endDate);

        $nomesReferencias = $this->nomesReferencias($logs);

        $users = Database::getConnection()->query('SELECT id, nome FROM usuario ORDER BY nome')->fetchAll(PDO::FETCH_ASSOC);
        $nomesUsuarios = [];
        foreach ($users as $u) {
            $nomesUsuarios[$u['id']] = $u['nome'];
        }

        $entidades = self::ENTITIES;

        ob_start();
        include '../src/Views/admin/partials/_rows.php';
        $html = ob_get_clean();

        echo json_encode(['ok' => true, 'html' => $html]);
        exit;
    }

    private function validDate(string $date): bool
    {
        if (!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $date)) {
            return false;
        }
        [$ano, $mes, $dia] = array_map('intval', explode('-', $date));
        return checkdate($mes, $dia, $ano);
    }

    private function nomesReferencias(array $logs): array
    {
        $ids = [];

        foreach ($logs as $log) {
            if (!in_array($log['campo'], ['centro_de_custo_id', 'fornecedor_id', 'natureza_id'], true)) {
                continue;
            }
            $tipo = str_replace('_id', '', $log['campo']);
            foreach (['valor_antigo', 'valor_novo'] as $coluna) {
                $valor = $log[$coluna];
                if ($valor !== null && is_numeric($valor) && (int)$valor > 0) {
                    $ids[$tipo][(int)$valor] = true;
                }
            }
        }

        $conn = Database::getConnection();

        $nomes = ['centro_de_custo' => [], 'fornecedor' => [], 'natureza' => []];
        foreach ($ids as $tipo => $idSet) {
            if (empty($idSet)) {
                continue;
            }
            $lista = implode(',', array_keys($idSet));
            $rows = $conn->query("SELECT id, nome FROM {$tipo} WHERE id IN ({$lista})")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $linha) {
                $nomes[$tipo][(int)$linha['id']] = $linha['nome'];
            }
        }

        return $nomes;
    }

    private function getLogs(string $search, string $entity, string $user, string $startDate, string $endDate): array
    {
        $conn = Database::getConnection();

        $unions = [
            "SELECT 'usuario' AS entidade, id, campo, valor_antigo, valor_novo, data_log, usuario_id, usuario_editado_id AS entidade_id, NULL AS banco_destino FROM log_usuario",
            "SELECT 'banco' AS entidade, id, campo, valor_antigo, valor_novo, data_log, usuario_id, banco_id AS entidade_id, NULL AS banco_destino FROM log_banco",
            "SELECT 'fornecedor' AS entidade, id, campo, valor_antigo, valor_novo, data_log, usuario_id, fornecedor_id AS entidade_id, NULL AS banco_destino FROM log_fornecedor",
            "SELECT 'natureza' AS entidade, id, campo, valor_antigo, valor_novo, data_log, usuario_id, natureza_id AS entidade_id, NULL AS banco_destino FROM log_natureza",
            "SELECT 'centro_de_custo' AS entidade, id, campo, valor_antigo, valor_novo, data_log, usuario_id, centro_de_custo_id AS entidade_id, NULL AS banco_destino FROM log_centro_de_custo",
            "SELECT 'conta' AS entidade, id, campo, valor_antigo, valor_novo, data_log, usuario_id, conta_id AS entidade_id, NULL AS banco_destino FROM log_conta",
            "SELECT 'deposito' AS entidade, l.id, l.campo, l.valor_antigo, l.valor_novo, l.data_log, l.usuario_id, l.deposito_id AS entidade_id, d.banco_id AS banco_destino FROM log_deposito l LEFT JOIN deposito d ON d.id = l.deposito_id",
        ];

        $sql = 'SELECT * FROM (' . implode(' UNION ALL ', $unions) . ') logs WHERE 1 = 1';
        $params = [];

        if ($startDate !== '' && $endDate !== '') {
            $sql .= ' AND logs.data_log >= :data_inicio AND logs.data_log < DATE_ADD(:data_fim, INTERVAL 1 DAY)';
            $params[':data_inicio'] = $startDate;
            $params[':data_fim'] = $endDate;
        }

        if ($entity !== 'todas' && array_key_exists($entity, self::ENTITIES)) {
            $sql .= ' AND logs.entidade = :entidade';
            $params[':entidade'] = $entity;
        }

        if ($user !== 'todos') {
            $sql .= ' AND logs.usuario_id = :usuario';
            $params[':usuario'] = (int)$user;
        }

        if ($search !== '') {
            $conditions = [
                'logs.campo LIKE :pesquisa',
                'logs.valor_antigo LIKE :pesquisa',
                'logs.valor_novo LIKE :pesquisa'
            ];
            $params[':pesquisa'] = "%{$search}%";

            $s = mb_strtolower($search);
            if (str_contains($s, 'cadast')) {
                $conditions[] = "logs.campo = 'create'";
            }
            if (str_contains($s, 'inativ')) {
                $conditions[] = "logs.campo = 'unable'";
            }
            if (str_contains($s, 'ativ')) {
                $conditions[] = "logs.campo = 'enable'";
            }
            if (str_contains($s, 'pag')) {
                $conditions[] = "(logs.campo = 'pay' OR logs.campo LIKE 'Parcela%')";
            }
            if (str_contains($s, 'estorn')) {
                $conditions[] = "(logs.campo LIKE '%estorn%' OR logs.campo LIKE '%estornadas%')";
            }
            if (str_contains($s, 'parcela')) {
                $conditions[] = "logs.campo LIKE '%Parcela%'";
            }

            $sql .= ' AND (' . implode(' OR ', $conditions) . ')';
        }

        $sql .= ' ORDER BY logs.data_log DESC, logs.id DESC';

        try {
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Logger::error('Falha ao listar logs da administração', ['PDOException' => $e->getMessage()]);
            $_SESSION['message'] = ['Não foi possível listar os logs.', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/admin');
            exit;
        }
    }
}