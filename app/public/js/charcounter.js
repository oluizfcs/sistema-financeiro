document.querySelectorAll('.input-wrapper').forEach(wrapper => {
    const field = wrapper.querySelector('input[maxlength], textarea[maxlength]');

    if (field) {
        const max = field.getAttribute('maxlength');

        const updateCounter = () => {
            if(field.value.length == max) {
                alert("Limite de caracteres atingido!");
            }
        };

        field.addEventListener('input', updateCounter);
    }
});