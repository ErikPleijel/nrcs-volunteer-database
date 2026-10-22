import './bootstrap';
import 'leaflet/dist/leaflet.css';
import '../css/app.css';


// Fix for Leaflet default markers
import L from 'leaflet';
import './components/location-cascade.js';

delete L.Icon.Default.prototype._getIconUrl;
L.Icon.Default.mergeOptions({
    iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon-2x.png',
    iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon.png',
    shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
});

// Forms marked data-submit-once ignore a second submit (double-click, impatient
// re-click) and grey out their buttons. Cancelled submits (confirm() = No) are
// left alone. Buttons are disabled on the next tick so the submitter's own
// name/value still goes with the request.
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-submit-once') || event.defaultPrevented) {
        return;
    }
    if (form.dataset.submitting === '1') {
        event.preventDefault();
        return;
    }
    form.dataset.submitting = '1';
    setTimeout(() => {
        form.querySelectorAll('button[type="submit"], button:not([type]), [data-submit-button]').forEach((button) => {
            button.disabled = true;
            button.classList.add('opacity-60', 'cursor-wait');
        });
    }, 0);
});

// Coming back with the browser's Back button restores the page from cache with
// the buttons still disabled; re-enable them.
window.addEventListener('pageshow', (event) => {
    if (!event.persisted) {
        return;
    }
    document.querySelectorAll('form[data-submit-once][data-submitting="1"]').forEach((form) => {
        delete form.dataset.submitting;
        form.querySelectorAll('button.cursor-wait').forEach((button) => {
            button.disabled = false;
            button.classList.remove('opacity-60', 'cursor-wait');
        });
    });
});

// Import and make BranchMap globally available
import BranchMap from './components/BranchMap.js';
window.BranchMap = BranchMap;

// Alpine.js — guarded so pages that already load the CDN version (app.blade.php)
// do not double-initialize; CDN defer script runs before this module, so
// window.Alpine is already set on those pages and we skip.
import Alpine from 'alpinejs';
if (!window.Alpine) {
    window.Alpine = Alpine;
    Alpine.start();
}
