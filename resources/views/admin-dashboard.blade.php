<x-layouts.admin title="Admin Dashboard">
    <x-slot name="pageHeader">
        Dashboard
    </x-slot>

    <section class="dev-note">
        <span class="dev-note-label">DEV NOTE<br>/Erik</span>
        <p>More relevant stats to be inserted here. It will be tailored for each admin (national/branch/division level) </p>
    </section>


    {{-- New Statistics Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
        {{-- Card for Members --}}
        <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Members</h3>
                <p class="text-4xl font-bold text-indigo-600 dark:text-indigo-400">{{ $dashboardData['numberOfMembers'] }}</p>
            </div>
            <div class="text-indigo-400 dark:text-indigo-300 text-5xl">
                <i class="fa-solid fa-users"></i>
            </div>
        </div>

        {{-- Card for Volunteers --}}
        <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Volunteers</h3>
                <p class="text-4xl font-bold text-green-600 dark:text-green-400">{{ $dashboardData['numberOfVolunteers'] }}</p>
            </div>
            <div class="text-green-400 dark:text-green-300 text-5xl">
                <i class="fa-solid fa-hand-holding-heart"></i>
            </div>
        </div>

        {{-- Card for Donors --}}
        <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Donors</h3>
                <p class="text-4xl font-bold text-red-600 dark:text-red-400">{{ $dashboardData['numberOfDonors'] }}</p>
            </div>
            <div class="text-red-400 dark:text-red-300 text-5xl">
                <i class="fa-solid fa-heart"></i>
            </div>
        </div>

        {{-- Card for People Trained --}}
        <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 flex items-center justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">People Trained</h3>
                <p class="text-4xl font-bold text-yellow-600 dark:text-yellow-400">{{ $dashboardData['numberOfPeopleTrained'] }}</p>
            </div>
            <div class="text-yellow-400 dark:text-yellow-300 text-5xl">
                <i class="fa-solid fa-graduation-cap"></i>
            </div>
        </div>
    </div>

    {{-- Membership Growth Graph --}}
    <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6 mb-6">
        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-4">Membership Growth Over Time (Not real data)</h3>
        <div style="height: 350px;">
            <canvas id="membershipChart"></canvas>
        </div>
    </div>

    {{-- The rest of your existing dashboard content would go here --}}

    @push('scripts')
        {{-- Chart.js library --}}
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var ctx = document.getElementById('membershipChart').getContext('2d');
                var membershipChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                        datasets: [{
                            label: 'New Memberships',
                            data: [65, 59, 80, 81, 56, 55, 40, 70, 60, 90, 85, 75], // Simple dummy data
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            borderColor: 'rgba(54, 162, 235, 1)',
                            borderWidth: 2,
                            tension: 0.4,
                            fill: true,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: true,
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            });
        </script>
    @endpush
</x-layouts.admin>
