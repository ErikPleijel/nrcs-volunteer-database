@props([
    'title' => 'Membership Fee Revenue – Trend',
    'chartId' => 'reportTrendChart',
    'dataset' => ['labels' => [], 'values' => []],
    'seriesLabel' => 'Membership fee revenue (₦)',
])

<div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6">
    <h2 class="text-lg font-semibold mb-2 text-gray-900 dark:text-white">
        {{ $title }}
    </h2>

    {{-- Fixed-height wrapper to stop "infinite growth" --}}
    <div class="relative h-64">
        <canvas id="{{ $chartId }}"></canvas>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const canvas = document.getElementById('{{ $chartId }}');
            if (!canvas) return;

            // Prevent double-init if the view is re-rendered (Livewire, Turbo, etc.)
            if (canvas.dataset.chartInitialized === '1') {
                return;
            }
            canvas.dataset.chartInitialized = '1';

            const ctx = canvas.getContext('2d');
            const dataset = @json($dataset);

            // eslint-disable-next-line no-undef
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dataset.labels,
                    datasets: [{
                        label: '{{ $seriesLabel }}',
                        data: dataset.values,
                        tension: 0.3,
                        fill: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false, // use the h-64 wrapper height
                    plugins: {
                        legend: {
                            display: true,
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                maxRotation: 45,
                                minRotation: 0,
                            }
                        },
                        y: {
                            ticks: {
                                callback: function (value) {
                                    // Show whole numbers only
                                    if (Number.isInteger(value)) {
                                        return value.toLocaleString();
                                    }
                                    return '';
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</div>
