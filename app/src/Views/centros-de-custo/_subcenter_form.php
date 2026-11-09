<div id="newSubCenterModal" class="modal" style="display:none; position:fixed; z-index:999; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.4);">
    <div class="modal-content" style="background-color:#fefefe; margin:15% auto; padding:20px; border:1px solid #888; width:40%; border-radius: 8px;">
        <span class="close" onclick="closeModal()" style="color:#aaa; float:right; font-size:28px; font-weight:bold; cursor:pointer;">&times;</span>
        <h2>Cadastrar Sub-centro</h2>
        <form id="newSubCenterForm">
            <input type="hidden" name="type" value="create">
            <input type="hidden" name="ajax" value="true">
            <input type="hidden" name="categoria_id" id="subcenter_categoria_id" value="">

            <label for="new_nome">Nome:</label>
            <div class="input-wrapper">
                <input type="text" id="new_nome" name="nome" required maxlength="45" autocomplete="off">
            </div>
            <br>
            <button class="btn btn-primary" type="submit">Cadastrar</button>
        </form>
    </div>
</div>

<script>
    function openSubcostCenterModal(categoriaId) {
        document.getElementById('subcenter_categoria_id').value = categoriaId;
        document.getElementById('newSubCenterModal').style.display = 'block';
        document.getElementById('new_nome').focus();
    }

    function closeModal() {
        document.getElementById('newSubCenterModal').style.display = 'none';
    }

    document.getElementById('newSubCenterForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const submitBtn = this.querySelector('button[type="submit"]');
        submitBtn.disabled = true;

        const formData = new FormData(this);

        fetch('<?= $_ENV['BASE_URL'] ?>/centros-de-custo', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    submitBtn.disabled = false;
                    alert('Erro: ' + (data.message || 'Desconhecido'));
                }
            })
            .catch(error => {
                submitBtn.disabled = false;
                console.error('Error:', error);
                alert('Erro ao processar requisição.');
            });
    });

    window.onclick = function(event) {
        if (event.target == document.getElementById('newSubCenterModal')) {
            closeModal();
        }
    }
</script>