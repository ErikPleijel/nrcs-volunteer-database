@props([
    'chartId',
    'dataset',
    'chartTitle' => null,
    'chartLabel' => 'Value',
    'trendOptions' => null,
    'selectedTrendKey' => null,
    'formAction' => null,
    'request' => null,
])

<div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6">
    <div class="flex justify-between items-center mb-3">
        @if ($chartTitle)
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
                {{ $chartTitle }}
            </h2>
        @else
            <div></div>
        @endif

        @if ($trendOptions && $formAction && $request)
            <form action="{{ $formAction }}" method="GET" class="flex items-center space-x-2 text-sm">
                @foreach ($request->except(['trend_years']) as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <label class="text-gray-600 dark:text-gray-300">Trend:</label>
                <select name="trend_years" onchange="this.form.submit()"
                        class="block pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                    @foreach ($trendOptions as $key => $years)
                        <option value="{{ $key }}" @if($key == $selectedTrendKey) selected @endif>
                            {{ $years }} {{ $years == 1 ? 'Year' : 'Years' }}
                        </option>
                    @endforeach
                </select>
            </form>
        @endif
    </div>

    <div style="height: 300px;">
        <canvas id="{{ $chartId }}"></canvas>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const canvas = document.getElementById('{{ $chartId }}');
        if (!canvas) return;

        if (canvas.dataset.chartInitialized === '1') {
            return;
        }
        canvas.dataset.chartInitialized = '1';

        const ctx = canvas.getContext('2d');

        const labels = @json($dataset['labels'] ?? []);

        // If "series" exists → multi-line chart
        const series = @json($dataset['series'] ?? null);

        let datasets = [];

        if (series && Array.isArray(series)) {
            datasets = series.map((s) => {
                return {
                    label: s.label,
                    data: s.values,
                    tension: 0.2,
                    borderWidth: 2,
                };
            });
        } else {
            // Fallback: old single-series format
            const values = @json($dataset['values'] ?? []);
            datasets = [{
                label: '{{ $chartLabel }}',
                data: values,
                tension: 0.2,
                borderWidth: 2,
            }];
        }

        if (!labels.length) {
            ctx.font = '14px sans-serif';
            ctx.fillText('No data available for the selected period.', 10, 30);
            return;
        }

        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: datasets,
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    mode: 'index',
                    intersect: false,
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0,
                        },
                    },
                },
            },
        });
    });
</script>
