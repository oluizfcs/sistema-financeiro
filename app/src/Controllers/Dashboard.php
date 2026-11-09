<?php

namespace App\Controllers;


use App\Models\Parcela;
use App\Models\Fornecedor;
use App\Models\CentroDeCusto;
use App\Models\Database;
use App\Models\Natureza;

class Dashboard
{
    public static bool $needLogin = true;
    public static bool $onlyAdmin = false;

    function __construct()
    {
        $this->loadView();
    }

    private function loadView(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $_SESSION['dashboard_filters'] = [
                'start_date' => $_POST['start_date'],
                'end_date' => $_POST['end_date'],
                'status' => $_POST['status'] ?? 'todas',
                'natureza' => $_POST['natureza'] ?? 'all',
                'hide_zeros' => isset($_POST['hide_zeros']),
            ];

            header('Location: ' . $_ENV['BASE_URL'] . '/dashboard');
            exit;
        }

        $filters = $_SESSION['dashboard_filters'] ?? [
            'start_date' => date('Y-m-01'),
            'end_date' => date('Y-m-t'),
            'status' => 'todas',
            'natureza' => 'all',
            'hide_zeros' => true
        ];

        $startDate = $filters['start_date'] ?? date('Y-m-01');
        $endDate = $filters['end_date'] ?? date('Y-m-t');
        $status = $filters['status'] ?? 'todas';
        $naturezaId = $filters['natureza'] ?? 'all';
        $hideZeros = $filters['hide_zeros'] ?? true;

        unset($_SESSION['dashboard_filters']);

        if (trim($startDate) == "" || trim($endDate) == "") {
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
        }

        $dailyTotals = Parcela::getDailyTotal($startDate, $endDate, $status, $naturezaId);

        if (!$hideZeros) {
            $dailyTotalsMap = [];
            foreach ($dailyTotals as $day) {
                $dailyTotalsMap[$day['date']] = $day['total'];
            }

            $currentDate = new \DateTime($startDate);
            $endDateTime = new \DateTime($endDate);
            $dailyTotals = [];

            while ($currentDate <= $endDateTime) {
                $dateStr = $currentDate->format('Y-m-d');
                $dailyTotals[] = [
                    'date' => $dateStr,
                    'total' => $dailyTotalsMap[$dateStr] ?? 0
                ];
                $currentDate->modify('+1 day');
            }
        }

        foreach($dailyTotals as $day) {
            $sumDailyTotals = ($sumDailyTotals ?? 0) + $day['total'];
        }

        if ($naturezaId == 'all') {
            $naturezasTotals = Natureza::getTotalsByPeriod($startDate, $endDate, $status);
        } else {
            $naturezasTotals = Natureza::getById($naturezaId)->getNome();
        }
        $suppliersTotals = Fornecedor::getTotalsByPeriod($startDate, $endDate, $status, $naturezaId);
        $costCenterTotals = CentroDeCusto::getTotalsByPeriod($startDate, $endDate, $status, $naturezaId);
        $costCenterGroupedTotals = CentroDeCusto::getGroupedTotalsByPeriod($startDate, $endDate, $status, $naturezaId);

        $chartData = [
            'daily' => $dailyTotals,
            'naturezas' => $naturezasTotals,
            'suppliers' => $suppliersTotals,
            'costCenters' => $costCenterTotals,
            'costCentersGrouped' => $costCenterGroupedTotals
        ];

        $naturezas = Database::getOptions(Natureza::$tableName);

        $contasAtrasadas = Parcela::getOverdueTotals();
        $vencimentosHoje = Parcela::getDueInRangeTotals(date('Y-m-d'), date('Y-m-d'));
        $vencimentosSemana = Parcela::getDueInRangeTotals(date('Y-m-d', strtotime('+1 day')), date('Y-m-d', strtotime('+7 days')));

        include '../src/templates/header.php';
        include '../src/Views/dashboard.php';
        include '../src/templates/footer.php';
    }
}