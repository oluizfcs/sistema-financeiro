document.addEventListener('DOMContentLoaded', function() {
    const modalCentros = document.getElementById('modalCentros');

    const fecharModal = function() {
        document.getElementById('searchCentro').value = '';
        triggerSearch('');
        modalCentros.close();
    };

    const btnSelectCentro = document.getElementById('btnSelectCentro');
    if (btnSelectCentro) {
        btnSelectCentro.addEventListener('click', function() {
            modalCentros.showModal();
            document.getElementById('searchCentro').focus();
        });
    }

    const closeModalCentros = document.getElementById('closeModalCentros');
    if (closeModalCentros) {
        closeModalCentros.addEventListener('click', fecharModal);
    }

    if (modalCentros) {
        // cobre o fechamento por Esc e por clique no backdrop (closedby="any")
        modalCentros.addEventListener('close', function() {
            document.getElementById('searchCentro').value = '';
            triggerSearch('');
        });
    }

    const searchCentro = document.getElementById('searchCentro');
    if (searchCentro) {
        searchCentro.addEventListener('input', function() {
            triggerSearch(this.value);
        });
    }

    const clearSearchCentro = document.getElementById('clearSearchCentro');
    if (clearSearchCentro) {
        clearSearchCentro.addEventListener('click', function() {
            const searchInput = document.getElementById('searchCentro');
            if (searchInput) {
                searchInput.value = '';
                searchInput.focus();
                triggerSearch('');
            }
        });
    }
});

function toggleCategory(element) {
    const subList = element.nextElementSibling;
    const icon = element.querySelector('.toggle-icon');
    if (subList.style.display === 'none' || subList.style.display === '') {
        subList.style.display = 'block';
        icon.textContent = '▲';
    } else {
        subList.style.display = 'none';
        icon.textContent = '▼';
    }
}

function selectCentro(id, nome) {
    document.getElementById('centro').value = id;
    document.getElementById('btnSelectCentroText').textContent = nome;
    document.getElementById('modalCentros').close();

    document.getElementById('searchCentro').value = '';
    triggerSearch('');
}

function triggerSearch(term) {
    term = term.toLowerCase();
    const categories = document.querySelectorAll('.category-item');
    const standaloneItems = document.querySelectorAll('#listCentros > .sub-item');
    let anyMatch = false;
    
    categories.forEach(category => {
        const categoryName = category.querySelector('.category-name').textContent.toLowerCase();
        const subItems = category.querySelectorAll('.sub-item');
        let hasMatch = false;
        
        subItems.forEach(sub => {
            const subName = sub.querySelector('.sub-name').textContent.toLowerCase();
            if (subName.includes(term) || categoryName.includes(term)) {
                sub.style.display = 'block';
                hasMatch = true;
            } else {
                sub.style.display = 'none';
            }
        });
        
        const subList = category.querySelector('.sub-centros');
        const icon = category.querySelector('.toggle-icon');
        if (term !== '') {
            if (hasMatch) {
                category.style.display = 'block';
                subList.style.display = 'block';
                icon.textContent = '▲';
                anyMatch = true;
            } else {
                category.style.display = 'none';
            }
        } else {
            category.style.display = 'block';
            subList.style.display = 'none';
            icon.textContent = '▼';
            subItems.forEach(sub => sub.style.display = 'block');
            anyMatch = true;
        }
    });
    
    standaloneItems.forEach(item => {
        const itemName = item.querySelector('.sub-name').textContent.toLowerCase();
        if (itemName.includes(term)) {
            item.style.display = 'block';
            anyMatch = true;
        } else {
            item.style.display = 'none';
        }
    });

    const noCentrosFound = document.getElementById('noCentrosFound');
    if (noCentrosFound) {
        if (!anyMatch && term !== '') {
            noCentrosFound.style.display = 'block';
        } else {
            noCentrosFound.style.display = 'none';
        }
    }
    
    const clearSearchBtn = document.getElementById('clearSearchCentro');
    if (clearSearchBtn) {
        if (term !== '') {
            clearSearchBtn.style.display = 'block';
        } else {
            clearSearchBtn.style.display = 'none';
        }
    }
}
