@props([
    'chartId',
    'dataset',
    'chartTitle',
    'chartLabel',
    'trendOptions',
    'selectedTrendKey',
    'formAction',
    'request',
])

<div class="mt-8 p-6 bg-white dark:bg-gray-800 rounded-lg shadow">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
            {{ $chartTitle }}
        </h2>

        {{-- Trend controls --}}
        <form action="{{ $formAction }}" method="GET" class="flex items-center space-x-2 text-sm">
            @foreach ($request->except(['trend_months']) as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach

            <label for="trend_months" class="text-gray-600 dark:text-gray-300">Trend:</label>
            <select name="trend_months" id="trend_months" onchange="this.form.submit()" class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                @foreach($trendOptions as $key => $value)
                    @php
                        $years = $value / 12;
                        $label = $years < 1 ? $value . ' Months' : ($years == 1 ? '1 Year' : $years . ' Years');
                    @endphp
                    <option value="{{ $key }}" @if($key == $selectedTrendKey) selected @endif>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            <noscript>
                <button type="submit" class="px-3 py-1 bg-blue-500 text-white rounded-md">Update</button>
            </noscript>
        </form>
    </div>

    {{-- Chart component --}}
    @php
        $hasValues = !empty($dataset['values']) && is_array($dataset['values']) && count($dataset['values']) > 1;
        $hasSeries = !empty($dataset['series']) && is_array($dataset['series'])
            && (
                (!empty($dataset['series']['male']) && is_array($dataset['series']['male']) && count($dataset['series']['male']) > 1)
                || (!empty($dataset['series']['female']) && is_array($dataset['series']['female']) && count($dataset['series']['female']) > 1)
            );

        $hasLabels = !empty($dataset['labels']) && is_array($dataset['labels']) && count($dataset['labels']) > 1;
    @endphp

    @if ($hasLabels && ($hasValues || $hasSeries))
        <div class="relative h-80">
            <canvas id="{{ $chartId }}"></canvas>
        </div>
    @else
        <div class="h-80 flex items-center justify-center bg-gray-50 dark:bg-gray-700 rounded-md">
            <p class="text-gray-500 dark:text-gray-400">Not enough data to display the trend graph.</p>
        </div>
    @endif
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const canvas = document.getElementById('{{ $chartId }}');
            if (!canvas) return;

            if (canvas.dataset.chartInitialized === '1') {
                return;
            }
            canvas.dataset.chartInitialized = '1';

            const ctx = canvas.getContext('2d');
            const dataset = @json($dataset);

            const hasSeries = dataset && dataset.series && typeof dataset.series === 'object';

            const datasets = hasSeries
                ? [
                    {
                        label: 'Male',
                        data: dataset.series.male ?? [],
                        tension: 0.3,
                        fill: false,
                        borderColor: '#2563eb',
                        spanGaps: false,
                        yAxisID: 'y',
                    },
                    {
                        label: 'Female',
                        data: dataset.series.female ?? [],
                        tension: 0.3,
                        fill: false,
                        borderColor: '#db2777',
                        spanGaps: false,
                        yAxisID: 'y',
                    },
                ]
                : [{
                    label: '{{ $chartLabel }}',
                    data: dataset.values ?? [],
                    tension: 0.3,
                    fill: false,
                    borderColor: 'rgb(75, 192, 192)',
                    spanGaps: false,
                    yAxisID: 'y',
                }];

            if (hasSeries && dataset.series.total) {
                datasets.push({
                    label: 'Total',
                    data: dataset.series.total,
                    tension: 0.3,
                    fill: false,
                    borderColor: '#374151',
                    borderWidth: 2.5,
                    spanGaps: false,
                    yAxisID: 'y',
                });
            }

            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dataset.labels ?? [],
                    datasets: datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    spanGaps: false,
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
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true,
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
@endpush
