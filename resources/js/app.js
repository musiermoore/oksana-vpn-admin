import './bootstrap';
import './inertia.js'

document.querySelectorAll('.js-remove_confirmation').forEach((el) => {
    el.addEventListener('click', (e) => {
        if (!confirm('Запись будет удалена. Вы уверены?')) {
            e.preventDefault();
        }
    })
})
