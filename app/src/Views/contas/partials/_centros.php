<dialog id="modalCentros" closedby="any">
    <div class="card">
        <span id="closeModalCentros" title="Fechar">&times;</span>
        <h3>Selecione o centro de custo</h3>

        <div class="search-wrap">
            <input type="text" id="searchCentro" placeholder="Buscar centro de custo..." autocomplete="off">
            <span id="clearSearchCentro" title="Limpar busca">&times;</span>
        </div>

        <div class="list-wrap">
            <ul id="listCentros">
                <?php foreach ($centros as $centro): ?>
                    <?php if (isset($centro['is_group']) && $centro['is_group'] && !empty($centro['children'])): ?>
                        <li class="centro-item category-item">
                            <div class="category-header" onclick="toggleCategory(this)">
                                <span class="category-name"><?= htmlspecialchars($centro['nome']) ?></span>
                                <span class="toggle-icon">▼</span>
                            </div>
                            <ul class="sub-centros">
                                <?php foreach ($centro['children'] as $child): ?>
                                    <li class="centro-item sub-item" onclick="selectCentro(<?= $child['id'] ?>, <?= htmlspecialchars(json_encode($child['nome']), ENT_QUOTES, 'UTF-8') ?>)">
                                        <span class="sub-name"><?= htmlspecialchars($child['nome']) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
                <li id="noCentrosFound">Nenhum centro de custo encontrado</li>
            </ul>
        </div>
    </div>
</dialog>

<script src="<?= $_ENV['BASE_URL'] ?>/js/modalCentros.js"></script>

<style>
    /*
     * O picker precisa ser um <dialog> (e nao um div com z-index) para
     * participar da top layer: um dialog aberto fica acima de qualquer z-index,
     * entao um div comum nunca apareceria por cima do dialog de origem.
     */
    #modalCentros {
        width: 100%;
        max-width: none;
        height: 100%;
        max-height: none;
        padding: 0;
        border: none;
        background: transparent;
        overflow: visible;
    }

    #modalCentros[open] {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    #modalCentros::backdrop {
        background: rgba(0, 0, 0, 0.5);
    }

    #modalCentros .card {
        width: 90%;
        max-width: 500px;
        max-height: 80vh;
        display: flex;
        flex-direction: column;
        background: white;
        padding: 20px;
        border-radius: 8px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    }

    #closeModalCentros {
        position: absolute;
        top: 10px;
        right: 20px;
        font-size: 28px;
        cursor: pointer;
    }

    #modalCentros h3 {
        margin-top: 0;
        margin-bottom: 20px;
    }

    .search-wrap {
        position: relative;
        margin-bottom: 15px;
    }

    .search-wrap input {
        width: 100%;
        padding: 10px;
        padding-right: 35px;
        box-sizing: border-box;
        border: 1px solid #ccc;
        border-radius: 4px;
    }

    #clearSearchCentro {
        display: none;
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        cursor: pointer;
        color: #888;
        font-weight: bold;
        font-size: 16px;
    }

    .list-wrap {
        overflow-y: auto;
        flex-grow: 1;
        border: 1px solid #eee;
        border-radius: 4px;
        padding: 5px;
    }

    #listCentros {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    #noCentrosFound {
        display: none;
        padding: 15px;
        text-align: center;
        color: #777;
    }

    .sub-centros {
        list-style: none;
        padding-left: 0;
        margin-top: 5px;
        display: none;
    }

    .sub-item {
        padding: 10px 10px 10px 30px;
        cursor: pointer;
        border-radius: 4px;
        border-bottom: 1px solid #f1f1f1;
        transition: background-color 0.2s;
    }

    .category-item {
        margin-bottom: 5px;
    }

    .category-header {
        cursor: pointer;
        padding: 10px;
        border-radius: 4px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border: 1px solid #e9ecef;
        background: #f8f9fa;
    }

    .sub-item:hover,
    .category-header:hover {
        background-color: #d4d4d4;
    }
</style>
