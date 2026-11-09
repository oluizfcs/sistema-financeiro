<?php

namespace App\Controllers;

use App\Controllers\Services\Logger;
use App\Controllers\Services\Money;
use App\Controllers\Services\PagamentoException;
use App\Controllers\Services\PagamentoService;
use App\Models\Banco;
use App\Models\CentroDeCusto;
use App\Models\Database;
use App\Models\Deposito;
use App\Models\Conta;
use App\Models\Fornecedor;
use App\Models\Natureza;
use App\Models\Parcela;
use App\Models\ParcelaPagamento;
use App\Models\Permissions;
use App\Models\Usuario;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;

class Contas
{
    public static bool $needLogin = true;
    public static bool $onlyAdmin = false;
    private array $views = ['index', 'detalhar'];
    private const ATALHOS_DE_FILTRO = ['atrasadas', 'hoje', 'semana'];
    private int $id;

    function __construct(string $view = 'index', string $param = '')
    {
        if (in_array($view, self::ATALHOS_DE_FILTRO, true)) {
            $this->aplicarAtalhoDeFiltro($view);
            header('Location: ' . $_ENV['BASE_URL'] . '/contas');
            exit;
        }
        if (strlen($param) > 0) {
            $id = filter_var($param, FILTER_VALIDATE_INT);
            if ($id) {
                $this->id = $id;
            } else {
                $_SESSION['message'] = ['Conta não encontrada', 'fail'];
                header('Location: ' . $_ENV['BASE_URL'] . '/contas');
                exit;
            }
        }
        if (!empty($_POST)) {
            switch ($_POST['type']) {
                case 'create':
                    $this->create();
                    break;
                case 'unable':
                    $this->unable();
                    break;
                case 'estorno':
                    $this->estorno();
                    break;
                case 'enable':
                    $this->enable();
                    break;
                case 'search':
                    $_SESSION['contas_filters'] = self::normalizarFiltros($_POST);
                    header('Location: ' . $_ENV['BASE_URL'] . '/contas');
                    exit;
                    break;
                case 'search_json':
                    $this->searchJson();
                    break;
                case 'update':
                    $this->update();
                    break;
                case 'editarAtributos':
                    onlyAdmin();
                    $this->editarAtributos();
                    break;
                case 'cobrar':
                    $this->cobrar();
                    break;
            }
        }

        $this->loadView($view);
    }

    private static function normalizarFiltros(array $filtros): array
    {
        $status = $filtros['filter_status'] ?? 'a pagar';
        if (!in_array($status, ['a pagar', 'pagas', 'inativadas'], true)) {
            $status = 'a pagar';
        }

        $normalizarId = static function ($valor) {
            $id = filter_var($valor, FILTER_VALIDATE_INT);
            return $id === false ? 'all' : $id;
        };

        $normalizarData = static function ($valor, string $padrao) {
            if ($valor === null) {
                return $padrao;
            }
            $valor = (string) $valor;
            return preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) ? $valor : '';
        };

