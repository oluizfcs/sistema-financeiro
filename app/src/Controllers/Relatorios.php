<?php

namespace App\Controllers;

use App\Models\Banco;
use App\Models\CentroDeCusto;
use App\Models\Database;
use App\Models\Deposito;
use App\Models\Fornecedor;
use App\Models\Natureza;
use App\Models\Parcela;
use Mpdf\Mpdf;
use DateTime;
use DateTimeImmutable;

class Relatorios
{
    public static bool $needLogin = true;
    public static bool $onlyAdmin = false;

    function __construct()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            set_time_limit(450);
            ini_set('memory_limit', '768M');

            $reportType = $_POST['report_type'] ?? '';

            $startDate = DateTime::createFromFormat('Y-m-d', $_POST['start_date']);

            if ($startDate == false) {
                $_SESSION['message'] = ['Data inicial inválida', 'fail'];
                header('Location: ' . $_ENV['BASE_URL'] . '/relatorios');
                exit;
            };

            $endDate = DateTime::createFromFormat('Y-m-d', $_POST['end_date']);

            if ($endDate == false) {
                $_SESSION['message'] = ['Data final inválida', 'fail'];
                header('Location: ' . $_ENV['BASE_URL'] . '/relatorios');
                exit;
            };

            $orientation = ($_POST['orientation'] ?? 'landscape') === 'portrait' ? 'portrait' : 'landscape';

            if ($reportType === 'bancoMovimentos') {
                $data = [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'orientation' => $orientation,
                    'banco_id' => ($_POST['banco_id'] ?? 'all') === 'all' ? null : (int)$_POST['banco_id']
                ];
                $this->renderExtratoPdf($data);
                exit;
            }

            $postedStatus = $_POST['status'];
            if ($postedStatus === 'a pagar') $postedStatus = 'unpaid';
            else if ($postedStatus === 'pagas') $postedStatus = 'paid';

            if ($postedStatus === 'inativadas') {
                $_SESSION['message'] = ['Não é possível gerar relatório de contas inativadas.', 'fail'];
                header('Location: ' . $_ENV['BASE_URL'] . '/relatorios');
                exit;
            }

            $reportStyle = $_POST['report_style'] ?? 'analitico';
            if (!in_array($reportStyle, ['analitico', 'semanal', 'sintetico', 'sintetico_geral'])) {
                $reportStyle = 'semanal';
            }

            $data = [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'natureza' => null,
                'centro' => null,
                'fornecedor' => null,
                'historicoCompleto' => false,
                'status' => $postedStatus,
                'report_source' => $_POST['report_source'] ?? 'relatorios',
                'report_style' => $reportStyle,
                'orientation' => ($_POST['orientation'] ?? 'landscape') === 'portrait' ? 'portrait' : 'landscape',
                'hidden_columns' => $_POST['hidden_columns'] ?? []
            ];

            if (isset($_POST['historicoCompleto'])) {
                $data['historicoCompleto'] = true;
            }

            if ($_POST['natureza'] != 'all') {
                $natureza = Natureza::getById($_POST['natureza']);
                $data['natureza'] = $natureza->getNome();
            }

            if ($_POST['centro'] != 'all') {
                $centro = CentroDeCusto::getById($_POST['centro']);
                $data['centro'] = $centro->getNome();
            }

            if ($_POST['fornecedor'] != 'all') {
                $fornecedor = Fornecedor::getById($_POST['fornecedor']);
                $data['fornecedor'] = $fornecedor->getNome();
            }

            if ($reportStyle === 'sintetico') {
                $rawData = Database::getSinteticoReport(
                    $_POST['start_date'],
                    $_POST['end_date'],
                    $_POST['natureza'],
                    $_POST['centro'],
                    $_POST['fornecedor'],
                    $postedStatus
                );
            } elseif ($reportStyle === 'sintetico_geral') {
                $rawData = Database::getSinteticoGeralReport(
                    $_POST['start_date'],
                    $_POST['end_date'],
                    $_POST['natureza'],
                    $_POST['centro'],
                    $_POST['fornecedor'],
                    $postedStatus
                );
            } else {
                $rawData = Database::getReport(
                    $_POST['start_date'],
                    $_POST['end_date'],
                    $_POST['natureza'],
                    $_POST['centro'],
                    $_POST['fornecedor'],
                    $postedStatus
                );
            }

