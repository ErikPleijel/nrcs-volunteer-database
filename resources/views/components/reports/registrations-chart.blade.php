@props([
    'title' => 'Registrations – Trend',
    'chartId' => 'registrationsTrendChart',
    // Expected shape: ['labels' => [...], 'values' => [...]]
    'dataset' => ['labels' => [], 'values' => []],
    'seriesLabel' => 'Registrations',
    'trendOptions' => null,
    'selectedTrendKey' => null,
    'formAction' => null,
    'request' => null,
])

<div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6">
    <div class="flex justify-between items-center mb-2">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
            {{ $title }}
        </h2>

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
                                    if (Number.isInteger(value)) {
                                        return value;
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
