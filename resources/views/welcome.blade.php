<x-layouts.app title="Red Cross Volunteers - Welcome">
    <x-slot name="styles">
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
              integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
              crossorigin=""/>

        <style>
            .custom-div-icon {
                background: none;
                border: none;
            }

            /* NRCS logo marker */
            .nrcs-logo-marker {
                width: 32px;
                height: 32px;
                border-radius: 50%;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
                border: 2px solid #dc2626;
                overflow: hidden;
                background: white;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .nrcs-logo-marker img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                border-radius: 50%;
            }

            /* ---------- Popup: z-index management ---------- */
            .leaflet-popup {
                z-index: 1000 !important;
            }

            .leaflet-popup-pane {
                z-index: 1000 !important;
            }

            .leaflet-control-zoom {
                z-index: 500 !important;
            }

            /* ---------- Popup: content styles ---------- */
            .custom-popup .leaflet-popup-content-wrapper {
                width: auto !important;
                max-width: 378px;
                border-radius: 8px;
            }

            .custom-popup .leaflet-popup-content {
                width: auto !important;
                margin: 8px 8px !important;
            }

            .custom-popup .leaflet-popup-content > div {
                min-width: unset !important;
                width: auto !important;
            }

            .custom-popup .leaflet-popup-content h3 {
                display: block;
                text-align: left !important;
                margin: 0;
                line-height: 1.25;
                word-wrap: break-word;
                white-space: normal;
            }

            .divisions-text {
                font-size: 11px;
                color: #374151;
                line-height: 1.4;
                word-wrap: break-word;
            }

            .no-divisions {
                font-style: italic;
                color: #6b7280;
                font-size: 11px;
            }

            /* ---------- Branch name floating labels ---------- */
            .branch-text-label {
                background: none;
                border: none;
                width: auto !important;
                height: auto !important;
            }

            .branch-name-label {
                background: rgba(255, 255, 255, 0.95);
                color: #dc2626;
                font-size: 12px;
                font-weight: bold;
                padding: 1px 4px;
                border-radius: 3px;
                white-space: nowrap;
                box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
                border: 1px solid #dc2626;
                line-height: 1.2;
                position: relative;
                transform: translateX(-50%);
                display: inline-block;
            }

            @media (max-width: 768px) {
                .branch-name-label {
                    font-size: 10px;
                    padding: 0 3px;
                }
            }

            /* ---------- Division markers on zoom-in ---------- */
            .division-popup h4 {
                font-size: 13px;
                font-weight: 600;
                margin-bottom: 2px;
            }

            .division-popup p {
                font-size: 11px;
                margin: 0;
                color: #4b5563;
            }

            /* Division marker container (for divIcon) */
            .leaflet-div-icon.division-div-icon {
                background: transparent;
                border: none;
            }

            /* Horizontal layout: red cross + label */
            .division-marker {
                display: inline-flex;
                align-items: center;
                padding: 2px 4px;
                border-radius: 9999px;
                background: rgba(255, 255, 255, 0.9);
                box-shadow: 0 1px 3px rgba(0,0,0,0.25);
                border: 1px solid #fecaca; /* light red border */
            }

            /* Red circular outline with red cross on white background */
            .division-marker-icon {
                width: 18px;
                height: 18px;
                border-radius: 9999px;       /* make it a circle */
                background: #ffffff;         /* white background */
                border: 2px solid #dc2626;   /* red circular outline */
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 4px;
            }

            .division-marker-cross {
                color: #dc2626;   /* red cross */
                font-size: 17px;  /* adjust to fit nicely inside circle */
                line-height: 1;
                font-weight: 700;
            }


            /* Division name label */
            .division-marker-label {
                font-size: 11px;
                color: #1f2933; /* gray-800 */
                white-space: nowrap;
                max-width: 140px;
                overflow: hidden;
                text-overflow: ellipsis;
            }
        </style>
    </x-slot>



    <section class="watermark-bg-white text-gray-900">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-5 mt-16">
            <div class="text-center">
                <div style="display: flex; flex-direction: column; align-items: center;">
                    <div style="max-width: 500px; width: 100%; box-sizing: border-box; background-color: #d9381e; color: #ffffff; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; padding: 16px 24px; border-radius: 6px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        <h2 style="margin: 0 0 8px 0; font-size: 22px; font-weight: 700; letter-spacing: -0.5px;">
                            ⚠️ Development Version
                        </h2>
                        <p style="margin: 0; font-size: 15px; line-height: 1.5; color: #fbebeb;">
                            <strong>RESTRICTED ACCESS:</strong> This database contains confidential personal data. Do not share any information or screenshots outside of administrative team of the NRCS.
                        </p>
                    </div>

                    <div style="max-width: 500px; width: 100%; box-sizing: border-box; background-color: #f0f7ff; border: 1px solid #cce3ff; border-radius: 6px; color: #1e293b; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; padding: 20px; margin-bottom: 20px;">
                        <h3 style="margin: 0 0 10px 0; font-size: 18px; color: #0369a1; font-weight: 600;">
                            👋 Welcome to the Sandbox!
                        </h3>
                        <p style="margin: 0 0 12px 0; font-size: 14px; line-height: 1.5;">
                            Please feel free to explore, click around, and test the features. This is an isolated environment, so <strong>you cannot break anything or cause any harm</strong> to live data.
                        </p>
                        <p style="margin: 0; font-size: 14px; line-height: 1.5;">
                            <strong>We want your feedback:</strong> Let us know what you think! Any note, big or small, helps us improve and adapt the database to your workflow.
                        </p>
                    </div>
                </div>

                <h1 class="text-3xl md:text-5xl font-bold mb-6">

                    Welcome to the Nigeria Red Cross Volunteer Management&nbsp;System
                    <br>
                </h1>
                <p class="text-xl text-gray-700 mb-12 max-w-4xl mx-auto">
                    Join our community of dedicated volunteers making a difference in people's lives.
                    Together, we provide humanitarian aid, disaster relief, and support to those in need.
                </p>
            </div>
        </div>
    </section>

    @auth
        @if(auth()->user()->is_super_admin)
            <div class="max-w-3xl mx-auto mt-6 rounded-lg border-2 border-indigo-300 bg-indigo-50 p-5 text-center">
                <p class="text-indigo-900 font-semibold">
                    You are signed in as a Super Administrator.
                </p>
                <p class="mt-1 text-sm text-indigo-900">
                    Your task is appointing National Database Administrators. Please proceed to the Admin page.
                </p>
                <a href="{{ route('reports.dashboard') }}"
                   class="mt-3 inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded shadow">
                    <i class="fas fa-arrow-right"></i> Go to Admin page
                </a>
            </div>
        @endif
    @endauth

    <section class="watermark-bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-1">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Our Branch Network</h2>
                @if($branchesCount > 0)
                    <p class="text-lg text-gray-500 mt-2">
                        We have branches in all 37 states of Nigeria, the Federal Capital Territory,
                        and are present in all 774 local government areas across the federation.
                    </p>

                @endif
            </div>

            <div class="watermark-bg-gray-50 rounded-lg shadow-lg p-4">
                <div class="mb-6 text-center">
                    <p class="text-lg text-gray-600 font-medium">
                        Click on the markers to view branch statistics, contact details and zoom into divisions
                    </p>
                </div>

                <div id="branchesMap" class="h-[28rem] w-full rounded-lg bg-gray-100">
                    @if($branchesCount == 0)
                        <div class="flex items-center justify-center h-full">
                            <div class="text-center text-gray-500">
                                <i class="fas fa-map-marker-alt text-4xl mb-4"></i>
                                <p>No branch locations available</p>
                            </div>
                        </div>
                    @endif
                </div>

                @if($branchesCount > 0)
                    <div class="mt-4 flex flex-col sm:flex-row gap-4 justify-between items-center">
                        {{-- Placeholder for future map controls/legend if needed --}}
                    </div>
                @endif
            </div>
        </div>
    </section>


    @auth
        <section class="watermark-bg-white text-gray-900">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
                <div class="text-center max-w-3xl mx-auto">

                    <h3 class="text-3xl font-bold text-gray-900 mb-4">
                        Welcome {{ auth()->user()->first_name }} {{ auth()->user()->last_name }}!
                    </h3>

                    @auth
                        @php
                            /** @var \App\Models\User $u */
                            $u = auth()->user();

                            $isMember      = (bool) $u->currentMembershipPayment;
                            $wantsMembership = (bool) $u->wantsMembership();
                            $membershipName  = $u->current_membership_name; // accessor suggested earlier
                        @endphp

                        @if ($isMember)
                            {{-- ✅ Member: show membership type --}}
                            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-900">
                                <div class="font-semibold">
                                    Your membership:
                                </div>
                                <div class="mt-1 text-2xl">
                                    <span class="font-medium">{{ $membershipName ?: 'Member' }}</span>
                                </div>
                            </div>

                        @elseif ($wantsMembership)
                            {{-- 🙋 Interested: show payment instructions --}}
                            <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-blue-900">
                                <div class="font-semibold">
                                    Become a member
                                </div>

                                <div class="mt-2 text-base space-y-2">
                                    <p>
                                        You’ve marked that you’re interested in membership.
                                    </p>
                                    <p>
                                        To activate it, please make your membership payment.
                                    </p>
                                </div>


                            </div>

                        @else
                            {{-- ❌ Not a member and not interested --}}
                            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-gray-800">
                                <div class="font-semibold">
                                    Membership status
                                </div>
                                <div class="mt-1 text-sm">
                                    Not a member.
                                </div>
                            </div>
                        @endif
                    @endauth


                    <p class="text-lg text-gray-700 mb-4 mt-8">
                        Take a moment to check your profile and make sure your details are up to date.
                    </p>

                    <a href="{{ route('profile.show') }}"
                       class="inline-flex items-center gap-3 bg-red-600 hover:bg-red-700 text-white font-semibold px-6 py-3 rounded-lg shadow transition duration-200">
                        <i class="fas fa-user-circle text-xl"></i>
                        Go to My Profile
                    </a>

                </div>
            </div>
        </section>
    @endauth


    <section class="watermark-bg-white text-gray-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="text-center">
                <div class="mb-12 mt-16">
                    <h3 class="text-3xl font-bold text-gray-900 mb-4">Ready to Make a Difference?</h3>
                </div>

                <div class="flex flex-col sm:flex-row gap-6 justify-center items-center">
                    <a href="{{ route('volunteer.journey') }}"
                       class="bg-red-600 text-white px-8 py-4 rounded-lg font-semibold hover:bg-red-700 transition duration-300 text-center min-w-48 shadow-lg text-xl">
                        <i class="fas fa-hands-helping mr-2"></i>Become a<br>Volunteer
                    </a>

                    <a href="{{ route('membership.journey') }}"
                       class="bg-blue-600 text-white px-8 py-4 rounded-lg font-semibold hover:bg-blue-700 transition duration-300 text-center min-w-48 shadow-lg text-xl">
                        <i class="fas fa-user-plus mr-2"></i>Become a <br>Member
                    </a>
                    <a href="{{ route('corporate.journey') }}"
                       class="bg-green-600 text-white px-8 py-4 rounded-lg font-semibold hover:bg-green-700 transition duration-300 text-center min-w-48 shadow-lg text-xl">
                        <i class="fas fa-building mr-2"></i>Become a<br>Corporate Member
                    </a>
                </div>
            </div>
        </div>
    </section>


    <section class="py-16 watermark-bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Our Impact</h2>
                <p class="text-xl text-gray-600">Making a difference together</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="bg-blue-600 text-white p-6 rounded-lg text-center">
                    <div class="mb-4">
                        <i class="fas fa-building text-4xl opacity-75"></i>
                    </div>
                    <h3 class="text-3xl font-bold mb-2">{{ number_format($totalRedCrossUnits) }}</h3>
                    <p class="text-blue-100">Red Cross Units</p>
                    <p class="text-sm text-blue-200">Total units</p>
                </div>
                <div class="bg-green-600 text-white p-6 rounded-lg text-center">
                    <div class="mb-4">
                        <i class="fas fa-users text-4xl opacity-75"></i>
                    </div>
                    <h3 class="text-3xl font-bold mb-2">{{ number_format($totalVolunteers) }}</h3>
                    <p class="text-green-100">Volunteers</p>
                    <p class="text-sm text-green-200">Active volunteers</p>
                </div>
                <div class="bg-purple-600 text-white p-6 rounded-lg text-center">
                    <div class="mb-4">
                        <i class="fas fa-id-card text-4xl opacity-75"></i>
                    </div>
                    <h3 class="text-3xl font-bold mb-2">{{ number_format($totalMembers) }}</h3>
                    <p class="text-purple-100">Total Members</p>
                    <p class="text-sm text-purple-200">Active memberships</p>
                </div>
                <div class="bg-indigo-600 text-white p-6 rounded-lg text-center">
                    <div class="mb-4">
                        <i class="fas fa-users-cog text-4xl opacity-75"></i>
                    </div>
                    <h3 class="text-3xl font-bold mb-2">{{ number_format($totalTaskForces) }}</h3>
                    <p class="text-indigo-100">Task Forces</p>
                    <p class="text-sm text-indigo-200">Total task forces</p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-20 watermark-bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">How We Help</h2>
                <p class="text-xl text-gray-600">Our volunteers work across multiple areas to support communities</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="watermark-bg-gray-50 p-8 rounded-lg shadow-lg text-center border border-gray-100 hover:shadow-xl transition duration-300">
                    <div class="mb-6">
                        <i class="fas fa-heartbeat text-5xl text-red-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Emergency Response</h3>
                    <p class="text-gray-600">
                        Rapid response teams providing immediate assistance during emergencies and disasters.
                    </p>
                </div>
                <div class="watermark-bg-gray-50 p-8 rounded-lg shadow-lg text-center border border-gray-100 hover:shadow-xl transition duration-300">
                    <div class="mb-6">
                        <i class="fas fa-first-aid text-5xl text-red-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">First Aid Training</h3>
                    <p class="text-gray-600">
                        Professional first aid certification courses to equip volunteers with life-saving skills and emergency medical response techniques.
                    </p>
                </div>
                <div class="watermark-bg-gray-50 p-8 rounded-lg shadow-lg text-center border border-gray-100 hover:shadow-xl transition duration-300">
                    <div class="mb-6">
                        <i class="fas fa-users text-5xl text-red-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Community Support</h3>
                    <p class="text-gray-600">
                        Building stronger communities through various support programs and initiatives.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <section class="py-16 watermark-bg-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Spread the Word</h2>
            <p class="text-gray-600 mb-6">Help us reach more people — share this page with your network.</p>
            <div class="flex justify-center gap-4">
                @php
                    $shareUrl = urlencode(route('welcome'));
                    $shareText = urlencode('Red Cross Nigeria');
                @endphp
                <a href="https://wa.me/?text={{ $shareText }}%20{{ $shareUrl }}"
                   target="_blank" rel="noopener"
                   class="w-12 h-12 flex items-center justify-center rounded-full bg-green-500 hover:bg-green-600 text-white text-xl transition"
                   aria-label="Share on WhatsApp">
                    <i class="fa-brands fa-whatsapp"></i>
                </a>
                <a href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl }}"
                   target="_blank" rel="noopener"
                   class="w-12 h-12 flex items-center justify-center rounded-full bg-blue-600 hover:bg-blue-700 text-white text-xl transition"
                   aria-label="Share on Facebook">
                    <i class="fa-brands fa-facebook-f"></i>
                </a>
                <a href="https://twitter.com/intent/tweet?url={{ $shareUrl }}&text={{ $shareText }}"
                   target="_blank" rel="noopener"
                   class="w-12 h-12 flex items-center justify-center rounded-full bg-gray-900 hover:bg-gray-800 text-white text-xl transition"
                   aria-label="Share on X">
                    <i class="fa-brands fa-x-twitter"></i>
                </a>
            </div>
        </div>
    </section>

    <x-slot name="scripts">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
                crossorigin=""></script>

        <!--suppress JSUnusedAssignment -->
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                @if($branchesCount > 0)
                const branchesData  = @json($branches);
                const branchesStats = @json($branchesStats);

                const isMobile  = window.innerWidth <= 768;
                const zoomLevel = isMobile ? 5 : 6;

                const map = L.map('branchesMap', {
                    zoomControl: false,
                    scrollWheelZoom: false,
                    dragging: !isMobile,
                }).setView([9.0820, 8.6753], zoomLevel);

                // Add zoom control in bottom right to avoid popup conflict
                L.control.zoom({
                    position: 'bottomright'
                }).addTo(map);

                // Scroll/drag activation hint overlay
                const mapContainer = document.getElementById('branchesMap');
                const mapHint = document.createElement('div');
                mapHint.textContent = isMobile ? 'Tap to interact' : 'Click to activate scroll';
                Object.assign(mapHint.style, {
                    position: 'absolute', bottom: '8px', left: '50%',
                    transform: 'translateX(-50%)',
                    background: 'rgba(0,0,0,0.5)', color: '#fff',
                    fontSize: '12px', padding: '4px 12px', borderRadius: '20px',
                    pointerEvents: 'none', zIndex: '1000',
                    transition: 'opacity 0.3s', whiteSpace: 'nowrap',
                });
                mapContainer.appendChild(mapHint);

                let mapActive = false;

                function activateMap() {
                    if (mapActive) return;
                    mapActive = true;
                    isMobile ? map.dragging.enable() : map.scrollWheelZoom.enable();
                    mapHint.style.opacity = '0';
                }

                function deactivateMap() {
                    if (!mapActive) return;
                    mapActive = false;
                    isMobile ? map.dragging.disable() : map.scrollWheelZoom.disable();
                    mapHint.style.opacity = '1';
                }

                map.on('click', activateMap);

                if (isMobile) {
                    document.addEventListener('touchstart', function(e) {
                        if (!mapContainer.contains(e.target)) deactivateMap();
                    }, { passive: true });
                } else {
                    mapContainer.addEventListener('mouseleave', deactivateMap);
                }

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                }).addTo(map);

                let markersGroup      = L.layerGroup().addTo(map);
                let allMarkers        = [];
                let divisionMarkers   = {}; // branchId => [markers]
                const branchZoomLevel = 12;

                const nrcsLogoIcon = L.divIcon({
                    html: `
                <div class="nrcs-logo-marker">
                    <img src="{{ asset('images/NRCS_logo.jpg') }}" alt="NRCS Logo" />
                </div>
            `,
                    iconSize: [32, 32],
                    iconAnchor: [16, 16],
                    popupAnchor: [0, -18],
                    className: 'custom-div-icon'
                });

                function clearDivisionMarkers(branchId = null) {
                    if (branchId && divisionMarkers[branchId]) {
                        divisionMarkers[branchId].forEach(m => map.removeLayer(m));
                        delete divisionMarkers[branchId];
                        return;
                    }

                    Object.values(divisionMarkers).forEach(markers => {
                        markers.forEach(m => map.removeLayer(m));
                    });
                    divisionMarkers = {};
                }

                async function loadAndShowDivisions(branch) {
                    // If already loaded before, just re-render existing markers
                    if (branch._divisionsLoaded && Array.isArray(branch.divisions)) {
                        renderDivisionMarkers(branch);
                        return;
                    }

                    try {
                        const response = await fetch(`/api/branches/${branch.id}/divisions`);
                        if (!response.ok) {
                            console.error('Failed to load divisions for branch', branch.id, response.status, response.statusText);
                            return;
                        }
                        const data = await response.json();
                        branch.divisions        = data.divisions || [];
                        branch._divisionsLoaded = true;
                        renderDivisionMarkers(branch);
                    } catch (e) {
                        console.error('Error loading divisions for branch', branch.id, e);
                    }
                }

                /**
                 * Load Red Cross Units for a division and populate the popup content.
                 * Uses /divisions/{division}/units (web.php route) and expects JSON:
                 * { id, name, physical_address, units: [{id, name, members_count}, ...] }
                 */
                async function loadDivisionUnitsIntoPopup(divisionId, popupElement) {
                    const container = popupElement.querySelector('[data-role="units-container"]');
                    if (!container) return;

                    // Avoid re-loading if we already populated it
                    if (container.dataset.loaded === 'true') {
                        return;
                    }

                    container.innerHTML = '<div class="text-xs text-gray-500">Loading Red Cross Units...</div>';

                    try {
                        // IMPORTANT: this URL must match your route definition exactly.
                        // You defined: Route::get('/divisions/{division}/units', ...)
                        const response = await fetch(`/api/divisions/${divisionId}/units`);

                        if (!response.ok) {
                            console.error('Failed to load units for division', divisionId, response.status, response.statusText);
                            container.innerHTML = '<div class="text-xs text-red-500">Error loading units.</div>';
                            return;
                        }

                        const data  = await response.json();
                        const units = data.units || [];

                        if (!units.length) {
                            container.innerHTML = '<div class="text-xs text-gray-500">No units found in this division.</div>';
                            container.dataset.loaded = 'true';
                            return;
                        }

                        // Make container scrollable if there are many units
                        container.innerHTML = units.map(unit => `
                    <div class="division-popup-unit-row" style="display:flex;justify-content:space-between;font-size:11px;padding:2px 0;border-bottom:1px solid #e5e7eb;">
                        <div class="division-popup-unit-name" style="font-weight:500;color:#111827;">
                            ${unit.name}
                        </div>
                        <div class="division-popup-unit-members" style="color:#4b5563;white-space:nowrap;">
                            ${(unit.members_count ?? 0)} volunteers
                        </div>
                    </div>
                `).join('');
                        container.style.maxHeight = '180px';
                        container.style.overflowY = 'auto';

                        container.dataset.loaded = 'true';
                    } catch (err) {
                        console.error('Error loading units for division', divisionId, err);
                        container.innerHTML = '<div class="text-xs text-red-500">Error loading units.</div>';
                    }
                }

                function renderDivisionMarkers(branch) {
                    clearDivisionMarkers(branch.id);

                    if (!Array.isArray(branch.divisions) || branch.divisions.length === 0) {
                        return;
                    }

                    divisionMarkers[branch.id] = [];

                    branch.divisions.forEach(division => {
                        if (!division.latitude || !division.longitude) {
                            return;
                        }

                        // Custom icon for each division
                        const divisionIcon = L.divIcon({
                            html: `
                        <div class="division-marker">
                            <div class="division-marker-icon">
                                <span class="division-marker-cross">+</span>
                            </div>
                            <span class="division-marker-label">${division.name || 'Division'}</span>
                        </div>
                    `,
                            iconSize: [1, 1],           // Size is driven by content
                            iconAnchor: [0, 8],         // Adjust so the "point" feels close to the division location
                            className: 'division-div-icon'
                        });

                        const marker = L.marker(
                            [parseFloat(division.latitude), parseFloat(division.longitude)],
                            { icon: divisionIcon }
                        ).addTo(map);

                        // Division popup skeleton with units container
                        marker.bindPopup(`
                    <div class="division-popup" data-division-id="${division.id}">
                        <div class="division-popup-header">
                            <h4>${division.name || 'Division'}</h4>
                            ${division.physical_address ? `<p>${division.physical_address}</p>` : ''}
                        </div>

                        <div class="division-popup-units" data-role="units-container">
                            <div class="text-xs text-gray-500">Loading Red Cross Units...</div>
                        </div>
                    </div>
                `);

                        // When popup opens, load units (once)
                        marker.on('popupopen', (e) => {
                            const popupEl = e.popup.getElement();
                            if (!popupEl) return;
                            loadDivisionUnitsIntoPopup(division.id, popupEl);
                        });

                        divisionMarkers[branch.id].push(marker);
                    });

                    const latLngs = divisionMarkers[branch.id].map(m => m.getLatLng());
                    if (latLngs.length) {
                        const bounds = L.latLngBounds(latLngs);
                        map.fitBounds(bounds.pad(0.2));
                    }
                }

                // Base branch markers + popups
                branchesData.forEach(branch => {
                    const marker = L.marker([branch.latitude, branch.longitude], {
                        icon: nrcsLogoIcon
                    });

                    // Floating text label above marker
                    const textLabel = L.divIcon({
                        html: `<div class="branch-name-label">${branch.name}</div>`,
                        iconSize: [1, 1],
                        iconAnchor: [0, 35],
                        className: 'branch-text-label'
                    });

                    const labelMarker = L.marker([branch.latitude, branch.longitude], {
                        icon: textLabel,
                        interactive: false,
                        zIndexOffset: 1000
                    });

                    const stats = branchesStats[branch.id] || {
                        volunteers: 0,
                        red_cross_units: 0,
                        task_forces: 0,
                        activity_hours: 0,
                        members: 0
                    };

                    // Build divisions text
                    let divisionsText  = '';
                    let divisionsCount = 0;
                    let divisionsLabel = '';

                    if (branch.divisions && branch.divisions.length > 0) {
                        divisionsCount = branch.divisions.length;
                        divisionsText  = branch.divisions.map(division => division.name).join(', ');
                        divisionsLabel = `${divisionsCount} ${divisionsCount === 1 ? 'Division' : 'Divisions'}`;
                    } else {
                        divisionsText  = 'No divisions available';
                        divisionsLabel = '0 Divisions';
                    }

                    const popupContent = `
                <div class="p-2 min-w-32">
                    <div class="border-b border-gray-200 pb-1 mb-2">
                        <h3 class="font-semibold text-lg text-gray-800 mb-1">Branch details</h3>
                        <div class="font-semibold text-base text-red-600">${branch.name}</div>
                    </div>

                    <div class="space-y-1 mb-2 text-xs">
                        <div>
                            <i class="fas fa-hands-helping text-green-600 mr-1"></i>
                            <span class="text-gray-600">Volunteers: </span>
                            <span class="font-bold text-green-600">${(stats.volunteers || 0).toLocaleString()}</span>
                        </div>
                        <div>
                            <i class="fas fa-users text-purple-600 mr-1"></i>
                            <span class="text-gray-600">Members: </span>
                            <span class="font-bold text-purple-600">${(stats.members || 0).toLocaleString()}</span>
                        </div>
                        <div>
                            <i class="fas fa-wrench text-orange-600 mr-1"></i>
                            <span class="text-gray-600">Projects: </span>
                            <span class="font-bold text-orange-600">${(branch.projects ?? 0).toLocaleString()}</span>
                        </div>

                    </div>

                    <div class="border-t border-gray-200 pt-2 mt-2">
                        <div class="mb-1 flex items-center justify-between">
                            <div>
                                <i class="fas fa-sitemap text-red-600 mr-1"></i>
                                <span class="text-gray-600 font-semibold text-xs">${divisionsLabel}:</span>
                            </div>
                            <button
                                type="button"
                                class="text-xs px-2 py-1 rounded bg-red-600 text-white hover:bg-red-700 transition"
                                data-action="zoom-branch"
                                data-branch-id="${branch.id}"
                            >
                                Zoom to divisions
                            </button>
                        </div>
                        <div class="divisions-text ${branch.divisions && branch.divisions.length > 0 ? '' : 'no-divisions'}">
                            ${divisionsText}
                        </div>
                    </div>

                    ${branch.telephone || branch.email ? `
                    <div class="text-xs text-gray-500 border-t border-gray-200 pt-1">
                        ${branch.telephone ? `<div><i class="fas fa-phone mr-1"></i>${branch.telephone}</div>` : ''}
                        ${branch.email ? `<div><i class="fas fa-envelope mr-1"></i>${branch.email}</div>` : ''}
                    </div>
                    ` : ''}
                </div>
            `;

                    marker.bindPopup(popupContent, {
                        className: 'custom-popup'
                    });

                    marker.on('popupopen', function(e) {
                        const popupEl = e.popup.getElement();
                        if (!popupEl) return;

                        const zoomBtn = popupEl.querySelector('[data-action="zoom-branch"]');
                        if (zoomBtn) {
                            zoomBtn.addEventListener('click', async (ev) => {
                                ev.preventDefault();

                                const lat = parseFloat(branch.latitude);
                                const lng = parseFloat(branch.longitude);

                                map.setView([lat, lng], branchZoomLevel, {
                                    animate: true
                                });

                                // Clear other branches' division markers to avoid clutter
                                clearDivisionMarkers();

                                // Load and show divisions for this branch
                                await loadAndShowDivisions(branch);
                            });
                        }
                    });

                    markersGroup.addLayer(marker);
                    markersGroup.addLayer(labelMarker);
                    allMarkers.push(marker);
                });
                @endif
            });
        </script>
    </x-slot>
</x-layouts.app>
