import './echo';

document.addEventListener('alpine:init', () => {
    window.Alpine.store('ui', { sidebarOpen: false });
});