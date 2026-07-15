@props([
    'gender'       => ['female' => 0, 'male' => 0, 'other' => 0, 'unknown' => 0],
    'ages'         => [],
    'agesByGender' => null,
    'chartIdPrefix' => 'demographics',
])

<div class="bg-white dark:bg-gray-800 shadow rounded-lg p-4 mb-6 print:break-inside-avoid">
    <h2 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">
        Demographics
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        {{-- Gender chart --}}
        <div>
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Gender distribution
            </h3>
            <div class="relative h-56">
                <canvas id="{{ $chartIdPrefix }}_gender"></canvas>
            </div>

            <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                Male: {{ $gender['male'] ?? 0 }}
                Female: {{ $gender['female'] ?? 0 }},
            </div>
        </div>

        {{-- Age chart (pyramid when ages_by_gender present, else bar) --}}
        <div>
            <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Age distribution
            </h3>
            <div class="relative h-56">
                <canvas id="{{ $chartIdPrefix }}_age"></canvas>
            </div>

            @if ($agesByGender)
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    &lt;15: {{ ($agesByGender['under15']['men'] ?? 0) + ($agesByGender['under15']['women'] ?? 0) }},
                    15–24: {{ ($agesByGender['age15_24']['men'] ?? 0) + ($agesByGender['age15_24']['women'] ?? 0) }},
                    25–34: {{ ($agesByGender['age25_34']['men'] ?? 0) + ($agesByGender['age25_34']['women'] ?? 0) }},
                    35–44: {{ ($agesByGender['age35_44']['men'] ?? 0) + ($agesByGender['age35_44']['women'] ?? 0) }},
                    45–54: {{ ($agesByGender['age45_54']['men'] ?? 0) + ($agesByGender['age45_54']['women'] ?? 0) }},
                    55–64: {{ ($agesByGender['age55_64']['men'] ?? 0) + ($agesByGender['age55_64']['women'] ?? 0) }},
                    65+: {{ ($agesByGender['age65plus']['men'] ?? 0) + ($agesByGender['age65plus']['women'] ?? 0) }}
                </div>
            @else
                <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    0–17: {{ $ages['age_0_17'] ?? 0 }},
                    18–25: {{ $ages['age_18_25'] ?? 0 }},
                    26–40: {{ $ages['age_26_40'] ?? 0 }},
                    41–60: {{ $ages['age_41_60'] ?? 0 }},
                    60+: {{ $ages['age_60_plus'] ?? 0 }}
                </div>
            @endif
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const genderCanvas = document.getElementById('{{ $chartIdPrefix }}_gender');
            const ageCanvas    = document.getElementById('{{ $chartIdPrefix }}_age');

            // Gender Chart
            if (genderCanvas && !genderCanvas.dataset.chartInitialized) {
                genderCanvas.dataset.chartInitialized = '1';

                new Chart(genderCanvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: ['Female', 'Male'],
                        datasets: [{
                            data: [
                                {{ $gender['female'] ?? 0 }},
                                {{ $gender['male'] ?? 0 }}
                            ],
                            backgroundColor: [
                                'rgb(255, 20, 147)',
                                'rgb(0, 102, 255)'
                            ],
                            borderColor: [
                                'rgba(255, 182, 193, 1)',
                                'rgba(135, 206, 235, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { color: 'rgb(156 163 175)' }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        let label = context.label || '';
                                        if (label) label += ': ';
                                        if (context.parsed !== null) label += context.parsed + ' members';
                                        return label;
                                    }
                                }
                            }
                        }
                    }
                });
            }

            // Age Chart
            if (ageCanvas && !ageCanvas.dataset.chartInitialized) {
                ageCanvas.dataset.chartInitialized = '1';

                const agesByGender = @json($agesByGender);

                if (agesByGender && typeof agesByGender === 'object') {
                    // Population pyramid
                    const bands      = ['under15', 'age15_24', 'age25_34', 'age35_44', 'age45_54', 'age55_64', 'age65plus'];
                    const bandLabels = ['<15', '15–24', '25–34', '35–44', '45–54', '55–64', '65+'];

                    // Reverse so oldest appears at top of chart
                    const displayBands  = [...bands].reverse();
                    const displayLabels = [...bandLabels].reverse();

                    const menData   = displayBands.map(b => -(agesByGender[b]?.men   ?? 0));
                    const womenData = displayBands.map(b =>   agesByGender[b]?.women ?? 0);

                    new Chart(ageCanvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: displayLabels,
                            datasets: [
                                {
                                    label: 'Men',
                                    data: menData,
                                    backgroundColor: 'rgba(37, 99, 235, 0.8)',
                                    borderColor: 'rgba(37, 99, 235, 1)',
                                    borderWidth: 1,
                                },
                                {
                                    label: 'Women',
                                    data: womenData,
                                    backgroundColor: 'rgba(236, 72, 153, 0.8)',
                                    borderColor: 'rgba(236, 72, 153, 1)',
                                    borderWidth: 1,
                                }
                            ]
                        },
                        options: {
                            indexAxis: 'y',
                            grouped: false,
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    display: true,
                                    position: 'bottom',
                                    labels: { color: 'rgb(156 163 175)' }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            const label = context.dataset.label || '';
                                            return label + ': ' + Math.abs(context.parsed.x);
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    ticks: {
                                        color: 'rgb(156 163 175)',
                                        callback: function(value) { return Math.abs(value); }
                                    }
                                },
                                y: {
                                    ticks: { color: 'rgb(156 163 175)' }
                                }
                            }
                        }
                    });
                } else {
                    // Fallback: classic age distribution bar chart
                    new Chart(ageCanvas.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: ['0–17', '18–25', '26–40', '41–60', '60+'],
                            datasets: [{
                                data: [
                                    {{ $ages['age_0_17'] ?? 0 }},
                                    {{ $ages['age_18_25'] ?? 0 }},
                                    {{ $ages['age_26_40'] ?? 0 }},
                                    {{ $ages['age_41_60'] ?? 0 }},
                                    {{ $ages['age_60_plus'] ?? 0 }}
                                ],
                                backgroundColor: 'rgba(75, 192, 192, 0.9)',
                                borderColor: 'rgba(75, 192, 192, 1)',
                                borderWidth: 1
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.dataset.label || '';
                                            if (context.parsed.x !== null) label += context.parsed.x + ' members';
                                            return label;
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    beginAtZero: true,
                                    ticks: { color: 'rgb(156 163 175)' }
                                },
                                y: {
                                    ticks: { color: 'rgb(156 163 175)' }
                                }
                            }
                        }
                    });
                }
            }
        });
    </script>
</div>
