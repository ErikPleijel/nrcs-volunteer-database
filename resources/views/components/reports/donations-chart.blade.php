@props([
    'chartId'          => 'donationsTrendChart',
    'chartTitle'       => 'Donations Trend',
    'trendOptions'     => [],
    'selectedTrendKey' => null,
    'formAction'       => null,
    'request'          => null,

    // Data arrays
    'labels'           => [],
    'cashValues'       => [],
    'inKindValues'     => [],
])

<div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6">
    <div class="flex items-center justify-between mb-3 gap-3 flex-wrap">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ $chartTitle }}
        </h2>

        @if ($formAction && !empty($trendOptions))
            <form method="GET"
                  action="{{ $formAction }}"
                  class="flex items-center gap-2 text-sm">

                {{-- Preserve other query params (like year, filters, etc.) --}}
                @if ($request)
                    @foreach ($request->except('trend_years', 'page') as $name => $value)
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
                    @endforeach
                @endif

                <label for="{{ $chartId }}_trend" class="text-gray-700 dark:text-gray-300">
                    Trend:
                </label>

                <select
                    id="{{ $chartId }}_trend"
                    name="trend_years"
                    class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100 text-sm rounded-md"
                    onchange="this.form.submit()"
                >
                    @foreach ($trendOptions as $key => $years)
                        <option value="{{ $key }}" @selected($selectedTrendKey === $key)>
                            {{ $years }} years
                        </option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    <div class="relative h-64">
        <canvas id="{{ $chartId }}"></canvas>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const canvas = document.getElementById('{{ $chartId }}');
            if (!canvas) return;

            // If Chart.js is not loaded, fail loudly in console
            if (typeof Chart === 'undefined') {
                console.error('Chart.js is not loaded. Make sure the Chart.js script is included on this page.');
                return;
            }

            // Prevent double-init (e.g. Turbo/Livewire re-renders)
            if (canvas.dataset.chartInitialized === '1') {
                return;
            }
            canvas.dataset.chartInitialized = '1';

            const ctx = canvas.getContext('2d');

            const labels       = {!! json_encode($labels) !!};
            const cashValues   = {!! json_encode($cashValues) !!};
            const inKindValues = {!! json_encode($inKindValues) !!};

            // Destroy old instance if any (super defensive)
            if (canvas._chartInstance) {
                canvas._chartInstance.destroy();
            }

            canvas._chartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Cash Donations (₦)',
                            data: cashValues,
                            borderWidth: 2,
                            tension: 0.3,
                            fill: false,
                            yAxisID: 'yCash',
                        },
                        {
                            label: 'In-kind Donations (count)',
                            data: inKindValues,
                            borderWidth: 2,
                            tension: 0.3,
                            fill: false,
                            borderDash: [4, 4],
                            yAxisID: 'yInKind',
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            display: true,
                        },
                        tooltip: {
                            enabled: true,
                        },
                    },
                    scales: {
                        yCash: {
                            type: 'linear',
                            position: 'left',
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Cash (₦)',
                            },
                            ticks: {
                                precision: 0,
                            },
                        },
                        yInKind: {
                            type: 'linear',
                            position: 'right',
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'In-kind (count)',
                            },
                            grid: {
                                drawOnChartArea: false, // keep grid from overlapping too much
                            },
                            ticks: {
                                precision: 0,
                            },
                        },
                    },
                },
            });
        });
    </script>
</div>