        return [
            'filter_status' => $status,
            'filter_natureza' => $normalizarId($filtros['filter_natureza'] ?? null),
            'filter_centro' => $normalizarId($filtros['filter_centro'] ?? null),
            'filter_fornecedor' => $normalizarId($filtros['filter_fornecedor'] ?? null),
            'filter_start_date' => $normalizarData($filtros['filter_start_date'] ?? null, date('Y-m-01')),
            'filter_end_date' => $normalizarData($filtros['filter_end_date'] ?? null, date('Y-m-t')),
        ];
    }

    private function restaurarFiltros(): void
    {
        if (isset($_SESSION['contas_filters_atual'])) {
            $_SESSION['contas_filters'] = $_SESSION['contas_filters_atual'];
        }
    }

    private function aplicarAtalhoDeFiltro(string $atalho): void
    {
        $hoje = date('Y-m-d');
        $filtros = ['filter_status' => 'a pagar'];

        if ($atalho === 'atrasadas') {
            $maisAntiga = Parcela::getOverdueTotals()['mais_antiga'] ?? null;
            $filtros['filter_start_date'] = $maisAntiga ?? date('Y-m-01');
            $filtros['filter_end_date'] = date('Y-m-d', strtotime('-1 day'));
        } elseif ($atalho === 'hoje') {
            $filtros['filter_start_date'] = $hoje;
            $filtros['filter_end_date'] = $hoje;
        } else {
            $filtros['filter_start_date'] = date('Y-m-d', strtotime('+1 day'));
            $filtros['filter_end_date'] = date('Y-m-d', strtotime('+7 days'));
        }

        $_SESSION['contas_filters'] = self::normalizarFiltros($filtros);
    }

    private function create(): void
    {
        $this->restaurarFiltros();

        $bancoId = filter_var($_POST['banco'] ?? -1, FILTER_VALIDATE_INT);
        $centroId = filter_var($_POST['centro'], FILTER_VALIDATE_INT);
        $naturezaId = filter_var($_POST['natureza'], FILTER_VALIDATE_INT);
        $fornecedorId = filter_var($_POST['fornecedor'], FILTER_VALIDATE_INT);
        $valorEmCentavos = Money::reais_para_centavos($_POST['valor_em_reais']);
        $qtdParcelas = filter_var($_POST['qtd_parcela'], FILTER_VALIDATE_INT);
        $historico = $_POST['historico'];
        $formaPagamento = $_POST['forma-pagamento'];
        $lancamentoAVista = ($_POST['lancamento_a_vista'] ?? '0') === '1';

        Natureza::getById($naturezaId);

        if ($lancamentoAVista) {
            if (!$bancoId || $bancoId < 1) {
                $_SESSION['message'] = ['Selecione uma conta bancária para o lançamento à vista', 'fail'];
                $_SESSION['post_data'] = $_POST;
                header('Location: ' . $_ENV['BASE_URL'] . '/contas');
                exit;
            }

            $banco = Banco::getById($bancoId); // if id does not exists (unlikely), execution stops.
            if (!$banco->isEnabled()) {
                $_SESSION['message'] = ['Não é possível pagar usando uma conta bancária inativa', 'fail'];
                $_SESSION['post_data'] = $_POST;
                header('Location: ' . $_ENV['BASE_URL'] . '/contas');
                exit;
            }
            $formaPagamento = 'a vista';
        } else {
            // O cadastro normal apenas lança a conta; nunca desconta de um banco.
            $bancoId = null;
        }

        if ($fornecedorId == -1) { // "Nenhum"
            $fornecedorId = null;
        } else {
            Fornecedor::getById($fornecedorId); // if id does not exists (unlikely), execution stops.
        }

        if ($valorEmCentavos == 0) {
            $_SESSION['message'] = ['O valor da conta não pode ser zero', 'fail'];
            $_SESSION['post_data'] = $_POST;
            header('Location: ' . $_ENV['BASE_URL'] . '/contas');
            exit;
        }

        if (trim($historico) == "") {
            $historico = "Histórico não informado.";
        }

        if (strlen(trim($historico)) >= 400) {
            $_SESSION['message'] = ['O histórico não pode exceder 400 caracteres', 'fail'];
            $_SESSION['post_data'] = $_POST;
            header('Location: ' . $_ENV['BASE_URL'] . '/contas');
            exit;
        }

        if (!$qtdParcelas) {
            $_SESSION['message'] = ['Quantidade de parcelas inválida', 'fail'];
            $_SESSION['post_data'] = $_POST;
            header('Location: ' . $_ENV['BASE_URL'] . '/contas');
            exit;
        }

        $parcelas = [];

        $hoje = new DateTimeImmutable('today');
        $tresDiasAtras = $hoje->modify('-3 days');

        switch ($formaPagamento) {
            case 'a vista':
                $dataPagamentoAVista = $_POST['dataParcelaAVista'] ?? '';
                if ($dataPagamentoAVista == '' || strlen($dataPagamentoAVista) != 10) {
                    $_SESSION['message'] = ['É necessário definir uma data de pagamento', 'fail'];
                    $_SESSION['post_data'] = $_POST;
                    header('Location: ' . $_ENV['BASE_URL'] . '/contas');
                    exit;
                }
                $dataPagamentoAVistaDateTime = new DateTime($dataPagamentoAVista);
                $dataPagamentoAVistaDateTime->setTime(0, 0, 0);
                if (($dataPagamentoAVistaDateTime < $tresDiasAtras) && !$_SESSION['usuario_is_admin']) {
                    $_SESSION['post_data'] = $_POST;
                    $_SESSION['post_data']['show_dialog_msg'] = ['Você não tem permissão para lançar contas mais que 3 dias no passado.', 'fail'];
                    header('Location: ' . $_ENV['BASE_URL'] . '/contas');
                    exit;
                }
                if($dataPagamentoAVistaDateTime > $hoje) {
                    if(!Permissions::userCan(Usuario::getById($_SESSION['usuario_id'])->getPermissions(), Permissions::CREATE_FUTURE_BILLS)) {
                        $_SESSION['message'] = ['Você não tem permissão para lançar contas no futuro.', 'fail'];
                        $_SESSION['post_data'] = $_POST;
                        header('Location: ' . $_ENV['BASE_URL'] . '/contas');
                        exit;
                    }
                }
                
                break;
            case 'parcelado':
                for ($i = 1; $i <= $qtdParcelas; $i++) {

                    $valorParcela = Money::reais_para_centavos($_POST["parcela$i" . "_valor"]);
                    $vencimentoParcela = (string) ($_POST["parcela$i" . "_vencimento"] ?? '');

                    if ($valorParcela == 0) {
                        $_SESSION['message'] = ['O valor das parcelas não está dividido corretamente', 'fail'];
                        $_SESSION['post_data'] = $_POST;
                        header('Location: ' . $_ENV['BASE_URL'] . '/contas');
                        exit;
                    }

                    if ($vencimentoParcela == '') {
                        $_SESSION['message'] = ['É necessário informar a data de vencimento das parcelas', 'fail'];
                        $_SESSION['post_data'] = $_POST;
                        header('Location: ' . $_ENV['BASE_URL'] . '/contas');
                        exit;
                    }

                    $dataVencimento = DateTimeImmutable::createFromFormat('!Y-m-d', $vencimentoParcela);

                    if (!$dataVencimento || $dataVencimento->format('Y-m-d') !== $vencimentoParcela) {
                        $_SESSION['message'] = ['Data de vencimento inválida', 'fail'];
                        $_SESSION['post_data'] = $_POST;
                        header('Location: ' . $_ENV['BASE_URL'] . '/contas');
                        exit;
                    }

                    if (!isAdmin() && $dataVencimento < $tresDiasAtras) {
                        $_SESSION['message'] = ['Você não tem permissão para cadastrar parcelas com vencimento há mais de 3 dias.', 'fail'];
                        $_SESSION['post_data'] = $_POST;
                        header('Location: ' . $_ENV['BASE_URL'] . '/contas');
                        exit;
                    }

                    $parcelas[] = ['valor' => $valorParcela, 'vencimento' => $vencimentoParcela];
                }
                break;
            default:
                $_SESSION['message'] = ['Forma de pagamento não identificada', 'fail'];
                $_SESSION['post_data'] = $_POST;
                header('Location: ' . $_ENV['BASE_URL'] . '/contas');
                exit;
        }

        if (CentroDeCusto::getById($centroId)->getCategoriaId() == null) {
            $_SESSION['message'] = ['Não é possível cadastrar contas diretamente em categorias de centro de custo, escolha um sub-centro de custo!', 'fail'];
            $_SESSION['post_data'] = $_POST;
            header('Location: ' . $_ENV['BASE_URL'] . '/contas');
            exit;
        }

        $c = new Conta(
            0,
            $historico,
            $valorEmCentavos,
            '',
            null,
            $centroId,
            $naturezaId,
            $fornecedorId,
            [],
            true,
            0
        );

        if ($c->save()) {
            $_SESSION['message'] = ['Conta cadastrada com sucesso!', 'success'];

            if (count($parcelas) > 0) {
                //parcelado
                Parcela::bulkInsert($parcelas, $c->lastInsertId);
            } else {
                //a vista
                new Parcela(0, 1, $valorEmCentavos, $dataPagamentoAVista, null, $c->lastInsertId, $bancoId, false)->save();
            }

            if ($formaPagamento == 'a vista' && $bancoId != null) {
                $this->cobrar(1, $bancoId, $c->lastInsertId, $dataPagamentoAVista, true);
                exit;
            }

            header('Location: ' . $_ENV['BASE_URL'] . '/contas');
            exit;
        }
    }

    private function unable(): void
    {
        $contaId = filter_var($_POST['conta_id'] ?? 0, FILTER_VALIDATE_INT);

        if (!isAdmin()) {
            $_SESSION['message'] = ['Somente o administrador pode estornar ou inativar contas.', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas' . ($contaId ? '/detalhar/' . $contaId : ''));
            exit;
        }

        $c = Conta::getById($contaId);

        try {
            $estorno = PagamentoService::estornarConta($c->getId(), (int) ($_SESSION['usuario_id'] ?? 0) ?: null);
        } catch (PagamentoException $e) {
            $_SESSION['message'] = [$e->getMessage(), 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas/detalhar/' . $c->getId());
            exit;
        }

        $c->setEnabled(0);

        $conn = Database::getConnection();
        $stmt = $conn->prepare('UPDATE parcela SET enabled = 0 WHERE conta_id = :conta_id');
        $stmt->execute([':conta_id' => $c->getId()]);

        $c->syncValorFromParcelas();

        Logger::log_unable(Conta::$tableName, $c->getId(), $_SESSION['usuario_id']);

        $msg = 'Conta inativada com sucesso!';
        if ($estorno['estornoCount'] > 0) {
            $valorFormatado = 'R$ ' . number_format($estorno['estornoTotal'] / 100, 2, ',', '.');
            $parcela = $estorno['estornoCount'] === 1 ? 'parcela estornada' : 'parcelas estornadas';
            $devolvida = $estorno['estornoCount'] === 1 ? 'devolvida ao respectivo banco' : 'devolvidas aos respectivos bancos';
            $msg .= " ({$estorno['estornoCount']} $parcela: $valorFormatado $devolvida.)";
        }

        $_SESSION['message'] = [$msg, 'success'];
        header('Location: ' . $_ENV['BASE_URL'] . '/contas/detalhar/' . $c->getId());
        exit;
    }

    private function estorno(): void
    {
        header('Content-Type: application/json');

        if (!isAdmin()) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'msg' => 'Somente o administrador pode estornar contas.']);
            exit;
        }

        $c = Conta::getById((int) ($_POST['conta_id'] ?? 0));

        try {
            $estorno = PagamentoService::estornarConta($c->getId(), (int) ($_SESSION['usuario_id'] ?? 0) ?: null);
        } catch (PagamentoException $e) {
            http_response_code(422);
            echo json_encode(['ok' => false, 'msg' => $e->getMessage()]);
            exit;
        }

        $c->syncValorFromParcelas();

        Logger::log(Conta::$tableName,  'parcelas estornadas', '-', '-', $c->getId(), $_SESSION['usuario_id']);

        echo json_encode([
            'ok' => true,
            'estornoCount' => $estorno['estornoCount'],
            'estornoTotal' => $estorno['estornoTotal'],
        ]);
        exit;
    }

    private function enable(): void
    {
        if (!isAdmin()) {
            $_SESSION['message'] = ['Somente o administrador pode ativar contas.', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas');
            exit;
        }

        $c = Conta::getById($_POST['conta_id']);
        $c->setEnabled(1);

        $conn = Database::getConnection();
        $stmt = $conn->prepare('UPDATE parcela SET enabled = 1 WHERE conta_id = :conta_id');
        $stmt->execute([':conta_id' => $c->getId()]);

        if ($c->save()) {
            Logger::log_enable(Conta::$tableName, $c->getId(), $_SESSION['usuario_id']);
            $_SESSION['message'] = ['Conta ativada com sucesso!', 'success'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas/detalhar/' . $c->getId());
            exit;
        }
    }

    private function cobrar($numero_parcela = '', $banco_id = -1, $conta_id = -1, $data = '', $aVista = false, $jurosAtraso = 0): void
    {
        $requestType = $_POST['request_type'] ?? 'html';

        if ($numero_parcela == '') {
            $numero_parcela = filter_var($_POST['numero_parcela'], FILTER_VALIDATE_INT);
        }

        if ($banco_id == -1) {
            $banco_id = filter_var($_POST['banco'], FILTER_VALIDATE_INT);
        }

        if ($conta_id == -1) {
            $conta_id = filter_var($_POST['conta_id'], FILTER_VALIDATE_INT);
        }

        if ($data == '') {
            $data = $_POST['data'] ?? '';
        }

        if (isset($_POST['juros_atraso'])) {
            $jurosAtraso = Money::reais_para_centavos($_POST['juros_atraso']);
        }

        // Pagamento parcial: o formulário envia quanto do principal será
        // amortizado. Sem o campo, "pagar tudo" usa o restante da parcela.
        $valorPrincipal = null;
        if ($requestType === 'js' && isset($_POST['valor']) && trim((string) $_POST['valor']) !== '') {
            $valorPrincipal = Money::reais_para_centavos((string) $_POST['valor']);
        }

        $conta = Conta::getById($conta_id);

        if (!$conta->isEnabled()) {
            if ($requestType == 'js') {
                self::respostaErroPagamento('Não é possível pagar contas inativadas.', $conta_id, 422);
            }
            $_SESSION['message'] = ['Não é possível pagar contas inativadas.', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas/detalhar/' . $conta_id);
            exit;
        }

        $parcelas = $conta->getParcelas();
        $parcela_id = -1;
        foreach ($parcelas as $parcela) {
            if ($parcela->getNumero_parcela() == $numero_parcela) {
                $parcela_id = $parcela->getId();
                break;
            }
        }

        if ($parcela_id == -1) {
            if ($requestType == 'js') {
                self::respostaErroPagamento('Parcela não encontrada', $conta_id, 422);
            }
            $_SESSION['message'] = ['Parcela não encontrada', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas/detalhar/' . $conta_id);
            exit;
        }

        $parcela = Parcela::getById($parcela_id);

        if ($jurosAtraso < 0 || $jurosAtraso > 100000000) {
            if ($requestType == 'js') {
                self::respostaErroPagamento('O valor do juros é inválido.', $conta_id, 422);
            }
            $_SESSION['message'] = ['O valor do juros é inválido', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas/detalhar/' . $conta_id);
            exit;
        }

        if ($jurosAtraso > 0) {
            $vencimentoParcela = new DateTime($parcela->getData_vencimento());
            $hoje = new DateTimeImmutable('today', new DateTimeZone('America/Sao_Paulo'));

            if ($vencimentoParcela >= $hoje) {
                if ($requestType == 'js') {
                    self::respostaErroPagamento('Só é possível aplicar juros em parcelas vencidas.', $conta_id, 422);
                }
                $_SESSION['message'] = ['Só é possível aplicar juros em parcelas vencidas.', 'fail'];
                header('Location: ' . $_ENV['BASE_URL'] . '/contas/detalhar/' . $conta_id);
                exit;
            }
        }

        if (!preg_match('/[0-9]{4}-[0-9]{2}-[0-9]{2}/', $data)) {
            if ($requestType == 'js') {
                self::respostaErroPagamento('Data inválida', $conta_id, 422);
            }
            $_SESSION['message'] = ['Data inválida', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas/detalhar/' . $conta_id);
            exit;
        }

        $dataHoje = new DateTimeImmutable(timezone: new DateTimeZone('America/Sao_Paulo'));
        $dataPagamentoEscolhida = new DateTime($data);
        $tresDiasAtras = $dataHoje->modify('-3 days')->modify('midnight');

        $foraDoPassado = $dataPagamentoEscolhida < $tresDiasAtras;
        $noFuturo = $dataPagamentoEscolhida > $dataHoje;
        $permiteFuturo = false;

        if ($aVista && isset($_SESSION['usuario_id'])) {
            $permiteFuturo = Permissions::userCan(
                Usuario::getById((int) $_SESSION['usuario_id'])->getPermissions(),
                Permissions::CREATE_FUTURE_BILLS
            );
        }

        if (!isAdmin() && ($foraDoPassado || ($noFuturo && !$permiteFuturo))) {
            if ($requestType == 'js') {
                self::respostaErroPagamento('Só é possível informar datas de pagamentos de hoje até três dias atrás', $conta_id, 422);
            }
            $_SESSION['message'] = ['Só é possível informar datas de pagamentos de hoje até três dias atrás', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas/detalhar/' . $conta_id);
            exit;
        }

        try {
            $pagamento = PagamentoService::pagarParcela(
                $parcela_id,
                (int) $banco_id,
                $data,
                $valorPrincipal,
                $jurosAtraso,
                (int) ($_SESSION['usuario_id'] ?? 0) ?: null
            );
        } catch (PagamentoException $e) {
            Logger::error('Erro ao pagar parcela', [
                'conta_id' => $conta_id,
                'numero_parcela' => $numero_parcela,
                'banco_id' => $banco_id,
                'data' => $data,
                'aVista' => $aVista,
                'erro' => $e->getMessage(),
            ]);

            if ($requestType == 'js') {
                self::respostaErroPagamento($e->getMessage(), $conta_id, 422);
            }
            $_SESSION['message'] = [$e->getMessage(), 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas/detalhar/' . $conta_id);
            exit;
        }

        if ($jurosAtraso > 0) {
            Logger::log(Conta::$tableName,
                "Valor parcela $numero_parcela: juros por atraso",
                $parcela->getValor_em_centavos(),
                $pagamento['total_pago_em_centavos'],
                $parcela->getConta_id(),
                $_SESSION['usuario_id']
            );
        }

        $conta->syncValorFromParcelas();

        $mensagem = $pagamento['paid'] ? 'Parcela paga com sucesso!' : 'Pagamento parcial registrado com sucesso!';

        if ($requestType == 'js') {
            header('Content-Type: application/json; charset=utf-8', response_code: 200);
            echo json_encode([
                'status' => 'success',
                'msg' => $mensagem,
                'parcela' => [
                    'id' => $pagamento['id'],
                    'paid' => $pagamento['paid'],
                    'parcial' => $pagamento['parcial'],
                    'valor_pago_em_centavos' => $pagamento['valor_pago_em_centavos'],
                    'valor_restante_em_centavos' => $pagamento['valor_restante_em_centavos'],
                    'juros_em_centavos' => $pagamento['juros_em_centavos'],
                    'data_pagamento' => $pagamento['data_pagamento'],
                    'banco_id' => $pagamento['banco_id'],
                ],
            ]);
            Logger::log(Conta::$tableName, 'Parcela ' . $parcela->getNumero_parcela(), '-', '-', $conta_id, $_SESSION['usuario_id']);
            exit;
        }
        if ($aVista) {
            $_SESSION['message'] = ['Conta cadastrada e paga com sucesso!', 'success'];
            header('Location: ' . $_SERVER['HTTP_REFERER']);
        } else {
            $_SESSION['message'] = [$mensagem, 'success'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas/detalhar/' . $conta_id);
        }
        Logger::log(Conta::$tableName, 'Parcela ' . $parcela->getNumero_parcela(), '-', '-', $conta_id, $_SESSION['usuario_id']);
        exit;
    }

    private static function respostaErroPagamento(string $msg, int $contaId, int $statusCode): void
    {
        header('Content-Type: application/json; charset=utf-8', response_code: $statusCode);
        echo json_encode([
            'status' => 'error',
            'msg' => $msg,
        ]);
        exit;
    }

    private function searchJson(): void
    {
        header('Content-Type: application/json');

        $filters = self::normalizarFiltros($_POST);
        $_SESSION['contas_filters_atual'] = $filters;

        $parcelas = Parcela::getAll(
            $filters['filter_status'],
            $filters['filter_natureza'],
            $filters['filter_centro'],
            $filters['filter_fornecedor'],
            $filters['filter_start_date'],
            $filters['filter_end_date']
        );

        $result = [];
        foreach ($parcelas as $p) {
            $valorReais = number_format($p['valor_em_centavos'] / 100, 2, ',', '.');
            $valorTotalReais = number_format($p['valor_total_em_centavos'] / 100, 2, ',', '.');
            $valorPagoReais = number_format($p['valor_pago_em_centavos'] / 100, 2, ',', '.');
            $valorRestanteReais = number_format($p['valor_restante_em_centavos'] / 100, 2, ',', '.');
            $pagamentoTotalCentavos = (int) ($p['pagamento_total_em_centavos'] ?? $p['valor_pago_em_centavos']);
            $result[] = [
                'id' => $p['id'],
                'conta_id' => $p['conta_id'],
                'numero_parcela' => $p['numero_parcela'],
                'total_parcelas' => $p['total_parcelas'],
                'valor_em_centavos' => $p['valor_em_centavos'],
                'valor_reais' => $valorReais,
                'juros_em_centavos' => $p['juros_em_centavos'],
                'valor_total_em_centavos' => $p['valor_total_em_centavos'],
                'valor_total_reais' => $valorTotalReais,
                'valor_pago_em_centavos' => (int) $p['valor_pago_em_centavos'],
                'valor_pago_reais' => $valorPagoReais,
                'valor_restante_em_centavos' => (int) $p['valor_restante_em_centavos'],
                'valor_restante_reais' => $valorRestanteReais,
                'paid' => (bool) $p['paid'],
                'parcial' => !(bool) $p['paid'] && (int) $p['valor_pago_em_centavos'] > 0,
                'origem' => $p['origem'] ?? 'parcela',
                'pagamento_id' => $p['pagamento_id'] !== null ? (int) $p['pagamento_id'] : null,
                'pagamento_total_em_centavos' => $pagamentoTotalCentavos,
                'pagamento_total_reais' => number_format($pagamentoTotalCentavos / 100, 2, ',', '.'),
                'data_vencimento' => $p['data_vencimento'],
                'data_pagamento' => $p['data_pagamento'],
                'banco_id' => $p['banco_id'],
                'fornecedor' => $p['fornecedor'] ?? '-',
                'natureza' => $p['natureza'],
                'centro' => $p['centro'],
                'historico' => $p['historico'],
            ];
        }

        echo json_encode(['ok' => true, 'parcelas' => $result, 'status' => $filters['filter_status']]);
        exit;
    }

    private function loadView(string $view): void
    {
        if (!in_array($view, $this->views)) {
            include '../src/404.php';
            exit;
        }

        if (isset($this->id)) {
            $conta = Conta::getById($this->id);

            if ($view == 'detalhar') {
                $logs = Database::getLog(Conta::$tableName, $this->id);
                $centros = CentroDeCusto::getOptionsWithHierarchy();
                $availableFilterOptions = [
                    'fornecedores' => Database::getOptions(Fornecedor::$tableName),
                    'naturezas' => Database::getOptions(Natureza::$tableName),
                ];
            }
        }

        if (isset($_GET['format']) && $_GET['format'] === 'json' && isset($conta)) {
            header('Content-Type: application/json');

            $pagamentosPorParcela = [];
            foreach (ParcelaPagamento::getByConta($conta->getId()) as $pagamento) {
                $pagamentosPorParcela[$pagamento->getParcela_id()][] = [
                    'id' => $pagamento->getId(),
                    'valor_em_centavos' => $pagamento->getValor_em_centavos(),
                    'juros_em_centavos' => $pagamento->getJuros_em_centavos(),
                    'total_em_centavos' => $pagamento->getValor_total_em_centavos(),
                    'data_pagamento' => $pagamento->getData_pagamento(),
                    'banco_id' => $pagamento->getBanco_id(),
                    'banco_nome' => $pagamento->getBanco_nome(),
                    'usuario_nome' => $pagamento->getUsuario_nome(),
                ];
            }

            $parcelasData = [];
            foreach ($conta->getParcelas() as $p) {
                $bancoNome = null;
                if ($p->getBanco_id() !== null) {
                    $b = Banco::getById($p->getBanco_id());
                    $bancoNome = $b ? $b->getNome() : null;
                }
                $parcelasData[] = [
                    'id' => $p->getId(),
                    'numero_parcela' => $p->getNumero_parcela(),
                    'valor_em_centavos' => $p->getValor_em_centavos(),
                    'juros_em_centavos' => $p->getJuros_em_centavos(),
                    'valor_pago_em_centavos' => $p->getValor_pago_em_centavos(),
                    'valor_restante_em_centavos' => $p->getValorRestante(),
                    'data_vencimento' => $p->getData_vencimento(),
                    'data_pagamento' => $p->isPaid() ? $p->getData_pagamento() : null,
                    'banco_id' => $p->getBanco_id(),
                    'banco_nome' => $bancoNome,
                    'paid' => $p->isPaid(),
                    'parcial' => $p->isParcial(),
                    'pagamentos' => $pagamentosPorParcela[$p->getId()] ?? [],
                ];
            }
            echo json_encode([
                'id' => $conta->getId(),
                'historico' => $conta->getHistorico(),
                'valor_em_centavos' => $conta->getValor_em_centavos(),
                'centro_de_custo_id' => $conta->getCentro_de_custo_id(),
                'centro_de_custo_nome' => CentroDeCusto::getById($conta->getCentro_de_custo_id())->getNome(),
                'natureza_id' => $conta->getNaturezaId(),
                'natureza_nome' => Natureza::getById($conta->getNaturezaId())->getNome(),
                'fornecedor_id' => $conta->getFornecedor_id(),
                'fornecedor_nome' => $conta->getFornecedor_id() ? Fornecedor::getById($conta->getFornecedor_id())->getNome() : null,
                'paid' => $conta->isPaid(),
                'enabled' => $conta->isEnabled(),
                'parcelas' => $parcelasData,
            ]);
            exit;
        }

        if ($view == 'index') {
            $filters = self::normalizarFiltros($_SESSION['contas_filters'] ?? []);

            unset($_SESSION['contas_filters']);
            $_SESSION['contas_filters_atual'] = $filters;

            $parcelas = [];

            $conn = Database::getConnection();
            $centrosStmt = $conn->query("SELECT id, nome, categoria_id FROM centro_de_custo WHERE enabled = 1 ORDER BY nome");

            $availableFilterOptions = [
                'naturezas' => Database::getOptions(Natureza::$tableName),
                'centros' => $centrosStmt->fetchAll(\PDO::FETCH_ASSOC),
                'fornecedores' => Database::getOptions(Fornecedor::$tableName)
            ];

            if (empty($availableFilterOptions['fornecedores'])) {
                $_SESSION['message'] = ['Não é possível cadastrar uma conta pois não há fornecedores cadastrados', 'warning'];
                header('Location: ' . $_ENV['BASE_URL'] . '/fornecedores/cadastrar');
                exit;
            }

            if (empty($availableFilterOptions['naturezas'])) {
                $_SESSION['message'] = ['Não é possível cadastrar uma conta pois não há naturezas cadastradas', 'warning'];
                header('Location: ' . $_ENV['BASE_URL'] . '/naturezas/cadastrar');
                exit;
            }

            if (empty($availableFilterOptions['centros'])) {
                $_SESSION['message'] = ['Não é possível cadastrar uma conta pois não há centros de custo cadastrados', 'warning'];
                header('Location: ' . $_ENV['BASE_URL'] . '/centros-de-custo/cadastrar');
                exit;
            }

            $parcelas = Parcela::getAll($filters['filter_status'], $filters['filter_natureza'], $filters['filter_centro'], $filters['filter_fornecedor'], $filters['filter_start_date'], $filters['filter_end_date']);

            $bancos = Database::getOptions(Banco::$tableName);
            $currentUser = Usuario::getById($_SESSION['usuario_id']);
        }

        include '../src/templates/header.php';
        include "../src/Views/contas/$view.php";
        include '../src/templates/footer.php';
    }

    private function update(): void
    {
        $contaId = filter_var($_POST['conta_id'], FILTER_VALIDATE_INT);
        $historico = trim($_POST['historico']);
        $valorEmCentavos = Money::reais_para_centavos($_POST['valor_em_reais']);
        $naturezaId = filter_var($_POST['natureza'], FILTER_VALIDATE_INT);
        $centroId = filter_var($_POST['centro'], FILTER_VALIDATE_INT);
        $fornecedorId = filter_var($_POST['fornecedor'], FILTER_VALIDATE_INT);
        $formaPagamento = $_POST['forma-pagamento'];

        $conta = Conta::getById($contaId);

        if (Conta::hasPayments($contaId)) {
            $_SESSION['message'] = ['Não é possível editar uma conta que possui pagamentos. Utilize a opção de inativar para estornar.', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas/detalhar/' . $contaId);
            exit;
        }

        if ($fornecedorId == -1) {
            $fornecedorId = null;
        }

        if ($valorEmCentavos == 0) {
            $_SESSION['message'] = ['O valor da conta não pode ser zero', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas/detalhar/' . $contaId);
            exit;
        }

        if ($historico == '') {
            $historico = 'Histórico não informado.';
        }

        if (strlen($historico) >= 400) {
            $_SESSION['message'] = ['O histórico não pode exceder 400 caracteres', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas/detalhar/' . $contaId);
            exit;
        }

        if (CentroDeCusto::getById($centroId)->getCategoriaId() == null) {
            $_SESSION['message'] = ['Escolha um sub-centro de custo!', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas/detalhar/' . $contaId);
            exit;
        }

        $oldHistorico = $conta->getHistorico();
        $oldValor = $conta->getValor_em_centavos();
        $oldNatureza = $conta->getNaturezaId();
        $oldCentro = $conta->getCentro_de_custo_id();
        $oldFornecedor = $conta->getFornecedor_id();

        $conta->setHistorico($historico);
        $conta->setValor_em_centavos($valorEmCentavos);
        $conta->setNaturezaId($naturezaId);
        $conta->setCentro_de_custo_id($centroId);
        $conta->setFornecedor_id($fornecedorId);

        if ($conta->save()) {
            if ($historico !== $oldHistorico) {
                Logger::log(Conta::$tableName, 'historico', $oldHistorico, $historico, $contaId, $_SESSION['usuario_id']);
            }
            if ($valorEmCentavos !== $oldValor) {
                Logger::log(Conta::$tableName, 'valor', (string)$oldValor, (string)$valorEmCentavos, $contaId, $_SESSION['usuario_id']);
            }
            if ($naturezaId !== $oldNatureza) {
                Logger::log(Conta::$tableName, 'natureza_id', (string)$oldNatureza, (string)$naturezaId, $contaId, $_SESSION['usuario_id']);
            }
            if ($centroId !== $oldCentro) {
                Logger::log(Conta::$tableName, 'centro_de_custo_id', (string)$oldCentro, (string)$centroId, $contaId, $_SESSION['usuario_id']);
            }
            if ($fornecedorId !== $oldFornecedor) {
                Logger::log(Conta::$tableName, 'fornecedor_id', (string)($oldFornecedor ?? ''), (string)($fornecedorId ?? ''), $contaId, $_SESSION['usuario_id']);
            }

            $parcelas = $conta->getParcelas();
            $oldParcelaCount = count($parcelas);
            $oldVencimento = null;
            if ($oldParcelaCount === 1) {
                $oldVencimento = $parcelas[0]->getData_vencimento();
            }

            Parcela::deleteByContaId($contaId);

            if ($formaPagamento == 'parcelado') {
                $qtdParcelas = filter_var($_POST['qtd_parcela'], FILTER_VALIDATE_INT);
                $hoje = new DateTimeImmutable('today');
                $tresDiasAtras = $hoje->modify('-3 days');
                $parcelasData = [];
                for ($i = 1; $i <= $qtdParcelas; $i++) {
                    $valorParcela = Money::reais_para_centavos($_POST["parcela$i" . "_valor"]);
                    $vencimentoParcela = (string) ($_POST["parcela$i" . "_vencimento"] ?? '');
                    if ($valorParcela == 0 || $vencimentoParcela == '') {
                        continue;
                    }

                    $dataVencimento = DateTimeImmutable::createFromFormat('!Y-m-d', $vencimentoParcela);

                    if (!$dataVencimento || $dataVencimento->format('Y-m-d') !== $vencimentoParcela) {
                        $_SESSION['message'] = ['Data de vencimento inválida', 'fail'];
                        header('Location: ' . $_ENV['BASE_URL'] . '/contas/detalhar/' . $contaId);
                        exit;
                    }

                    if (!isAdmin() && $dataVencimento < $tresDiasAtras) {
                        $_SESSION['message'] = ['Você não tem permissão para cadastrar parcelas com vencimento há mais de 3 dias.', 'fail'];
                        header('Location: ' . $_ENV['BASE_URL'] . '/contas/detalhar/' . $contaId);
                        exit;
                    }

                    $parcelasData[] = ['valor' => $valorParcela, 'vencimento' => $vencimentoParcela];
                }
                Parcela::bulkInsert($parcelasData, $contaId);
            } else {
                $bancoId = filter_var($_POST['banco'] ?? -1, FILTER_VALIDATE_INT);
                if ($bancoId == -1) {
                    $bancoId = null;
                }
                $dataPagamento = $_POST['dataParcelaAVista'];
                new Parcela(0, 1, $valorEmCentavos, $dataPagamento, null, $contaId, $bancoId, false)->save();
                if ($bancoId != null) {
                    $this->cobrar(1, $bancoId, $contaId, $dataPagamento, true);
                }
            }

            $newParcelas = Parcela::getByContaId($contaId);
            $newParcelaCount = count($newParcelas);
            if ($oldParcelaCount !== $newParcelaCount) {
                Logger::log(Conta::$tableName, 'Nº Parcelas', (string)$oldParcelaCount, (string)$newParcelaCount, $contaId, $_SESSION['usuario_id']);
            }

            if ($oldParcelaCount === 1 && $newParcelaCount === 1 && $oldVencimento !== null) {
                $newVencimento = $newParcelas[0]->getData_vencimento();
                if ($oldVencimento !== $newVencimento) {
                    Logger::log(Conta::$tableName, 'vencimento', $oldVencimento, $newVencimento, $contaId, $_SESSION['usuario_id']);
                }
            }

            $this->sortParcelasByDate($contaId);

            $_SESSION['message'] = ['Conta atualizada com sucesso!', 'success'];
            $this->restaurarFiltros();
            header('Location: ' . $_ENV['BASE_URL'] . '/contas');
            exit;
        }
    }

    /**
     * Edicao leve: altera somente os atributos descritivos da conta
     * (fornecedor, natureza, centro de custo e historico).
     *
     * Diferente de update(), nunca toca no valor da conta nem na tabela
     * parcela, portanto nao exige que a conta esteja sem parcelas pagas
     * e nao recalcula nada. O valor so e reescrito com o proprio valor
     * ja carregado em memoria por Conta::save().
     */
    private function editarAtributos(): void
    {
        $contaId = filter_var($_POST['conta_id'], FILTER_VALIDATE_INT);
        $centroId = filter_var($_POST['centro'], FILTER_VALIDATE_INT);
        $naturezaId = filter_var($_POST['natureza'], FILTER_VALIDATE_INT);
        $fornecedorId = filter_var($_POST['fornecedor'], FILTER_VALIDATE_INT);
        $historico = trim($_POST['historico']);

        if (!$contaId || !$centroId || !$naturezaId) {
            $_SESSION['message'] = ['Dados inválidos', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas');
            exit;
        }

        if ($fornecedorId == -1) { // "Nenhum"
            $fornecedorId = null;
        }

        if ($historico == '') {
            $historico = 'Histórico não informado.';
        }

        if (strlen($historico) >= 400) {
            $_SESSION['message'] = ['O histórico não pode exceder 400 caracteres', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas/detalhar/' . $contaId);
            exit;
        }

        if (CentroDeCusto::getById($centroId)->getCategoriaId() == null) {
            $_SESSION['message'] = ['Não é possível editar uma conta para uma categoria de centro de custo, escolha um sub-centro de custo!', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas/detalhar/' . $contaId);
            exit;
        }

        Natureza::getById($naturezaId); // se id nao existe, a execucao para

        if ($fornecedorId !== null) {
            Fornecedor::getById($fornecedorId); // se id nao existe, a execucao para
        }

        $conta = Conta::getById($contaId);

        // o pdo devolve os ints como string (emulated prepares), entao converte
        // para comparar com === e nao gerar log de alteracao a toa.
        $oldHistorico = $conta->getHistorico();
        $oldNatureza = (int)$conta->getNaturezaId();
        $oldCentro = (int)$conta->getCentro_de_custo_id();
        $oldFornecedor = $conta->getFornecedor_id() === null ? null : (int)$conta->getFornecedor_id();

        $conta->setHistorico($historico);
        $conta->setNaturezaId($naturezaId);
        $conta->setCentro_de_custo_id($centroId);
        $conta->setFornecedor_id($fornecedorId);

        if ($conta->save()) {
            // o construtor de Conta ja escapa o historico, entao compara com o
            // valor decodificado para nao acusar alteracao quando nada mudou.
            if ($historico !== htmlspecialchars_decode($oldHistorico, ENT_QUOTES)) {
                Logger::log(Conta::$tableName, 'historico', $oldHistorico, $historico, $contaId, $_SESSION['usuario_id']);
            }
            if ($naturezaId !== $oldNatureza) {
                Logger::log(Conta::$tableName, 'natureza_id', (string)$oldNatureza, (string)$naturezaId, $contaId, $_SESSION['usuario_id']);
            }
            if ($centroId !== $oldCentro) {
                Logger::log(Conta::$tableName, 'centro_de_custo_id', (string)$oldCentro, (string)$centroId, $contaId, $_SESSION['usuario_id']);
            }
            if ($fornecedorId !== $oldFornecedor) {
                Logger::log(Conta::$tableName, 'fornecedor_id', (string)($oldFornecedor ?? ''), (string)($fornecedorId ?? ''), $contaId, $_SESSION['usuario_id']);
            }

            $_SESSION['message'] = ['Conta atualizada com sucesso!', 'success'];
            header('Location: ' . $_ENV['BASE_URL'] . '/contas/detalhar/' . $contaId);
            exit;
        }
    }

    private function sortParcelasByDate(int $contaId): void
    {
        $parcelas = Parcela::getByContaId($contaId);
        usort($parcelas, function ($a, $b) {
            return strcmp($a->getData_vencimento(), $b->getData_vencimento());
        });
        $ids = array_map(fn($p) => $p->getId(), $parcelas);
        Parcela::renumber($ids);
    }

}
