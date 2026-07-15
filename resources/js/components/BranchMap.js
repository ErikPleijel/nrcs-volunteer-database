import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

class BranchMap {
    constructor(containerId, options = {}) {
        this.containerId = containerId;

        // Configuration options
        this.options = {
            center: options.center || [9.0820, 8.6753], // Nigeria center
            zoom: options.zoom || 6,
            branches: options.branches || [],
            apiEndpoint: options.apiEndpoint || '/api/branches/map-data',
            showStatistics: options.showStatistics !== false, // default true
            popupTemplate: options.popupTemplate || 'default',
            markerIcon: options.markerIcon || 'default',
            onMarkerClick: options.onMarkerClick || null,
            loadStatsOnDemand: options.loadStatsOnDemand !== false, // default true
            ...options
        };

        this.map = null;
        this.markers = [];
        this.loadingStats = new Set(); // Track which branches are loading stats
    }

    async init() {
        this.initializeMap();
        this.fixDefaultMarkers();

        // Load branches if not provided
        if (this.options.branches.length === 0) {
            await this.loadBranchesFromAPI();
        }

        this.addBranchMarkers();
        this.fitMapToMarkers();
    }

    initializeMap() {
        this.map = L.map(this.containerId).setView(this.options.center, this.options.zoom);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 18,
        }).addTo(this.map);
    }

    async loadBranchesFromAPI() {
        try {
            const response = await fetch(this.options.apiEndpoint);
            const data = await response.json();
            this.options.branches = data.branches || data;
        } catch (error) {
            console.error('Failed to load branches:', error);
        }
    }

    addBranchMarkers() {
        this.options.branches.forEach(branch => {
            if (branch.latitude && branch.longitude) {
                const marker = this.createMarker(branch);
                this.markers.push(marker);
            }
        });
    }

    createMarker(branch) {
        const marker = L.marker([parseFloat(branch.latitude), parseFloat(branch.longitude)])
            .addTo(this.map);

        // Configure popup based on options
        if (this.options.showStatistics) {
            marker.bindPopup(this.createLoadingPopup(branch), {
                maxWidth: 400,
                className: 'branch-popup-wrapper'
            });

            // Load stats when popup opens if loadStatsOnDemand is true
            if (this.options.loadStatsOnDemand) {
                marker.on('popupopen', () => this.loadBranchStats(branch, marker));
            } else {
                marker.bindPopup(this.createPopupContent(branch), {
                    maxWidth: 400,
                    className: 'branch-popup-wrapper'
                });
            }
        } else {
            marker.bindPopup(this.createBasicPopup(branch));
        }

        // Custom click handler
        if (this.options.onMarkerClick) {
            marker.on('click', () => this.options.onMarkerClick(branch));
        }

        marker.branchId = branch.id;
        marker.branchData = branch;

        return marker;
    }

    createLoadingPopup(branch) {
        return `
            <div class="branch-popup">
                <div class="popup-header">
                    <h3 class="popup-title">${branch.name}</h3>
                    <span class="branch-status ${branch.is_active ? 'status-active' : 'status-inactive'}">
                        ${branch.is_active ? 'Active' : 'Inactive'}
                    </span>
                </div>
                <div class="popup-content">
                    <p class="popup-address">${branch.physical_address || branch.address || 'Branch Location'}</p>
                    <div class="loading-stats">
                        <div class="loading-spinner"></div>
                        <p>Loading statistics...</p>
                    </div>
                </div>
            </div>
        `;
    }

    async loadBranchStats(branch, marker) {
        if (this.loadingStats.has(branch.id) || branch.statistics) {
            return;
        }

        this.loadingStats.add(branch.id);

        try {
            const response = await fetch(`/api/branches/${branch.id}/statistics`);
            const data = await response.json();

            branch.statistics = data.statistics || data;
            marker.setPopupContent(this.createPopupContent(branch));
        } catch (error) {
            console.error(`Failed to load stats for branch ${branch.id}:`, error);
            marker.setPopupContent(this.createErrorPopup(branch, error.message));
        } finally {
            this.loadingStats.delete(branch.id);
        }
    }

    createPopupContent(branch) {
        if (this.options.popupTemplate === 'minimal') {
            return this.createMinimalPopup(branch);
        }

        return this.createFullPopup(branch);
    }

    createBasicPopup(branch) {
        return `
            <div class="branch-popup">
                <div class="popup-header">
                    <h3 class="popup-title">${branch.name}</h3>
                    <span class="branch-status ${branch.is_active ? 'status-active' : 'status-inactive'}">
                        ${branch.is_active ? 'Active' : 'Inactive'}
                    </span>
                </div>
                <div class="popup-content">
                    <p class="popup-address">${branch.physical_address || branch.address || 'Branch Location'}</p>
                    ${branch.telephone ? `<p><strong>Tel:</strong> ${branch.telephone}</p>` : ''}
                    ${branch.email ? `<p><strong>Email:</strong> ${branch.email}</p>` : ''}
                </div>
            </div>
        `;
    }

    createMinimalPopup(branch) {
        return `
            <div class="branch-popup">
                <div class="popup-header">
                    <h3 class="popup-title">${branch.name}</h3>
                </div>
                <div class="popup-content">
                    <p class="popup-address">${branch.physical_address || 'Branch Location'}</p>
                </div>
            </div>
        `;
    }

    createFullPopup(branch) {
        return `
            <div class="branch-popup">
                <div class="popup-header">
                    <h3 class="popup-title">${branch.name}</h3>
                    <span class="branch-status ${branch.is_active ? 'status-active' : 'status-inactive'}">
                        ${branch.is_active ? 'Active' : 'Inactive'}
                    </span>
                </div>
                <div class="popup-content">
                    <p class="popup-address">${branch.physical_address || branch.address || 'Branch Location'}</p>
                    <div class="popup-stats">
                        ${this.renderStatistics(branch.statistics)}
                    </div>
                </div>
                <div class="popup-actions">
                    <button class="btn-view-details" onclick="window.location.href='/branches/${branch.id}'">
                        View Details
                    </button>
                    <button class="btn-refresh-stats" onclick="this.refreshBranchStats(${branch.id})">
                        Refresh Stats
                    </button>
                </div>
            </div>
        `;
    }

    createErrorPopup(branch, errorMessage) {
        return `
            <div class="branch-popup">
                <div class="popup-header">
                    <h3 class="popup-title">${branch.name}</h3>
                </div>
                <div class="popup-content">
                    <p class="popup-address">${branch.physical_address || 'Branch Location'}</p>
                    <div class="stats-error">
                        <div class="error-icon">⚠️</div>
                        <p class="error-message">Failed to load statistics</p>
                        <p class="error-detail">${errorMessage}</p>
                        <button class="btn-retry" onclick="this.retryLoadStats(${branch.id})">
                            Try Again
                        </button>
                    </div>
                </div>
            </div>
        `;
    }

    renderStatistics(stats) {
        if (!stats) {
            return '<div class="stats-error"><p class="error-message">Statistics not available</p></div>';
        }

        // Your existing renderStatistics logic...
        return `
            <div class="stats-grid">
                <!-- Your existing statistics rendering -->
                ${this.renderRedCrossUnits(stats)}
                ${this.renderVolunteers(stats)}
                ${this.renderTrainings(stats)}
                ${this.renderTaskForces(stats)}
                ${this.renderActivities(stats)}
            </div>
        `;
    }

    // Existing render methods...
    renderRedCrossUnits(stats) {
        return `
            <div class="stat-section">
                <h4 class="stat-section-title">Red Cross Units</h4>
                <div class="stat-row">
                    <div class="stat-item">
                        <span class="stat-label">Total:</span>
                        <span class="stat-value">${stats.redcross_units?.total_units || 0}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Active:</span>
                        <span class="stat-value active-color">${stats.redcross_units?.active_units || 0}</span>
                    </div>
                </div>
            </div>
        `;
    }

    renderVolunteers(stats) {
        return `
            <div class="stat-section">
                <h4 class="stat-section-title">Volunteers</h4>
                <div class="stat-row">
                    <div class="stat-item">
                        <span class="stat-label">Total:</span>
                        <span class="stat-value">${stats.volunteers?.total_volunteers || 0}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Active:</span>
                        <span class="stat-value active-color">${stats.volunteers?.active_volunteers || 0}</span>
                    </div>
                </div>
            </div>
        `;
    }

    renderTrainings(stats) {
        return `
            <div class="stat-section">
                <h4 class="stat-section-title">Trainings</h4>
                <div class="stat-row">
                    <div class="stat-item">
                        <span class="stat-label">Active:</span>
                        <span class="stat-value">${stats.trainings?.active_trainings || 0}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Hours:</span>
                        <span class="stat-value">${stats.trainings?.total_hours || 0}h</span>
                    </div>
                </div>
            </div>
        `;
    }

    renderTaskForces(stats) {
        return `
            <div class="stat-section">
                <h4 class="stat-section-title">Task Forces</h4>
                <div class="stat-row">
                    <div class="stat-item">
                        <span class="stat-label">Active:</span>
                        <span class="stat-value">${stats.task_forces?.active_task_forces || 0}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Members:</span>
                        <span class="stat-value">${stats.task_forces?.total_members || 0}</span>
                    </div>
                </div>
            </div>
        `;
    }

    renderActivities(stats) {
        return `
            <div class="stat-section">
                <h4 class="stat-section-title">Activities</h4>
                <div class="stat-row">
                    <div class="stat-item">
                        <span class="stat-label">Total:</span>
                        <span class="stat-value">${stats.activities?.total_activities || 0}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Hours:</span>
                        <span class="stat-value">${stats.activities?.total_hours || 0}h</span>
                    </div>
                </div>
            </div>
        `;
    }

    // Utility methods
    fitMapToMarkers() {
        if (this.markers.length === 0) return;

        const group = new L.featureGroup(this.markers);
        this.map.fitBounds(group.getBounds().pad(0.1));
    }

    fixDefaultMarkers() {
        delete L.Icon.Default.prototype._getIconUrl;
        L.Icon.Default.mergeOptions({
            iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon-2x.png',
            iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-icon.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/images/marker-shadow.png',
        });
    }

    // Public API methods
    refreshBranchStats(branchId) {
        const marker = this.markers.find(m => m.branchId === branchId);
        const branch = this.options.branches.find(b => b.id === branchId);

        if (marker && branch) {
            branch.statistics = null; // Clear cached stats
            this.loadBranchStats(branch, marker);
        }
    }

    addBranch(branch) {
        this.options.branches.push(branch);
        const marker = this.createMarker(branch);
        this.markers.push(marker);
        return marker;
    }

    removeBranch(branchId) {
        const markerIndex = this.markers.findIndex(m => m.branchId === branchId);
        if (markerIndex !== -1) {
            this.map.removeLayer(this.markers[markerIndex]);
            this.markers.splice(markerIndex, 1);
        }

        const branchIndex = this.options.branches.findIndex(b => b.id === branchId);
        if (branchIndex !== -1) {
            this.options.branches.splice(branchIndex, 1);
        }
    }

    destroy() {
        if (this.map) {
            this.map.remove();
            this.map = null;
        }
        this.markers = [];
    }
}

export default BranchMap;
