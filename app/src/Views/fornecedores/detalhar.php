<?php
$data_criacao = DateTime::createFromFormat("Y-m-d H:i:s", $fornecedor->getData_criacao());
if ($fornecedor->getData_edicao() != null) {
    $data_edicao = DateTime::createFromFormat("Y-m-d H:i:s", $fornecedor->getData_edicao());
    $data_edicao = $data_edicao->format("d/m/Y \à\s H:i");
} else {
    $data_edicao = 'nunca';
}

use App\Controllers\Services\Money;

?>

<h1>Fornecedor: <?= $fornecedor->getNome() ?></h1>
Telefone: <?= $fornecedor->getTelefone() ?? '<span style="color: #999;">N/A</span>' ?> <br>
<br>
<a class="btn btn-secondary" href="<?= $_ENV['BASE_URL'] ?>/fornecedores">Voltar</a>
<a class="btn btn-secondary" href="<?= $_ENV['BASE_URL'] ?>/fornecedores/atualizar/<?= $fornecedor->getId() ?>"><i
        class="fa-solid fa-pen-to-square"></i> Atualizar</a>

<form method="POST" action="">
    <input type="hidden" name="fornecedor_id" value="<?= $fornecedor->getId() ?>">
    <?php if ($fornecedor->isEnabled()): ?>
        <button class="btn btn-secondary" name="type" value="unable"
            onclick="return confirm('Realmente deseja inativar este fornecedor?')">
            <i class="fa-solid fa-box-archive"></i> Inativar
        </button>
    <?php else: ?>
        <button class="btn btn-secondary" name="type" value="enable"
            onclick="return confirm('Realmente deseja ativar este fornecedor?')">
            <i class="fa-solid fa-box-open"></i> Ativar
        </button>
    <?php endif; ?>
</form>

<?php if (count($contas) > 0): ?>
    <div class="section">
        <details>
            <summary style="cursor: pointer;">Ver contas a pagar</summary>
            <div class="table-section">
                <table id="contas-table">
                    <tr>
                        <th>Centro de Custo</th>
                        <th>Próximo vencimento</th>
                        <th>Parcelas pagas</th>
                        <th>Valor Total (R$)</th>
                    </tr>
                    <?php foreach ($contas as $conta): ?>
                        <?php

                        $nextInstallment = null;
                        $paidInstallments = 0;
                        $nextInstallmentPrice = null;

                        foreach ($conta->getParcelas() as $parcela) {
                            if (!$parcela->isPaid() && $nextInstallment == null) {
                                $nextInstallment = new DateTime($parcela->getData_vencimento())->format('d/m/Y');
                                $nextInstallmentPrice = Money::centavos_para_reais($parcela->getValor_em_centavos());
                            }

                            if ($parcela->isPaid()) {
                                $paidInstallments += 1;
                            }
                        }
                        ?>
                        <tr onclick="window.open('<?= $_ENV['BASE_URL'] ?>/contas/detalhar/<?= $conta->getId() ?>', '_blank');">
                            <td><?= $conta->centro_de_custo ?></td>
                            <?php if ($nextInstallment == null): ?>
                                <td>-</td>
                            <?php else: ?>
                                <td><?= "$nextInstallment<br><span style='color: #777; font-size: smaller;'>(R$ $nextInstallmentPrice)</span>" ?>
                                </td>
                            <?php endif; ?>
                            <td>
                                <?= $paidInstallments . '/' . count($conta->getParcelas()) ?>
                            </td>
                            <td><?= Money::centavos_para_reais($conta->getValor_em_centavos()) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </details>
    </div>
<?php endif; ?>

<?php include '../src/templates/auditoria.php'; ?>