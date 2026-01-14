import './bootstrap';
import collapse from '@alpinejs/collapse'

// Livewire 3 ya incluye Alpine, solo registramos plugins adicionales
document.addEventListener('livewire:init', () => {
    window.Alpine.plugin(collapse)
})
