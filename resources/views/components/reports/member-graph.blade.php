@props([
    'title' => 'Trend',
    'chartId' => 'reportTrendChart',
    // Backward-compatible: supports {labels, values} OR {labels, series: {male: [], female: [], total?: []}}
    'dataset' => ['labels' => [], 'values' => null, 'series' => null],
    // Optional: when provided, renders the trend selector in the card header
    'trendOptions'     => null,
    'selectedTrendKey' => null,
    'formAction'       => null,
    'request'          => null,
])

<div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6">
    <div class="flex justify-between items-center mb-2">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ $title }}
        </h2>

        @if ($formAction && $trendOptions)
            <form action="{{ $formAction }}" method="GET" class="flex items-center space-x-2 text-sm">
                @if ($request)
                    @foreach ($request->except(['trend_months']) as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                @endif

                <label for="{{ $chartId }}_trend" class="text-gray-600 dark:text-gray-300">Trend:</label>
                <select name="trend_months" id="{{ $chartId }}_trend"
                        onchange="this.form.submit()"
                        class="block pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    @foreach ($trendOptions as $key => $months)
                        @php $years = $months / 12; @endphp
                        <option value="{{ $key }}" @selected($key == $selectedTrendKey)>
                            {{ $years < 1 ? $months . ' Months' : ($years == 1 ? '1 Year' : $years . ' Years') }}
                        </option>
                    @endforeach
                </select>
                <noscript>
                    <button type="submit" class="px-3 py-1 bg-blue-500 text-white rounded-md">Update</button>
                </noscript>
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

            if (canvas.dataset.chartInitialized === '1') return;
            canvas.dataset.chartInitialized = '1';

            const ctx = canvas.getContext('2d');
            const dataset = @json($dataset);

            const hasSeries = dataset && dataset.series && typeof dataset.series === 'object';

            let chartDatasets;
            if (hasSeries) {
                chartDatasets = [
                    {
                        label: 'Male',
                        data: dataset.series.male ?? [],
                        tension: 0.3,
                        fill: false,
                        spanGaps: false,
                        borderColor: '#2563eb',
                        backgroundColor: '#2563eb',
                    },
                    {
                        label: 'Female',
                        data: dataset.series.female ?? [],
                        tension: 0.3,
                        fill: false,
                        spanGaps: false,
                        borderColor: '#db2777',
                        backgroundColor: '#db2777',
                    }
                ];

                if (dataset.series.total) {
                    chartDatasets.push({
                        label: 'Total',
                        data: dataset.series.total,
                        tension: 0.3,
                        fill: false,
                        spanGaps: false,
                        borderColor: '#374151',
                        backgroundColor: '#374151',
                        borderWidth: 2.5,
                    });
                }
            } else {
                chartDatasets = [{
                    label: 'Active members',
                    data: dataset.values ?? [],
                    tension: 0.3,
                    fill: false,
                    spanGaps: false,
                }];
            }

            // eslint-disable-next-line no-undef
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dataset.labels ?? [],
                    datasets: chartDatasets,
                },
                options: {
                    spanGaps: false,
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: true }
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
                                    return Number.isInteger(value) ? value : '';
                                }
                            }
                        }
                    }
                }
            });
        });
    </script>
</div>
