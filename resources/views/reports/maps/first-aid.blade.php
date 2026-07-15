<x-layouts.admin :title="$title">
    <x-slot name="styles">
        <link rel="stylesheet"
              href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
              integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
              crossorigin=""/>
    </x-slot>

    <x-slot name="pageHeader">{{ $title }}</x-slot>
    <x-slot name="subHeader">Bubble size = number of first aiders · Colour = training freshness (green = recent)</x-slot>

    <div class="p-4 md:p-6">

        {{-- Level toggle --}}
        <div class="flex gap-2 mb-4">
            <a href="{{ route('reports.maps.first-aid.branches') }}"
               class="px-4 py-2 rounded text-sm font-medium
                      {{ $level === 'branch'
                           ? 'bg-indigo-600 text-white'
                           : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                By Branch
            </a>
            <a href="{{ route('reports.maps.first-aid.divisions') }}"
               class="px-4 py-2 rounded text-sm font-medium
                      {{ $level === 'division'
                           ? 'bg-indigo-600 text-white'
                           : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                By Division
            </a>
        </div>

        {{-- Freshness-not-computed note --}}
        @if (! $hasFreshness)
            <p class="mb-3 text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded px-3 py-2">
                <i class="fas fa-circle-info mr-1"></i>
                Freshness not yet computed; run <code>firstaid:recalculate</code>.
                Bubbles shown in neutral colour.
            </p>
        @endif

        {{-- Missing-coordinate warning --}}
        @if ($missingCount > 0)
            <p class="mb-3 text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded px-3 py-2">
                <i class="fas fa-triangle-exclamation mr-1"></i>
                {{ $missingCount }} {{ $level === 'branch' ? 'branch(es)' : 'division(s)' }}
                not shown — missing coordinates.
            </p>
        @endif

        {{-- Map + Legend wrapper --}}
        <div class="relative">
            <div id="volunteerMap"
                 class="h-[36rem] w-full rounded-lg border border-gray-200"></div>
            <div id="mapLegend"
                 class="absolute bottom-4 left-4 bg-white bg-opacity-90 rounded-lg shadow-lg p-3 text-xs"
                 style="z-index: 1000;"></div>
        </div>

    </div>

    <x-slot name="scripts">
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
                crossorigin=""></script>
        <script>
            const points   = @json($points);
            const maxCount = {{ $maxCount }};
            const maxRadius = 38;
            const minRadius = 4;

            const map = L.map('volunteerMap', { scrollWheelZoom: false })
                .setView([9.0820, 8.6753], 6);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            map.on('click', () => map.scrollWheelZoom.enable());

            function radiusFor(count) {
                if (count <= 0) return minRadius;
                return Math.max(minRadius, maxRadius * Math.sqrt(count / maxCount));
            }

            function heatColor(heat, count) {
                if (count === 0) return '#ffffff';                   // no volunteers → white
                if (heat === null || heat === undefined) return '#9ca3af'; // not computed → gray
                const h = Math.max(0, Math.min(1, heat));
                if (h < 0.5) {
                    const t = h / 0.5;
                    return `rgb(220,${Math.round(38 + t * (158 - 38))},38)`;
                }
                const t = (h - 0.5) / 0.5;
                return `rgb(${Math.round(220 - t * (220 - 22))},${Math.round(158 + t * (163 - 158))},${Math.round(38 + t * (74 - 38))})`;
            }

            // Draw largest bubbles first so smaller ones appear on top
            const sorted = [...points].sort((a, b) => b.count - a.count);
            sorted.forEach(p => {
                L.circleMarker([p.lat, p.lng], {
                    radius:      radiusFor(p.count),
                    color:       '#374151',
                    weight:      1,
                    fillColor:   heatColor(p.fresh, p.count),
                    fillOpacity: 0.75,
                })
                .bindPopup(
                    `<strong>${p.name}</strong><br>` +
                    `${p.count.toLocaleString()} first aider${p.count === 1 ? '' : 's'}<br>` +
                    `Avg last trained: ${p.avg_days === null ? '—' : Math.round(p.avg_days / 30.44) + ' months ago'}`
                )
                .addTo(map);
            });

            // Build legend — color temperature only
            const legendEl = document.getElementById('mapLegend');
            const stripW   = 130;

            let html = '<p class="font-semibold text-gray-700 mb-1">Training freshness</p>';
            html += `
                <div class="mb-1">
                    <div style="height:10px;width:${stripW}px;background:linear-gradient(to right,rgb(220,38,38),rgb(220,158,38),rgb(22,163,74));border-radius:2px;"></div>
                    <div class="flex justify-between text-gray-500 mt-0.5" style="width:${stripW}px">
                        <span>Stale</span>
                        <span>Recent</span>
                    </div>
                </div>`;

            // Gray swatch — only if any point has null freshness but has first-aiders
            if (points.some(p => p.fresh === null && p.count > 0)) {
                html += `
                    <div class="flex items-center gap-2 mt-1">
                        <svg width="14" height="14" style="flex-shrink:0">
                            <circle cx="7" cy="7" r="6" fill="#9ca3af" stroke="#6b7280" stroke-width="1"/>
                        </svg>
                        <span>Not computed</span>
                    </div>`;
            }

            // White swatch — only if any point has zero first aiders
            if (points.some(p => p.count === 0)) {
                html += `
                    <div class="flex items-center gap-2 mt-1">
                        <svg width="14" height="14" style="flex-shrink:0">
                            <circle cx="7" cy="7" r="6" fill="#ffffff" stroke="#374151" stroke-width="1"/>
                        </svg>
                        <span>No first aiders</span>
                    </div>`;
            }

            legendEl.innerHTML = html;
        </script>
    </x-slot>
</x-layouts.admin>
