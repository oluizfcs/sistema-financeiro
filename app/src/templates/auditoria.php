<?php
if (!isAdmin()) exit; //fallback
use App\Models\Usuario;
use App\Models\Banco;
use App\Models\Natureza;
use App\Models\CentroDeCusto;
use App\Models\Fornecedor;
use App\Controllers\Services\Money;
?>
<div class="section">
    <h2><i class="fa-solid fa-scroll"></i> Auditoria</h2>
    <?php
    echo "Cadastrado em: " . $data_criacao->format("d/m/Y \à\s H:i");
    if (isset($data_edicao)) {
        echo "<br>Última modificação: $data_edicao";
    }
    ?>

    <div class="table-section">
        <table id="audit-table" class="non-clickable-table">
            <tr>
                <th>Usuário</th>
                <th>Ação</th>
                <th>Campo</th>
                <th>Antigo</th>
                <th>Novo</th>
                <th>Data e Hora</th>
            </tr>
            <?php 
            $query = explode('/', $_SERVER['QUERY_STRING']);
            if ($query[0] == 'url=contas' && $query[1] == 'detalhar') {
                $numeroParcelaBanco = [];
                foreach($logs as $log) {
                    if(str_contains($log['campo'], 'Parcela ') && !str_contains($log['campo'], 'Estorno')) {
                        $parts = explode(' ', $log['campo']);
                        $numero = (int) end($parts);
                        $parcela = null;
                        foreach($conta->getParcelas() as $p) {
                            if($p->getNumero_parcela() == $numero) {
                                $parcela = $p;
                            }
                        }
                        if ($parcela !== null && $parcela->getBanco_id() !== null) {
                            $numeroParcelaBanco[$numero] = Banco::getById($parcela->getBanco_id())->getNome();
                        }
                    }
                }
            }

            foreach ($logs as $log) {
                $usuario = Usuario::getById($log['usuario_id'])->getNome();
                $data = DateTime::createFromFormat('Y-m-d H:i:s', $log['data_log'])->format('d/m/Y \à\s H:i');

                $msg = "<br> $usuario";
                $acao = 'atualizar';
                $campo = $log['campo'];
                $antigo = $log['valor_antigo'];
                $novo = $log['valor_novo'];

                if (in_array($log['campo'], ['create', 'unable', 'enable', 'pay'])) {
                    $campo = '-';
                    $antigo = '-';
                    $novo = '-';

                    switch ($log['campo']) {
                        case 'create':
                            $acao = 'cadastrar';
                            break;
                        case 'unable':
                            $acao = 'inativar';
                            break;
                        case 'enable':
                            $acao = 'ativar';
                    }
                }

                if (str_contains($log['campo'], 'Parcela ') && !str_contains($log['campo'], 'Estorno')) {
                    $acao = 'pagar';
                    $parts = explode(' ', $log['campo']);
                    $num = end($parts);
                    if (isset($numeroParcelaBanco[$num])) {
                        $acao .= ' <br> <span style="font-size: smaller; color: #555">Banco: ' . $numeroParcelaBanco[$num] . '</span>';
                    }
                }

                if (str_contains($log['campo'], 'Estorno')) {
                    $acao = 'estornar';
                }

                if (str_contains($log['campo'], 'excluído')) {
                    $acao = 'excluir';
                }

                if ($log['campo'] === 'valor' || str_contains($log['campo'], 'juros')) {
                    $antigo = 'R$ ' . Money::centavos_para_reais($antigo);
                    $novo = 'R$ ' . Money::centavos_para_reais($novo);
                }

                if ($log['campo'] === 'vencimento') {
                    $antigo = implode('/', array_reverse(explode('-', $antigo)));
                    $novo = implode('/', array_reverse(explode('-', $novo)));
                }

                if (in_array($log['campo'], ['natureza_id', 'centro_de_custo_id', 'fornecedor_id'])) {
                    $campo = str_replace('_id', '', $log['campo']);
                    $map = [
                        'natureza_id' => Natureza::class,
                        'centro_de_custo_id' => CentroDeCusto::class,
                        'fornecedor_id' => Fornecedor::class,
                    ];
                    $class = $map[$log['campo']];
                    $antigoObj = $antigo ? $class::getById((int)$antigo) : null;
                    $novoObj = $novo ? $class::getById((int)$novo) : null;
                    $antigo = $antigoObj ? $antigoObj->getNome() : '-';
                    $novo = $novoObj ? $novoObj->getNome() : '-';
                }

                echo "<tr>";
                echo "<td>$usuario</td>";
                echo "<td>$acao</td>";
                echo "<td>$campo</td>";
                echo "<td>$antigo</td>";
                echo "<td>$novo</td>";
                echo "<td>$data</td>";
                echo "</tr>";
            }
            ?>
        </table>
    </div>
</div>

<script>
    [...document.getElementById("audit-table").childNodes[1].childNodes].splice(1).forEach(e => {
        if (e.tagName != "TR") {
            return;
        }

        e.childNodes.forEach(td => {
            if (td.tagName != "TD" || td.textContent.length <= 50) {
                return;
            }

            let savedText = td.textContent;
            td.textContent = td.textContent.slice(0, 50) + "... ";
            const a = document.createElement("a");
            a.textContent = "Ler mais";
            a.classList.add("clickable-text")
            td.appendChild(a);

            a.addEventListener("click", function () {
                td.innerHTML = savedText;
            });
        });
    });
</script>