            if (count($rawData) <= 0) {
                $_SESSION['message'] = ['Não há registros que satisfaçam os filtros selecionados', 'fail'];
                header('Location: ' . $_ENV['BASE_URL'] . '/relatorios');
                exit;
            }

            if ($reportStyle === 'sintetico') {
                $data['mostrar_subcentros'] = isset($_POST['show_subcentros']);
                $data['grupos'] = $this->buildSinteticoGrupos($rawData, $data['mostrar_subcentros']);

                $data['total'] = 0;
                $data['quantidade'] = 0;

                foreach ($data['grupos'] as $grupo) {
                    $data['total'] += $grupo['subtotal'];
                    $data['quantidade'] += $grupo['quantidade'];
                }

                $this->renderSinteticoPdf($data);
                exit;
            }

            if ($reportStyle === 'sintetico_geral') {
                $data['centros'] = $rawData;

                $data['total'] = 0;
                $data['quantidade'] = 0;

                foreach ($data['centros'] as $centro) {
                    $data['total'] += $centro['total'];
                    $data['quantidade'] += $centro['quantidade'];
                }

                $this->renderSinteticoGeralPdf($data);
                exit;
            }

            if ($reportStyle === 'analitico') {
                $data['total'] = 0;
                foreach ($rawData as $row) {
                    $data['total'] += $row['valor_total_em_centavos'];
                }

                $data['rows'] = $rawData;

                $dateColumn = Parcela::dateColumnForStatus($postedStatus);

                usort($data['rows'], function ($a, $b) use ($dateColumn) {
                    return strcmp($a[$dateColumn], $b[$dateColumn]);
                });

                $this->renderAnaliticoPdf($data);
                exit;
            }

            $dateColumn = Parcela::dateColumnForStatus($postedStatus);

            $data['weeks'] = $this->groupDataByWeek($rawData, $_POST['start_date'], $_POST['end_date'], $dateColumn);

            $data['total'] = 0;

            foreach($data['weeks'] as $week) {
                $data['total'] += $week['total'];
            }

