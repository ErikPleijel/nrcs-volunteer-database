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
