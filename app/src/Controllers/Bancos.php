<?php

namespace App\Controllers;

use App\Controllers\Services\Logger;
use App\Controllers\Services\Money;
use App\Models\Database;
use App\Models\Banco;
use App\Models\Parcela;
use App\Models\Deposito;
use App\Models\CentroDeCusto;
use App\Models\Fornecedor;
use App\Models\Natureza;
use Throwable;

class Bancos
{
    public static bool $needLogin = true;
    public static bool $onlyAdmin = false;
    private array $views = ['index', 'cadastrar', 'detalhar', 'atualizar', 'depositar'];
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
                $_SESSION['message'] = ['Conta bancária não encontrada', 'fail'];
                header('Location: ' . $_ENV['BASE_URL'] . '/bancos');
                exit;
            }
        }

        if (isset($_GET['format']) && $_GET['format'] === 'json' && isset($_GET['deposito_id'])) {
            onlyAdmin();
            $this->getDepositoJson();
            exit;
        }

        if (!empty($_POST)) {
            switch ($_POST['type']) {
                case 'create':
                    onlyAdmin();
                    $this->create();
                    break;
                case 'update':
                    onlyAdmin();
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
                case 'depositar':
                    $this->depositar();
                    break;
                case 'editarDeposito':
                    $this->editarDeposito();
                    break;
                case 'deletarDeposito':
                    onlyAdmin();
                    $this->deletarDeposito();
                    break;
                case 'search':
                    $_SESSION['bancos_filters'] = [
                        'search' => $_POST['search'],
                        'enabled' => $_POST['enabled']
                    ];
                    header('Location: ' . $_ENV['BASE_URL'] . '/bancos');
                    exit;
                    break;
            }
        }

        $this->loadView($view);
    }

    private function create(): void
    {
        $saldoDeAbertura = Money::reais_para_centavos($_POST['saldo-abertura']);

        $b = new Banco(0, $_POST['nome'], $saldoDeAbertura, '', null, 0);

        if ($b->save()) {
            $d = new Deposito(0, $_SESSION['usuario_id'], $b->lastInsertId, $saldoDeAbertura, 'Saldo de abertura', '');
            $d->save();
            $_SESSION['message'] = ['Banco cadastrado com sucesso!', 'success'];
            header('Location: ' . $_ENV['BASE_URL'] . '/bancos');
            exit;
        }
    }

    private function update(): void
    {
        $b = Banco::getById($_POST['entity_id']);
        $b->setNome($_POST['nome']);

        if ($b->save()) {
            $_SESSION['message'] = ['Banco atualizado com sucesso!', 'success'];
            header('Location: ' . $_ENV['BASE_URL'] . '/bancos/detalhar/' . $_POST['entity_id']);
            exit;
        }
    }

    private function unable(): void
    {
        $b = Banco::getById($_POST['banco_id']);
        $b->setEnabled(0);

        if ($b->save()) {
            Logger::log_unable(Banco::$tableName, $b->getId(), $_SESSION['usuario_id']);
            $_SESSION['message'] = ['Banco inativado com sucesso!', 'success'];
            header('Location: ' . $_ENV['BASE_URL'] . '/bancos/detalhar/' . $b->getId());
            exit;
        }
    }

    private function enable(): void
    {
        $b = Banco::getById($_POST['banco_id']);
        $b->setEnabled(1);

        if ($b->save()) {
            Logger::log_enable(Banco::$tableName, $b->getId(), $_SESSION['usuario_id']);
            $_SESSION['message'] = ['Banco ativado com sucesso!', 'success'];
            header('Location: ' . $_ENV['BASE_URL'] . '/bancos/detalhar/' . $b->getId());
            exit;
        }
    }



    private function depositar(): void
    {
        $valor = Money::reais_para_centavos($_POST['valor']);

        if ($valor <= 0) {
            $_SESSION['message'] = ['O valor do depósito deve ser maior que zero', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/bancos/depositar/' . $_POST['banco_id']);
            exit;
        }

        if ($valor > Deposito::$LIMITE_DEPOSITO) {
            $_SESSION['message'] = ['O limite para depósitos é de R$ ' . Money::centavos_para_reais(Deposito::$LIMITE_DEPOSITO), 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/bancos/depositar/' . $_POST['banco_id']);
            exit;
        }

        $banco_id = $_POST['banco_id'];
        $historico = $_POST['historico'];
        if (strlen($historico) > 255) {
            $_SESSION['message'] = ['Histórico muito longo!', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/bancos/detalhar/' . $banco_id);
            exit;
        }

        $conn = Database::getConnection();
        $conn->beginTransaction();

        try {
            $banco = Banco::getById($banco_id);
            $banco->setSaldo_em_centavos($banco->getSaldo_em_centavos() + $valor);
            $banco->save();

            $dataDeposito = $_POST['data'] ?? date('Y-m-d');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataDeposito)) {
                $dataDeposito = date('Y-m-d');
            }
            $d = new Deposito(0, $_SESSION['usuario_id'], $banco_id, $valor, $historico, $dataDeposito);

            if (!$d->save()) {
                throw new \Exception('Falha ao registrar o depósito');
            }

            $conn->commit();

            $_SESSION['message'] = ['Depósito realizado com sucesso!', 'success'];
            $this->redirectToDetalhar((int) $banco_id, substr($dataDeposito, 0, 7));
        } catch (\Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            Logger::error('Erro ao realizar depósito', ['banco_id' => $banco_id, 'erro' => $e->getMessage()]);
            $_SESSION['message'] = ['Erro ao realizar o depósito, favor contatar o desenvolvedor do sistema.', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/bancos/detalhar/' . $banco_id);
            exit;
        }
    }

    private function editarDeposito(): void
    {
        $valor = Money::reais_para_centavos($_POST['valor']);
        $mes = (string) ($_POST['mes'] ?? '');

        if ($valor > Deposito::$LIMITE_DEPOSITO) {
            $_SESSION['message'] = ['O limite para depósitos é de R$ ' . Money::centavos_para_reais(Deposito::$LIMITE_DEPOSITO), 'fail'];
            $this->redirectToDetalhar((int) $_POST['banco_id'], $mes);
        }

        $depositoId = $_POST['deposito_id'];
        $bancoId = $_POST['banco_id'];
        $historico = $_POST['historico'];
        if (strlen($historico) > 255) {
            $_SESSION['message'] = ['Histórico muito longo!', 'fail'];
            $this->redirectToDetalhar((int) $bancoId, $mes);
        }

        $conn = Database::getConnection();
        $conn->beginTransaction();

        try {
            $banco = Banco::getById($bancoId);
            $deposito = Deposito::getById($depositoId);

            $valorOld = $deposito->getValorEmCentavos();
            $deposito->setValorEmCentavos($valor);
            $deposito->setHistorico($historico);
            $dataDeposito = $_POST['data'] ?? null;
            if ($dataDeposito && preg_match('/^\d{4}-\d{2}-\d{2}/', $dataDeposito)) {
                $deposito->setDataDeposito($dataDeposito);
            }
            $deposito->save();

            $banco->setSaldo_em_centavos($banco->getSaldo_em_centavos() + $valor - $valorOld);
            $banco->save();

            $conn->commit();

            $_SESSION['message'] = ['Depósito atualizado com sucesso!', 'success'];
            $this->redirectToDetalhar((int) $bancoId, $mes);
        } catch (\Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            Logger::error('Erro ao realizar atualizar depósito', ['banco_id' => $bancoId, 'deposito_id' => $depositoId, 'erro' => $e->getMessage()]);
            $_SESSION['message'] = ['Erro ao atualizar depósito', 'fail'];
            $this->redirectToDetalhar((int) $bancoId, $mes);
        }
    }

    /**
     * Exclui um depósito do banco: apaga o registro da tabela deposito e
     * subtrai o valor do saldo do banco correspondente.
     */
    private function deletarDeposito(): void
    {
        $depositoId = filter_var($_POST['deposito_id'] ?? 0, FILTER_VALIDATE_INT);
        $bancoId = filter_var($_POST['banco_id'] ?? 0, FILTER_VALIDATE_INT);
        $mes = (string) ($_POST['mes'] ?? '');

        if (!$depositoId || !$bancoId) {
            $_SESSION['message'] = ['Depósito inválido', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/bancos');
            exit;
        }

        $conn = Database::getConnection();
        $conn->beginTransaction();

        try {
            $deposito = Deposito::getById($depositoId);

            if ((int)$deposito->getBancoId() !== $bancoId) {
                throw new \Exception('Depósito não pertence ao banco informado');
            }

            $valor = $deposito->getValorEmCentavos();

            $banco = Banco::getById($bancoId);
            $banco->setSaldo_em_centavos($banco->getSaldo_em_centavos() - $valor);
            $banco->save();

            if (!$deposito->delete()) {
                throw new \Exception('Falha ao excluir o depósito');
            }

            Logger::log(
                Banco::$tableName,
                'deposito #' . $depositoId . ' excluído',
                Money::centavos_para_reais($valor),
                '-',
                $bancoId,
                $_SESSION['usuario_id']
            );

            $conn->commit();

            $_SESSION['message'] = ['Depósito excluído com sucesso!', 'success'];
        } catch (Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            Logger::error('Erro ao excluir depósito', ['banco_id' => $bancoId, 'deposito_id' => $depositoId, 'erro' => $e->getMessage()]);
            $_SESSION['message'] = ['Erro ao excluir o depósito, favor contatar o desenvolvedor do sistema.', 'fail'];
        }

        $this->redirectToDetalhar((int) $bancoId, $mes);
    }

    private function getDepositoJson(): void
    {
        header('Content-Type: application/json');
        $depositoId = filter_var($_GET['deposito_id'], FILTER_VALIDATE_INT);
        if (!$depositoId) {
            http_response_code(400);
            echo json_encode(['error' => 'ID de depósito inválido']);
            return;
        }

        $deposito = Deposito::getById($depositoId);
        echo json_encode([
            'id' => $deposito->getId(),
            'banco_id' => $deposito->getBancoId(),
            'valor_em_centavos' => $deposito->getValorEmCentavos(),
            'historico' => $deposito->getHistorico(),
            'data_deposito' => $deposito->getDataDeposito(),
        ]);
    }

    private static function rotuloMes(string $mes): string
    {
        $nomes = [
            1 => 'janeiro',
            'fevereiro',
            'março',
            'abril',
            'maio',
            'junho',
            'julho',
            'agosto',
            'setembro',
            'outubro',
            'novembro',
            'dezembro'
        ];

        [$ano, $numero] = array_pad(explode('-', $mes), 2, '');

        return ($nomes[(int) $numero] ?? $mes) . '/' . $ano;
    }

    private function redirectToDetalhar(int $bancoId, string $mes = ''): void
    {
        $url = $_ENV['BASE_URL'] . '/bancos/detalhar/' . $bancoId;

        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mes)) {
            $url .= '?mes=' . urlencode($mes);
        }

        header('Location: ' . $url);
        exit;
    }

    private function loadView(string $view): void
    {
        if (!in_array($view, $this->views)) {
            include '../src/404.php';
            exit;
        }

        if (isset($this->id)) {
            $banco = Banco::getById($this->id);

            if ($view == 'detalhar') {
                $logs = Database::getLog(Banco::$tableName, $this->id);

                $mesAtual = date('Y-m');
                $meses = array_values(array_unique(array_merge(
                    Deposito::getMonthsByBank($this->id),
                    Parcela::getPaidMonthsByBank($this->id),
                    [$mesAtual]
                )));
                rsort($meses);

                $mesParam = $_GET['mes'] ?? $mesAtual;
                $mesSelecionado = $mesAtual;

                if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mesParam)) {
                    $mesSelecionado = $mesParam;
                    $startDate = $mesParam . '-01';
                    $endDate = date('Y-m-t', strtotime($startDate));
                } else {
                    $startDate = $mesAtual . '-01';
                    $endDate = date('Y-m-t', strtotime($startDate));
                }

                $parcelas = Parcela::getPaidByBankBetween($this->id, $startDate, $endDate);
                $depositos = Deposito::getByBankBetween($this->id, $startDate, $endDate);

                if (!in_array($mesSelecionado, $meses, true)) {
                    $meses[] = $mesSelecionado;
                    rsort($meses);
                }

                $periodoLabel = self::rotuloMes($mesSelecionado);

                $mesesOpcoes = [];
                foreach ($meses as $mes) {
                    $mesesOpcoes[] = [
                        'valor' => $mes,
                        'rotulo' => self::rotuloMes($mes) . ($mes === $mesAtual ? ' (atual)' : '')
                    ];
                }

                $centros = CentroDeCusto::getOptionsWithHierarchy();
                $availableFilterOptions = [
                    'fornecedores' => Database::getOptions(Fornecedor::$tableName),
                    'naturezas' => Database::getOptions(Natureza::$tableName),
                    'centros' => Database::getOptions(CentroDeCusto::$tableName),
                ];
                $bancos = Database::getOptions(Banco::$tableName);
            }
        }

        if ($view == 'index') {
            $this->search = $_SESSION['bancos_filters']['search'] ?? $this->search;
            $this->enabled = $_SESSION['bancos_filters']['enabled'] ?? $this->enabled;

            unset($_SESSION['bancos_filters']);

            $bancos = Banco::getAll($this->enabled, $this->search);
            $bancosOpcoes = Banco::getAll(true, '');
        }

        if ($view == 'cadastrar' || $view == 'atualizar') {
            onlyAdmin();
        }

        include '../src/templates/header.php';
        if ($view == 'cadastrar' || $view == 'atualizar') {
            include "../src/Views/bancos/form.php";
        } else {
            include "../src/Views/bancos/$view.php";
        }
        include '../src/templates/footer.php';
    }
}