            $this->renderPdf($data);
            exit;
        }

        include '../src/404.php';
    }

    private function groupDataByWeek($rawData, $startDate, $endDate, string $dateColumn): array
    {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);

        if ($start->format('w') != 6) {
            $start->modify('last saturday');
        }

        if ($end->format('w') != 5) {
            $end->modify('next friday');
        }

        $weeks = [];
        $current = clone $start;

        while ($current <= $end) {
            $weekEnd = clone $current;
            $weekEnd->modify('+6 days');

            $key = $current->format('Y-m-d');
            $weeks[$key] = [
                'start_date' => $current->format('d/m/Y'),
                'end_date' => $weekEnd->format('d/m/Y'),
                'rows' => [],
                'total' => 0
            ];
            $current->modify('+1 week');
        }

        foreach ($rawData as $row) {
            $date = new DateTime($row[$dateColumn]);
            $weekStart = clone $date;

            if ($weekStart->format('w') != 6) {
                $weekStart->modify('last saturday');
            }

            $key = $weekStart->format('Y-m-d');

            if (isset($weeks[$key])) {
                $weeks[$key]['rows'][] = $row;
                if (isset($row['valor_total_em_centavos'])) {
                    $weeks[$key]['total'] += $row['valor_total_em_centavos'];
                }
            }
        }

        return array_values($weeks);
    }

    /**
     * Agrupa os totais por natureza e, dentro de cada uma, por centro de custo superior.
     * O subtotal da natureza sempre vem da agregação por pai_id, nunca das linhas exibidas,
     * para que o centro de custo e seus sub-centros não sejam contados duas vezes.
     *
     * @param array $rawData
     * @param bool $mostrarSubcentros
     * @return array
     */
    private function buildSinteticoGrupos(array $rawData, bool $mostrarSubcentros): array
    {
        $grupos = [];

        foreach ($rawData as $row) {
            $naturezaId = $row['natureza_id'];

            if (!isset($grupos[$naturezaId])) {
                $grupos[$naturezaId] = [
                    'natureza' => $row['natureza'],
                    'pais' => [],
                    'subtotal' => 0,
                    'quantidade' => 0
                ];
            }

            $paiId = $row['pai_id'];

            if (!isset($grupos[$naturezaId]['pais'][$paiId])) {
                $grupos[$naturezaId]['pais'][$paiId] = [
                    'nome' => $row['pai_nome'],
                    'total' => 0,
                    'quantidade' => 0,
                    'filhos' => []
                ];
            }

            $grupos[$naturezaId]['pais'][$paiId]['total'] += $row['total'];
            $grupos[$naturezaId]['pais'][$paiId]['quantidade'] += $row['quantidade'];

            if ($row['centro_id'] != $paiId) {
                $grupos[$naturezaId]['pais'][$paiId]['filhos'][$row['centro_id']] = [
                    'nome' => $row['centro'],
                    'total' => $row['total'],
                    'quantidade' => $row['quantidade']
                ];
            }

            $grupos[$naturezaId]['subtotal'] += $row['total'];
            $grupos[$naturezaId]['quantidade'] += $row['quantidade'];
        }

        $resultado = [];

        foreach ($grupos as $grupo) {
            $linhas = [];

            $pais = $grupo['pais'];

            uasort($pais, function ($a, $b) {
                return $b['total'] <=> $a['total'];
            });

            foreach ($pais as $pai) {
                $linhas[] = [
                    'nome' => $pai['nome'],
                    'total' => $pai['total'],
                    'quantidade' => $pai['quantidade'],
                    'nivel' => 0,
                    'eh_subtotal' => $mostrarSubcentros && count($pai['filhos']) > 0
                ];

                if (!$mostrarSubcentros || count($pai['filhos']) === 0) {
                    continue;
                }

                $filhos = array_values($pai['filhos']);

                usort($filhos, function ($a, $b) {
                    return $b['total'] <=> $a['total'];
                });

                foreach ($filhos as $filho) {
                    $linhas[] = [
                        'nome' => $filho['nome'],
                        'total' => $filho['total'],
                        'quantidade' => $filho['quantidade'],
                        'nivel' => 1,
                        'eh_subtotal' => false
                    ];
                }
            }

            $resultado[] = [
                'natureza' => $grupo['natureza'],
                'subtotal' => $grupo['subtotal'],
                'quantidade' => $grupo['quantidade'],
                'linhas' => $linhas
            ];
        }

        return $resultado;
    }

    private function renderPdf($data): void
    {
        ob_start();
        include('../src/Views/relatorios/relatorio.php');
        $html = ob_get_contents();
        ob_end_clean();

        $mpdf = new Mpdf(['format' => $data['orientation'] === 'portrait' ? 'A4' : 'A4-L']);
        $mpdf->SetFooter('{PAGENO}');
        $mpdf->WriteHTML($html);
        $mpdf->OutputHttpInline();
    }

    private function renderSinteticoPdf($data): void
    {
        ob_start();
        include('../src/Views/relatorios/relatorio_sintetico.php');
        $html = ob_get_contents();
        ob_end_clean();

        $mpdf = new Mpdf(['format' => $data['orientation'] === 'portrait' ? 'A4' : 'A4-L']);
        $mpdf->SetFooter('{PAGENO}');
        $mpdf->WriteHTML($html);
        $mpdf->OutputHttpInline();
    }

    private function renderSinteticoGeralPdf($data): void
    {
        ob_start();
        include('../src/Views/relatorios/relatorio_sintetico_geral.php');
        $html = ob_get_contents();
        ob_end_clean();

        $mpdf = new Mpdf(['format' => $data['orientation'] === 'portrait' ? 'A4' : 'A4-L']);
        $mpdf->SetFooter('{PAGENO}');
        $mpdf->WriteHTML($html);
        $mpdf->OutputHttpInline();
    }

    private function renderAnaliticoPdf($data): void
    {
        ob_start();
        include('../src/Views/relatorios/relatorio_analitico.php');
        $html = ob_get_contents();
        ob_end_clean();

        $mpdf = new Mpdf(['format' => $data['orientation'] === 'portrait' ? 'A4' : 'A4-L']);
        $mpdf->SetFooter('{PAGENO}');
        $mpdf->WriteHTML($html);
        $mpdf->OutputHttpInline();
    }

    private function renderExtratoPdf($data): void
    {
        $startDate = $data['start_date']->format('Y-m-d');
        $endDate = $data['end_date']->format('Y-m-d');

        if ($data['banco_id'] !== null) {
            $bancos = [Banco::getById($data['banco_id'])];
        } else {
            $bancos = [];
            foreach (Banco::getAll(true, '') as $b) {
                $bancos[] = Banco::getById($b['id']);
            }
        }

        $extrato = [];
        foreach ($bancos as $banco) {
            $depositos = Deposito::getByBankBetween($banco->getId(), $startDate, $endDate);
            $parcelas = Parcela::getPaidByBankBetween($banco->getId(), $startDate, $endDate);

            $rows = [];
            $totalEntradas = 0;
            foreach ($depositos as $dep) {
                $rows[] = [
                    'data' => substr($dep->getDataDeposito(), 0, 10),
                    'ordem' => 0,
                    'id' => $dep->getId(),
                    'historico' => $dep->getHistorico(),
                    'numero_parcela' => null,
                    'natureza' => null,
                    'entrada' => $dep->getValorEmCentavos(),
                    'saida' => 0
                ];
            }
            foreach ($parcelas as $p) {
                // If has parent (categoria_id), parent goes to Centro, child to Sub centro
                $centro = $p->centro_pai ?? $p->centro_de_custo;
                $subcentro = ($p->centro_pai !== null) ? $p->centro_de_custo : '-';
                $rows[] = [
                    'data' => $p->getData_pagamento(),
                    'ordem' => 1,
                    'id' => $p->getId(),
                    'historico' => $p->historico,
                    'numero_parcela' => $p->getNumero_parcela() . '/' . $p->total_parcelas,
                    'fornecedor' => $p->fornecedor,
                    'natureza' => $p->natureza,
                    'centro' => $centro,
                    'subcentro' => $subcentro,
                    'entrada' => 0,
                    'saida' => $p->getValor_em_centavos() + $p->getJuros_em_centavos()
                ];
            }

            usort($rows, function ($a, $b) {
                $cmp = strcmp($a['data'], $b['data']);
                if ($cmp != 0) {
                    return $cmp;
                }
                if ($a['ordem'] != $b['ordem']) {
                    return $a['ordem'] - $b['ordem'];
                }
                return $a['id'] - $b['id'];
            });

            $totalEntradas = 0;
            $totalSaidas = 0;
            foreach ($rows as $r) {
                $totalEntradas += $r['entrada'];
                $totalSaidas += $r['saida'];
            }

            $saldoAtual = $banco->getSaldo_em_centavos();

            // O saldo inicial é o saldo do banco ao final do dia anterior ao
            // período, ou seja, antes de qualquer movimento da data inicial.
            $diaAnterior = (new DateTimeImmutable($startDate))->modify('-1 day')->format('Y-m-d');
            $saldoInicial = Banco::getSaldoEmData($banco->getId(), $diaAnterior);

            $saldo = $saldoInicial;
            foreach ($rows as &$r) {
                $saldo += $r['entrada'] - $r['saida'];
                $r['saldo'] = $saldo;
            }
            unset($r);

            $extrato[] = [
                'banco_nome' => $banco->getNome(),
                'rows' => $rows,
                'total_entradas' => $totalEntradas,
                'total_saidas' => $totalSaidas,
                'saldo_inicial' => $saldoInicial,
                'saldo_atual' => $saldoAtual
            ];
        }

        $extrato = array_values(array_filter($extrato, fn ($b) => count($b['rows']) > 0));

        if (count($extrato) <= 0) {
            // If AJAX/fetch request, return JSON error instead of redirecting
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                http_response_code(400);
                echo json_encode(['error' => 'Não há registros que satisfaçam os filtros selecionados']);
                exit;
            }
            $_SESSION['message'] = ['Não há registros que satisfaçam os filtros selecionados', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/bancos');
            exit;
        }

        $data['extrato'] = $extrato;
        $data['banco_nome'] = $data['banco_id'] !== null ? $extrato[0]['banco_nome'] : null;

        ob_start();
        include('../src/Views/relatorios/relatorio_extrato.php');
        $html = ob_get_contents();
        ob_end_clean();

        $mpdf = new Mpdf(['format' => $data['orientation'] === 'portrait' ? 'A4' : 'A4-L']);
        $mpdf->SetFooter('{PAGENO}');
        $mpdf->WriteHTML($html);
        $mpdf->OutputHttpInline();
    }
}
