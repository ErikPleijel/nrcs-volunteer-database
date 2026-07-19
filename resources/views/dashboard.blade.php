<x-layouts.admin title="Admin Dashboard">

    @php
        $user = auth()->user();
        $selectedBranch = $branches->firstWhere('id', $dashboardData['branchId']);
        $selectedBranchName = $selectedBranch ? $selectedBranch->name : 'National';
    @endphp

    @if(auth()->user()->is_super_admin)
        <div class="mb-8 rounded-lg border-2 border-indigo-300 bg-indigo-50 p-6">
            <div class="flex items-start gap-3">
                <i class="fas fa-user-shield text-2xl text-indigo-500 mt-1"></i>
                <div>
                    <h2 class="text-lg font-bold text-indigo-900">Super Administrator Account</h2>
                    <p class="mt-1 text-sm text-indigo-900">
                        This account has one purpose: appointing and removing
                        <span class="font-semibold">National Database Administrators</span>.
                        It cannot edit records, make payments, or run campaigns, and it has no personal profile.
                    </p>
                    <a href="{{ route('users.roles.edit') }}"
                       class="mt-3 inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded shadow">
                        <i class="fas fa-user-check"></i> Go to Authorizations
                    </a>
                </div>
            </div>
        </div>
    @endif

    <div class="mb-8 px-4 sm:px-6 lg:px-8">
        {{-- User Welcome & Metadata --}}
        <h1 class="text-3xl font-extrabold text-gray-900 dark:text-white leading-tight">
            Welcome, {{ $user->full_name }}!
        </h1>

        <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-gray-600 dark:text-gray-400">
            <div class="flex items-center gap-1">
                <i class="fa-solid fa-shield-halved text-blue-500 text-xs"></i>
                <span class="font-medium">Role:</span>
                <span>{{ $user->role_display_name }}</span>
            </div>

            <span class="hidden sm:inline-block text-gray-300 dark:text-gray-600">|</span>

            <div class="flex items-center gap-1">
                <i class="fa-solid fa-lock text-green-500 text-xs"></i>
                <span class="font-medium">Access:</span>
                <span>{{ $user->getAccessLevel() }}</span>
            </div>

            <span class="hidden sm:inline-block text-gray-300 dark:text-gray-600">|</span>

            <div class="flex items-center gap-1">
                <i class="fa-solid fa-sitemap text-purple-500 text-xs"></i>
                <span class="font-medium">Scope:</span>
                <span>{{ $user->scope_path }}</span>
            </div>
        </div>

        <div class="max-w-xl mx-auto my-6 px-6 py-4 bg-amber-50 border border-amber-300 rounded-lg text-center">
            <p class="flex items-center justify-center gap-2 text-amber-900 font-semibold">
                <i class="fas fa-lock text-amber-600"></i>
                This database holds confidential personal data. Do not share it with anyone outside the administrative team.
            </p>
        </div>

        {{-- Tutorial Training --}}
        <section class="mt-14">
            <h2 class="dashboard-heading text-center block w-full">Training</h2>
            <div class="mt-2 flex justify-center">
                <a href="{{ route('tutorials.index') }}"
                   class="flex flex-col items-center gap-2 p-5 bg-white rounded-xl shadow-sm hover:shadow-md border border-gray-100 transition text-center min-w-[200px]">
                    <i class="fas fa-graduation-cap text-2xl text-indigo-500"></i>
                    <span class="font-semibold text-gray-800">Open Tutorials</span>
                    <span class="text-xs text-gray-500">Guided lessons — learn how to use the database.</span>
                </a>
            </div>
        </section>

        {{-- Selected Branch Name --}}
        <div class="mt-16 text-center">
            <p class="dashboard-heading text-center block w-full">
                {{ $selectedBranchName }} Statistics
            </p>
        </div>



        {{-- Branch Filter --}}
        <div class="mt-2 flex justify-center">
            <form action="{{ route('reports.dashboard') }}" method="GET" class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 p-4 ">
                <label for="branch_id" class="text-sm font-semibold text-gray-700 dark:text-gray-300">Select branch:</label>

                <select id="branch_id" name="branch_id"
                        class="block w-full sm:w-auto rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 py-2 pl-3 pr-10 text-base text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-blue-500 focus:border-blue-500 shadow-sm"
                        onchange="this.form.submit()">
                    <option value="">National</option>

                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}"
                            @selected($dashboardData['branchId'] == $branch->id)>
                            {{ $branch->name }}
                        </option>
                    @endforeach
                </select>
            </form>
        </div>
    </div>




    {{-- Statistics Cards --}}
    <section class="mt-6">
        <div class="gap-6 mb-6 px-4 sm:px-6 lg:px-8 {{ $extended ? 'grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4' : 'flex flex-col sm:flex-row sm:justify-center items-center sm:items-stretch' }}">

            {{-- Card for Members --}}
            <div class="{{ $extended ? '' : 'w-full max-w-sm' }}">
            <div class="relative bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6">

                {{-- Top-right icon --}}
                <div class="absolute top-4 right-4 text-indigo-600 dark:text-indigo-300 text-4xl opacity-60">
                    <i class="fa-solid fa-users"></i>
                </div>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Members</h3>

                {{-- Main number --}}
                <p class="text-4xl font-bold text-indigo-600 dark:text-indigo-400">
                    {{ number_format($dashboardData['numberOfMembers']) }}
                </p>

                {{-- Trend info --}}
                <div class="mt-3 space-y-1">

                    {{-- Month trend --}}
                    @php
                        $m = $dashboardData['changeMonth'] ?? null;
                        $mPositive = $m !== null && $m >= 0;
                    @endphp

                    @if(!is_null($m))
                        <p class="text-sm flex items-center gap-2 {{ $mPositive ? 'text-green-600' : 'text-red-600' }}">
                            <i class="fa-solid {{ $mPositive ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                            {{ $mPositive ? '+' : '' }}{{ $m }}% since last month
                        </p>
                    @endif

                    {{-- Year trend --}}
                    @php
                        $y = $dashboardData['changeYear'] ?? null;
                        $yPositive = $y !== null && $y >= 0;
                    @endphp

                    @if(!is_null($y))
                        <p class="text-sm flex items-center gap-2 {{ $yPositive ? 'text-green-600' : 'text-red-600' }}">
                            <i class="fa-solid {{ $yPositive ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                            {{ $yPositive ? '+' : '' }}{{ $y }}% since last year
                        </p>
                    @endif

                </div>

                @php
                    $gM = $dashboardData['genderMen']     ?? 0;
                    $gW = $dashboardData['genderWomen']   ?? 0;
                    $gU = $dashboardData['genderUnknown'] ?? 0;
                    $gTotal = $gM + $gW + $gU;
                @endphp
                @if($gTotal > 0)
                    @php
                        $cx = 50; $cy = 50; $r = 40;
                        $colors = ['#3b82f6', '#ec4899', '#d1d5db'];
                        $labels = ['Men', 'Women', 'Unknown'];
                        $values = [$gM, $gW, $gU];
                        $startAngle = -90;

                        $paths = [];
                        foreach ($values as $i => $val) {
                            if ($val <= 0) continue;
                            $pct = $val / $gTotal;
                            $endAngle = $startAngle + $pct * 360;
                            $largeArc = ($pct > 0.5) ? 1 : 0;
                            $x1 = $cx + $r * cos(deg2rad($startAngle));
                            $y1 = $cy + $r * sin(deg2rad($startAngle));
                            $x2 = $cx + $r * cos(deg2rad($endAngle));
                            $y2 = $cy + $r * sin(deg2rad($endAngle));
                            $paths[] = [
                                'd'     => "M {$cx},{$cy} L {$x1},{$y1} A {$r},{$r} 0 {$largeArc},1 {$x2},{$y2} Z",
                                'color' => $colors[$i],
                                'label' => $labels[$i],
                                'value' => $val,
                                'pct'   => round($pct * 100),
                            ];
                            $startAngle = $endAngle;
                        }
                    @endphp

                    <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">
                            Gender distribution
                        </p>
                        <div class="flex items-center gap-4">
                            <svg viewBox="0 0 100 100" class="w-20 h-20 flex-shrink-0">
                                @foreach($paths as $path)
                                    <path d="{{ $path['d'] }}" fill="{{ $path['color'] }}" />
                                @endforeach
                            </svg>
                            <div class="text-xs text-gray-600 dark:text-gray-300 space-y-1">
                                @foreach($paths as $path)
                                    <div class="flex items-center gap-1.5">
                                        <span class="inline-block w-2.5 h-2.5 rounded-sm flex-shrink-0"
                                              style="background-color: {{ $path['color'] }}"></span>
                                        <span>{{ $path['label'] }}: <span class="font-semibold">{{ number_format($path['value']) }}</span>
                                            <span class="text-gray-400">({{ $path['pct'] }}%)</span>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

            </div>
            </div>


            @if($extended)
            {{-- Card: Renewal Rate --}}
            <div class="relative bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6">

                {{-- Top-right icon --}}
                <div class="absolute top-3 right-3 text-blue-200 dark:text-blue-300 text-3xl opacity-60">
                    <i class="fa-solid fa-rotate-right"></i>
                </div>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">
                    Renewal Rate
                </h3>

                @php
                    $rate = $dashboardData['renewalRate'] ?? null;

                    if (is_null($rate)) {
                        $colorClass = 'text-gray-400 dark:text-gray-500';
                    } elseif ($rate >= 70) {
                        $colorClass = 'text-green-600 dark:text-green-400';
                    } elseif ($rate >= 50) {
                        $colorClass = 'text-yellow-500 dark:text-yellow-400';
                    } else {
                        $colorClass = 'text-red-600 dark:text-red-400';
                    }

                    $expired12 = $dashboardData['expiredLast12Months'] ?? 0;
                    $expiredTotal = $dashboardData['expiredTotal'] ?? 0;
                    $expired90 = $dashboardData['expiredLast90Days'] ?? 0;
                    $expiring30 = $dashboardData['expiringNext30Days'] ?? 0;
                @endphp

                {{-- Main percentage --}}
                @if(!is_null($rate))

                    <div class="">
                        <span class="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400 ">LAST 12 MONTHS:</span>
                    </div>

                    <p class="text-4xl font-bold {{ $colorClass }} leading-none">
                        {{ $rate }}%
                    </p>

                @else
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        No data available
                    </p>
                @endif

                {{-- Small secondary stats --}}
                <div class="mt-3 space-y-1 text-sm">

                    <div class="flex justify-between text-gray-600 dark:text-gray-300 text-xs">
                        <span>Expiring next 30 days:</span>
                        <span class="font-semibold">{{ $expiring30 }}</span>
                    </div>

                    <div class="flex justify-between text-gray-600 dark:text-gray-300 text-xs">
                        <span>Expired last 90 days:</span>
                        <span class="font-semibold">{{ $expired90 }}</span>
                    </div>

                    <div class="flex justify-between text-gray-600 dark:text-gray-300 text-xs">
                        <span>Expired last 12 months:</span>
                        <span class="font-semibold">{{ $expired12 }}</span>
                    </div>

                    <div class="flex justify-between text-gray-600 dark:text-gray-300 text-xs">
                        <span>Total expired:</span>
                        <span class="font-semibold">{{ $expiredTotal }}</span>
                    </div>

                </div>

            </div>


            {{-- Card for Membership Revenue --}}
            <div class="relative bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6">

                {{-- Top-right icon --}}
                <div class="absolute top-4 right-4 text-green-600 dark:text-green-300 text-4xl opacity-60">
                    <i class="fa-solid fa-money-bill-wave"></i>
                </div>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Membership Revenue</h3>

                @php
                    $revNow   = $dashboardData['revenueLast12Months'] ?? 0;
                    $revPrev  = $dashboardData['revenuePrevious12Months'] ?? 0;

                    $revDiff  = $revNow - $revPrev;

                    if ($revPrev > 0) {
                        $revPercent = round(($revDiff / $revPrev) * 100);
                    } else {
                        $revPercent = null;
                    }

                    $revPositive = $revPercent !== null && $revPercent >= 0;
                @endphp

                {{-- MAIN FIGURE: last 12 months --}}
                <div class="flex items-center gap-2">
                    <p class="text-4xl font-bold text-green-600 dark:text-green-400">
                        ₦{{ number_format($revNow, 0) }}
                    </p>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Last 12 months (rolling)
                </p>

                {{-- BLOCKS BELOW MAIN FIGURE --}}
                <div class="mt-3 text-xs text-gray-600 dark:text-gray-300 space-y-0.5">
                    <div class="flex justify-between">
                        <span>12–24 months ago:</span>
                        <span class="font-semibold">
                    ₦{{ number_format($revPrev, 0) }}
                </span>
                    </div>

                    @if ($revPercent !== null)
                        <div class="flex items-center justify-between mt-0.5">
                            <span>Change:</span>
                            <span class="flex items-center gap-1 {{ $revPositive ? 'text-green-600' : 'text-red-600' }}">
                        <i class="fa-solid {{ $revPositive ? 'fa-arrow-up' : 'fa-arrow-down' }} text-[11px]"></i>
                        <span>
                            {{ $revPositive ? '+' : '' }}{{ $revPercent }}%
                            ({{ $revDiff >= 0 ? '+' : '' }}₦{{ number_format($revDiff, 0) }})
                        </span>
                    </span>
                        </div>
                    @else
                        <div class="mt-0.5 text-[11px] text-gray-500 dark:text-gray-400">
                            Not enough historical data to calculate change.
                        </div>
                    @endif
                </div>

            </div>

            @endif

            {{-- Card for Volunteers --}}
            <div class="{{ $extended ? '' : 'w-full max-w-sm' }}">
            <div class="relative bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6">

                {{-- Top-right icon --}}
                <div class="absolute top-4 right-4 text-blue-600 dark:text-blue-300 text-4xl opacity-60">
                    <i class="fa-solid fa-people-group"></i>
                </div>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Volunteers</h3>

                {{-- Main number --}}
                <p class="text-4xl font-bold text-blue-600 dark:text-blue-400">
                    {{ number_format($dashboardData['volunteersCount']) }}
                </p>

                {{-- Trend info --}}
                <div class="mt-3 space-y-1">

                    {{-- Month trend --}}
                    @php
                        $vm = $dashboardData['volunteersChangeMonth'] ?? null;
                        $vmPositive = $vm !== null && $vm >= 0;
                    @endphp

                    @if(!is_null($vm))
                        <p class="text-sm flex items-center gap-2 {{ $vmPositive ? 'text-green-600' : 'text-red-600' }}">
                            <i class="fa-solid {{ $vmPositive ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                            {{ $vmPositive ? '+' : '' }}{{ $vm }}% since last month
                        </p>
                    @endif

                    {{-- Year trend --}}
                    @php
                        $vy = $dashboardData['volunteersChangeYear'] ?? null;
                        $vyPositive = $vy !== null && $vy >= 0;
                    @endphp

                    @if(!is_null($vy))
                        <p class="text-sm flex items-center gap-2 {{ $vyPositive ? 'text-green-600' : 'text-red-600' }}">
                            <i class="fa-solid {{ $vyPositive ? 'fa-arrow-up' : 'fa-arrow-down' }}"></i>
                            {{ $vyPositive ? '+' : '' }}{{ $vy }}% since last year
                        </p>
                    @endif

                </div>

                @php
                    $vgM = $dashboardData['volunteerGenderMen']     ?? 0;
                    $vgW = $dashboardData['volunteerGenderWomen']   ?? 0;
                    $vgU = $dashboardData['volunteerGenderUnknown'] ?? 0;
                    $vgTotal = $vgM + $vgW + $vgU;
                @endphp
                @if($vgTotal > 0)
                    @php
                        $cx = 50; $cy = 50; $r = 40;
                        $colors = ['#3b82f6', '#ec4899', '#d1d5db'];
                        $labels = ['Men', 'Women', 'Unknown'];
                        $values = [$vgM, $vgW, $vgU];
                        $startAngle = -90;

                        $paths = [];
                        foreach ($values as $i => $val) {
                            if ($val <= 0) continue;
                            $pct = $val / $vgTotal;
                            $endAngle = $startAngle + $pct * 360;
                            $largeArc = ($pct > 0.5) ? 1 : 0;
                            $x1 = $cx + $r * cos(deg2rad($startAngle));
                            $y1 = $cy + $r * sin(deg2rad($startAngle));
                            $x2 = $cx + $r * cos(deg2rad($endAngle));
                            $y2 = $cy + $r * sin(deg2rad($endAngle));
                            $paths[] = [
                                'd'     => "M {$cx},{$cy} L {$x1},{$y1} A {$r},{$r} 0 {$largeArc},1 {$x2},{$y2} Z",
                                'color' => $colors[$i],
                                'label' => $labels[$i],
                                'value' => $val,
                                'pct'   => round($pct * 100),
                            ];
                            $startAngle = $endAngle;
                        }
                    @endphp

                    <div class="mt-4 pt-3 border-t border-gray-200 dark:border-gray-700">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400 mb-2">
                            Gender distribution
                        </p>
                        <div class="flex items-center gap-4">
                            <svg viewBox="0 0 100 100" class="w-20 h-20 flex-shrink-0">
                                @foreach($paths as $path)
                                    <path d="{{ $path['d'] }}" fill="{{ $path['color'] }}" />
                                @endforeach
                            </svg>
                            <div class="text-xs text-gray-600 dark:text-gray-300 space-y-1">
                                @foreach($paths as $path)
                                    <div class="flex items-center gap-1.5">
                                        <span class="inline-block w-2.5 h-2.5 rounded-sm flex-shrink-0"
                                              style="background-color: {{ $path['color'] }}"></span>
                                        <span>{{ $path['label'] }}: <span class="font-semibold">{{ number_format($path['value']) }}</span>
                                            <span class="text-gray-400">({{ $path['pct'] }}%)</span>
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

            </div>
            </div>

            @if($extended)
            {{-- Card: Training & First Aid --}}
            <div class="relative bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6">

                {{-- Top-right icon --}}
                <div class="absolute top-4 right-4 text-indigo-600 dark:text-indigo-300 text-4xl opacity-60">
                    <i class="fa-solid fa-graduation-cap"></i>
                </div>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Training & First Aid</h3>

                @php

                    $trainedNow      = $dashboardData['totalTrainingsLast12Months'] ?? 0;
                    $trainedPrev     =  $dashboardData['totalTrainings12to24MonthsAgo'] ?? 0;
                    $firstAidNow     = $dashboardData['firstAidTrainingsLast12Months'] ?? 0;
                    $firstAidPrev    = $dashboardData['firstAidTrainings12to24MonthsAgo'] ?? 0;

                    $trainedDiff     = $trainedNow - $trainedPrev;
                    $trainedPercent  = $trainedPrev > 0 ? round(($trainedDiff / $trainedPrev) * 100) : null;
                    $trainedPositive = $trainedPercent !== null && $trainedPercent >= 0;

                    $firstAidDiff     = $firstAidNow - $firstAidPrev;
                    $firstAidPercent  = $firstAidPrev > 0 ? round(($firstAidDiff / $firstAidPrev) * 100) : null;
                    $firstAidPositive = $firstAidPercent !== null && $firstAidPercent >= 0;
                @endphp

                {{-- MAIN FIGURE: total trained last 12 months --}}
                <div class="flex items-center gap-2">
                    <p class="text-4xl font-bold text-indigo-600 dark:text-indigo-400">
                        {{ number_format($trainedNow) }}
                    </p>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Trained in the last 12 months (rolling)
                </p>

                {{-- Details --}}
                <div class="mt-3 text-xs text-gray-600 dark:text-gray-300 space-y-0.5">

                    <div class="flex justify-between">
                        <span>12–24 months ago:</span>
                        <span class="font-semibold">{{ number_format($trainedPrev) }}</span>
                    </div>

                    @if ($trainedPercent !== null)
                        <div class="flex items-center justify-between mt-0.5">
                            <span>Total change:</span>
                            <span class="flex items-center gap-1 {{ $trainedPositive ? 'text-green-600' : 'text-red-600' }}">
                        <i class="fa-solid {{ $trainedPositive ? 'fa-arrow-up' : 'fa-arrow-down' }} text-[11px]"></i>
                        <span>
                            {{ $trainedPositive ? '+' : '' }}{{ $trainedPercent }}%
                            ({{ $trainedDiff >= 0 ? '+' : '' }}{{ number_format($trainedDiff) }})
                        </span>
                    </span>
                        </div>
                    @endif
                </div>

                {{-- First aid --}}
                <div class="mt-3 text-xs text-gray-600 dark:text-gray-300 space-y-0.5">
                    <p class="font-semibold inline-flex items-center gap-1">
                        <i class="fa-solid fa-kit-medical text-[11px]"></i>
                        First aid training
                    </p>

                    <div class="flex justify-between">
                        <span>Last 12 months:</span>
                        <span class="font-semibold">{{ number_format($firstAidNow) }}</span>
                    </div>

                    <div class="flex justify-between">
                        <span>12–24 months ago:</span>
                        <span class="font-semibold">{{ number_format($firstAidPrev) }}</span>
                    </div>

                    @if ($firstAidPercent !== null)
                        <div class="flex items-center justify-between mt-0.5">
                            <span>Change:</span>
                            <span class="flex items-center gap-1 {{ $firstAidPositive ? 'text-green-600' : 'text-red-600' }}">
                        <i class="fa-solid {{ $firstAidPositive ? 'fa-arrow-up' : 'fa-arrow-down' }} text-[11px]"></i>
                        <span>
                            {{ $firstAidPositive ? '+' : '' }}{{ $firstAidPercent }}%
                            ({{ $firstAidDiff >= 0 ? '+' : '' }}{{ number_format($firstAidDiff) }})
                        </span>
                    </span>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Card: Donations --}}
            <div class="relative bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6">

                {{-- Top-right icon --}}
                <div class="absolute top-4 right-4 text-amber-500 dark:text-amber-300 text-4xl opacity-60">
                    <i class="fa-solid fa-hand-holding-heart"></i>
                </div>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Donations</h3>

                @php
                    // Real data from $dashboardData
                    $cashNow           = $dashboardData['cashLast12'] ?? 0;
                    $cashPrev          = $dashboardData['cashPrev12'] ?? 0;
                    $inKindCountNow    = $dashboardData['inKindCountLast12'] ?? 0;
                    $inKindCountPrev   = $dashboardData['inKindCountPrev12'] ?? 0;

                    // Cash calculations
                    $totalNow      = $cashNow;
                    $totalPrev     = $cashPrev;
                    $totalDiff     = $totalNow - $totalPrev;
                    $totalPercent  = $totalPrev > 0 ? round(($totalDiff / $totalPrev) * 100) : null;
                    $totalPositive = $totalPercent !== null && $totalPercent >= 0;

                    // In-kind calculations (count-based)
                    $inKindDiff      = $inKindCountNow - $inKindCountPrev;
                    $inKindPercent   = $inKindCountPrev > 0 ? round(($inKindDiff / $inKindCountPrev) * 100) : null;
                    $inKindPositive  = $inKindPercent !== null && $inKindPercent >= 0;
                @endphp

                {{-- MAIN FIGURE: total cash donations last 12 months --}}
                <div class="flex items-center gap-2">
                    <p class="text-3xl md:text-4xl font-bold text-amber-600 dark:text-amber-300">
                        ₦{{ number_format($totalNow, 0) }}
                    </p>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Cash donations last 12 months
                </p>

                {{-- Cash change --}}
                <div class="mt-3 text-xs text-gray-600 dark:text-gray-300 space-y-0.5">
                    <div class="flex justify-between">
                        <span>Cash 12–24 months ago:</span>
                        <span class="font-semibold">₦{{ number_format($totalPrev, 0) }}</span>
                    </div>

                    @if ($totalPercent !== null)
                        <div class="flex items-center justify-between mt-0.5">
                            <span>Cash change:</span>
                            <span class="flex items-center gap-1 {{ $totalPositive ? 'text-green-600' : 'text-red-600' }}">
                        <i class="fa-solid {{ $totalPositive ? 'fa-arrow-up' : 'fa-arrow-down' }} text-[11px]"></i>
                        <span>
                            {{ $totalPositive ? '+' : '' }}{{ $totalPercent }}%
                            ({{ $totalDiff >= 0 ? '+' : '' }}₦{{ number_format($totalDiff, 0) }})
                        </span>
                    </span>
                        </div>
                    @endif
                </div>

                {{-- Breakdown --}}
                <div class="mt-3 text-xs text-gray-600 dark:text-gray-300 space-y-0.5">
                    <p class="font-semibold flex items-center gap-1">
                        <i class="fa-solid fa-box-open text-[11px]"></i>
                        In-kind Donations
                    </p>

                    <div class="flex justify-between mt-1">
                        <span>In-kind (last 12 months):</span>
                        <span class="font-semibold">{{ $inKindCountNow }} donations</span>
                    </div>

                    <div class="flex justify-between text-[11px] text-gray-500 dark:text-gray-400">
                        <span>In-kind 12–24 months ago:</span>
                        <span>{{ $inKindCountPrev }} donations</span>
                    </div>

                    {{-- In-kind percent change --}}
                    @if ($inKindPercent !== null)
                        <div class="flex items-center justify-between mt-1">
                            <span>In-kind change:</span>

                            <span class="flex items-center gap-1 {{ $inKindPositive ? 'text-green-600' : 'text-red-600' }}">
                        <i class="fa-solid {{ $inKindPositive ? 'fa-arrow-up' : 'fa-arrow-down' }} text-[11px]"></i>
                        <span>
                            {{ $inKindPositive ? '+' : '' }}{{ $inKindPercent }}%
                            ({{ $inKindDiff >= 0 ? '+' : '' }}{{ $inKindDiff }})
                        </span>
                    </span>
                        </div>
                    @endif
                </div>
            </div>




            {{-- Card: Registrations --}}
            <div class="relative bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6">

                {{-- Top-right icon --}}
                <div class="absolute top-4 right-4 text-sky-600 dark:text-sky-300 text-4xl opacity-60">
                    <i class="fa-solid fa-user-plus"></i>
                </div>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Registrations</h3>

                @php
                    // Real dashboard data
                    $registrationsNow  = $dashboardData['registrationsLast12Months']  ?? 0;
                    $registrationsPrev = $dashboardData['registrationsPrev12Months'] ?? 0;
                    $dormantTotal      = $dashboardData['dormantProfiles']           ?? 0;

                    // Change calculations
                    $regDiff      = $registrationsNow - $registrationsPrev;
                    $regPercent   = $registrationsPrev > 0 ? round(($regDiff / $registrationsPrev) * 100) : null;
                    $regPositive  = $regPercent !== null && $regPercent >= 0;
                @endphp

                {{-- MAIN FIGURE: registrations last 12 months --}}
                <div class="flex items-center gap-2">
                    <p class="text-4xl font-bold text-sky-600 dark:text-sky-300">
                        {{ number_format($registrationsNow) }}
                    </p>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    New registrations in the last 12 months
                </p>

                <div class="mt-3 text-xs text-gray-600 dark:text-gray-300 space-y-0.5">
                    <div class="flex justify-between">
                        <span>12–24 months ago:</span>
                        <span class="font-semibold">{{ number_format($registrationsPrev) }}</span>
                    </div>

                    @if ($regPercent !== null)
                        <div class="flex items-center justify-between mt-0.5">
                            <span>Change:</span>
                            <span class="flex items-center gap-1 {{ $regPositive ? 'text-green-600' : 'text-red-600' }}">
                        <i class="fa-solid {{ $regPositive ? 'fa-arrow-up' : 'fa-arrow-down' }} text-[11px]"></i>
                        <span>
                            {{ $regPositive ? '+' : '' }}{{ $regPercent }}%
                            ({{ $regDiff >= 0 ? '+' : '' }}{{ number_format($regDiff) }})
                        </span>
                    </span>
                        </div>
                    @endif
                </div>

            </div>


            {{-- Card: Red Cross Units --}}
            <div class="relative bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6">

                {{-- Top-right icon --}}
                <div class="absolute top-4 right-4 text-rose-500 dark:text-rose-300 text-4xl opacity-60">
                    <i class="fa-solid fa-people-roof"></i>
                </div>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Red Cross Units</h3>

                @php
                    $unitsActive       = $dashboardData['activeUnitsCount']            ?? 0;
                    $avgMembers        = $dashboardData['averageMembersPerActiveUnit'] ?? 0;
                    $unitsNoLeadership = $dashboardData['unitsWithoutLeadershipCount'] ?? 0;

                    // Derive units with leadership from active units - without leadership
                    $unitsWithLeadership = max($unitsActive - $unitsNoLeadership, 0);
                @endphp

                {{-- MAIN FIGURE: active units --}}
                <div class="flex items-center gap-2">
                    <p class="text-4xl font-bold text-rose-500 dark:text-rose-300">
                        {{ number_format($unitsActive) }}
                    </p>
                </div>

                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                    Active units nationwide
                </p>

                {{-- Members --}}
                <div class="mt-3 text-xs text-gray-600 dark:text-gray-300 space-y-0.5">
                    <p class="font-semibold flex items-center gap-1">
                        <i class="fa-solid fa-users text-[11px]"></i>
                        Members
                    </p>

                    <div class="flex justify-between">
                        <span>Average members per active unit:</span>
                        <span class="font-semibold">
                    {{ number_format($avgMembers, 1) }}
                </span>
                    </div>
                </div>

                {{-- Leadership section --}}
                <div class="mt-4 text-xs text-gray-600 dark:text-gray-300 space-y-0.5">

                    <p class="font-semibold inline-flex items-center gap-1">
                        <i class="fa-solid fa-kit-medical text-[11px]"></i>
                        Leadership coverage
                    </p>
                    <div class="flex justify-between">
                        <span>Units with leadership:</span>
                        <span class="font-semibold">
                    {{ number_format($unitsWithLeadership) }}
                </span>
                    </div>

                    <div class="flex justify-between">
                        <span>Units without leadership:</span>
                        <span class="font-semibold">
                    {{ number_format($unitsNoLeadership) }}
                </span>
                    </div>
                </div>
            </div>

            @endif

        </div>

        @if(!$extended)
            @php
                $extParams = ['extended' => 1];
                if ($dashboardData['branchId']) {
                    $extParams['branch_id'] = $dashboardData['branchId'];
                }
            @endphp
            <div class="mt-6 flex justify-center">
                <a href="{{ route('reports.dashboard', $extParams) }}"
                   class="inline-flex items-center gap-2 px-5 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                    <i class="fa-solid fa-chart-bar text-indigo-500"></i>
                    Show more statistics
                </a>
            </div>
        @else
            <div class="mt-6 flex justify-end">
                <x-help-popup>
                    <div class="mb-4 text-center">
                        <i class="fas fa-circle-question text-2xl text-blue-500"></i>
                        <h3 class="mt-1 text-base font-semibold text-gray-900">Statistics Glossary</h3>
                    </div>
                    <dl class="space-y-3">
                        <div>
                            <dt class="font-semibold text-gray-800">Renewal Rate</dt>
                            <dd class="text-gray-600 mt-0.5">Percentage of members whose membership expired in the last 12 months and who renewed afterward. Good: ≥ 70% &middot; Moderate: 50–69% &middot; Poor: &lt; 50%</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-800">Membership Revenue</dt>
                            <dd class="text-gray-600 mt-0.5">Total membership fees paid in the last 12 months, compared with the previous 12-month period.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-800">Volunteers</dt>
                            <dd class="text-gray-600 mt-0.5">Persons attached to an active Red Cross unit, with lifecycle status active or dormant. Historical comparisons are based on nightly statistics snapshots. For dates before the snapshot system was introduced, figures are approximated from unit assignment dates, so volunteers who later left a unit are not reflected.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-800">Training &amp; First Aid</dt>
                            <dd class="text-gray-600 mt-0.5">Number of trainings conducted in the last 12 months (all types). First Aid training count is listed separately below.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-800">First Aid training</dt>
                            <dd class="text-gray-600 mt-0.5">Total number of First Aid–related trainings conducted during this period.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-800">Cash Donations</dt>
                            <dd class="text-gray-600 mt-0.5">Total cash donations received in the last 12 months. In-kind donations are counted separately as number of records.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-800">Registrations</dt>
                            <dd class="text-gray-600 mt-0.5">People who created a profile in the last 12 months (new member or volunteer registrations).</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-800">Red Cross Units</dt>
                            <dd class="text-gray-600 mt-0.5">Units currently marked as active in the database.</dd>
                        </div>
                        <div>
                            <dt class="font-semibold text-gray-800">Leadership Coverage</dt>
                            <dd class="text-gray-600 mt-0.5">Active units that have at least one team leader or assistant. Units without leadership indicate a gap to follow up.</dd>
                        </div>
                    </dl>
                </x-help-popup>
            </div>
        @endif
    </section>

    <section class="mt-12  overflow-hidden">

        <h2 class="dashboard-heading text-center block w-full">Lifecycle Overview ({{ $selectedBranchName }})</h2>

        <div class="p-3">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">

                <!-- Pending engagement -->
                <div class="rounded-lg border border-gray-200 bg-white p-5 flex flex-col">
                    <div class="text-center mb-2"><i class="fas fa-seedling text-3xl text-sky-500"></i></div>
                    <h3 class="text-2xl font-semibold text-gray-900 text-center">Pending engagement</h3>

                    <div class="mt-4 text-center">
                        <p class="text-5xl font-bold tracking-tight text-gray-900">
                            {{ number_format($dashboardData['lifecycleAwaitingEngagement']) }}
                        </p>
                        <p class="mt-2 text-xs text-gray-600">
                            Make first contact and guide them to the&nbsp;next&nbsp;step.
                        </p>
                    </div>

                    <div class="mt-auto pt-4 text-center">
                        <x-help-popup trigger-class="help-btn">
                            <x-slot:trigger><i class="fas fa-bolt mr-1"></i> Take action!</x-slot:trigger>

                            {{-- Header --}}
                            <div class="-mt-8 mb-4 text-center">
                                <i class="fas fa-seedling text-3xl text-sky-500"></i>
                                <h3 class="mt-1 text-base font-semibold text-gray-900">Pending Engagement</h3>
                            </div>

                            {{-- Summary counts --}}
                            <p class="text-sm text-gray-700 mb-6">
                                <span class="font-semibold">{{ number_format($dashboardData['lifecycleAwaitingEngagement']) }} persons</span>
                                have registered but need guidance before they become active.
                            </p>



                            {{-- Next Steps Container Box --}}
                            <div class="mb-6 rounded-lg border border-gray-200 bg-gray-50/50 grid grid-cols-1 md:grid-cols-2 gap-4">

                                {{-- Volunteers --}}
                                <div class="flex flex-col">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Volunteers</p>
                                    <p class="text-sm text-gray-700">
                                        <span class="font-semibold">{{ number_format($dashboardData['pendingVolunteers']) }} </span>
                                        are interested in volunteering.
                                        Place them in a <span class="font-semibold">Red Cross Unit</span>. Once placed they can train and contribute.
                                    </p>
                                </div>

                                {{-- Members --}}
                                <div class="flex flex-col border-t pt-3 md:border-t-0 md:pt-0 md:border-l md:pl-4 border-gray-200">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1">Members</p>
                                    <p class="text-sm text-gray-700">
                                        <span class="font-semibold">{{ number_format($dashboardData['pendingMembers']) }}</span>
                                        are  interested in membership.
                                        Guide them to pay their <span class="font-semibold"> membership fee</span>.
                                    </p>
                                </div>
                            </div>

                            {{-- Slogan --}}
                            <div class="mb-4 text-center">
                                <p class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-1">Your mission:</p>
                                <p class="-mt-2 text-2xl font-bold text-sky-600">Get them engaged!</p>
                            </div>

                            {{-- Accordion --}}
                            <div x-data="{ open: null }" class="space-y-1 text-sm mb-4">

                                {{-- One by one --}}
                                <div class="rounded-md border border-gray-200 overflow-hidden">
                                    <button type="button"
                                            @click="open = open === 'onebyone' ? null : 'onebyone'"
                                            class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                        <span><i class="fas fa-user mr-2 text-sky-400"></i>One by one</span>
                                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                           :class="open === 'onebyone' ? 'rotate-180' : ''"></i>
                                    </button>
                                    <div x-show="open === 'onebyone'" x-collapse class="px-4 py-3 bg-white">
                                        <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                            <li>Go to <span class="font-semibold">Persons → Show more filters</span> → set <span class="font-semibold">Lifecycle Status:</span> Pending engagement → set <span class="font-semibold">Wants to contribute as</span> Member or Volunteer → click <span class="font-semibold">Filter</span></li>
                                            <li>Call each person and find out what they need — a unit placement, payment instructions, or both.</li>
                                            <li>For <span class="font-semibold">volunteers</span>: open their profile → <span class="font-semibold">Edit → Select Red Cross Unit → Update Person</span>. They move to Active once the record is approved.</li>
                                            <li>For <span class="font-semibold">members</span>: explain how to pay the membership fee. Once payment is recorded and approved, they move to Active automatically.</li>
                                            <li>If the person is unreachable after reasonable attempts, consider archiving them to keep the list clean. See instructions below.</li>
                                        </ul>
                                    </div>
                                </div>

                                {{-- Campaigns --}}
                                <div class="rounded-md border border-gray-200 overflow-hidden">
                                    <button type="button"
                                            @click="open = open === 'campaigns' ? null : 'campaigns'"
                                            class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                        <span><i class="fas fa-envelope-open-text mr-2 text-indigo-400"></i>Campaigns</span>
                                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                           :class="open === 'campaigns' ? 'rotate-180' : ''"></i>
                                    </button>
                                    <div x-show="open === 'campaigns'" x-collapse class="px-4 py-3 bg-white">
                                        <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                            <li>Make a campaign. Send a welcome message — one clear message with a simple next step.</li>

                                            <li>
                                                Use planning tool <span class="planning-tool-txt">Welcome&nbsp;Campaign&nbsp;Planner</span> to strategize.
                                            </li>

                                            <li>
                                                Design a message:
                                                <ul class="list-circle pl-4 mt-1 space-y-0.5">
                                                    <li>For aspiring <span class="font-semibold">members</span>: Instruct how to make payment.</li>
                                                    <li>For aspiring <span class="font-semibold">volunteers</span>: Instruct how to call the branch. Give telephone number to contact person, and times to call (<i>call window</i>).</li>
                                                </ul>
                                            </li>

                                            <li>
                                                <x-wizard-path wizard-name="Welcome newly registered persons" />
                                            </li>

                                            <li>For members: Once payment is recorded and approved, the member returns to Active automatically.</li>
                                            <li>For volunteers: When they call, find the person and assign to a Red Cross Unit: <span class="font-semibold">Edit → Select Red Cross Unit → Update Person</span></li>
                                        </ul>
                                    </div>
                                </div>

                            </div>{{-- end accordion --}}

                            {{-- Archiving — always visible --}}
                            <br>
                            <div class="rounded-md bg-amber-50 border border-amber-200 p-3 mx-8">
                                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 mb-1">Not reachable? Archive them</p>
                                <ul class="space-y-1 text-sm text-amber-900 list-disc pl-5">
                                    <li><span class="font-semibold">Persons</span> → Edit → Scroll down → Tick <span class="font-semibold">Archive this user</span> → Update Person</li>
                                    <li>Or use the <span class="font-semibold">Archive tool</span> for bulk archiving</li>
                                </ul>
                                <p class="mt-1 text-xs text-gray-500">
                                    Archived users: {{ number_format($dashboardData['lifecycleArchived']) }}
                                </p>
                            </div>

                        </x-help-popup>
                    </div>
                </div>

                <!-- Active -->

                <div class="rounded-lg border border-gray-200 bg-white p-5 flex flex-col">
                    <div class="text-center mb-2"><i class="fas fa-heart-pulse text-3xl text-green-500"></i></div>
                    <h3 class="text-2xl font-semibold text-gray-900 text-center">Active</h3>

                    <div class="mt-4 text-center">
                        <p class="text-5xl font-bold tracking-tight text-gray-900">
                            {{ number_format($dashboardData['lifecycleActive']) }}
                        </p>
                        <p class="mt-2 text-xs text-gray-600">
                            Maintain momentum.
                        </p>
                    </div>

                    <div class="mt-auto pt-4 text-center">
                        <x-help-popup trigger-class="help-btn">
                            <x-slot:trigger><i class="fas fa-bolt mr-1"></i> Take action!</x-slot:trigger>

                            {{-- Header --}}
                            <div class="-mt-8 mb-4 text-center">
                                <i class="fas fa-heart-pulse text-3xl text-green-500"></i>
                                <h3 class="mt-1 text-base font-semibold text-gray-900">Active</h3>
                            </div>

                            {{-- Intro --}}
                            @php $dormantMonths = \App\Models\Setting::getInt('membership.dormant_after_months', 12); @endphp

                            <p class="text-sm text-gray-700 mb-5">
                                <span class="font-semibold">{{ number_format($dashboardData['lifecycleActive']) }} persons</span>
                                are currently active. For volunteers, active means they are assigned to a Red Cross Unit
                                and have recent activity records — after <span class="font-semibold">{{ $dormantMonths }} months</span>
                                of inactivity they move to Dormant. For members, active means their membership fee is
                                valid — when it expires and is not renewed, they move to Dormant.
                            </p>

                            <div class="mb-5 text-center">
                                <p class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-1">Your mission:</p>
                                <p class="-mt-2 text-2xl font-bold text-green-600">Keep them active!</p>
                            </div>

                            {{-- Accordion sections --}}
                            <div x-data="{ open: null }" class="space-y-1 text-sm">

                                {{-- Newsletters --}}
                                <div class="rounded-md border border-gray-200 overflow-hidden">
                                    <button type="button"
                                            @click="open = open === 'newsletters' ? null : 'newsletters'"
                                            class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                        <span><i class="fas fa-newspaper mr-2 text-indigo-400"></i>Newsletters / Mobilisation</span>
                                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                           :class="open === 'newsletters' ? 'rotate-180' : ''"></i>
                                    </button>
                                    <div x-show="open === 'newsletters'" x-collapse class="px-4 py-3 bg-white">
                                        <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                            <li>Send regular updates — news, achievements, and upcoming events — to keep members and volunteers informed and connected.</li>
                                            <li>Use the <span class="planning-tool-txt">All Campaigns</span> planning tool to review previously sent newsletters.</li>
                                            <li><x-wizard-path wizard-name="Send a newsletter" /></li>
                                            <li>The wizard lets you target volunteers, members, or both.</li>
                                        </ul>
                                    </div>
                                </div>

                                {{-- Membership renewal --}}
                                <div class="rounded-md border border-gray-200 overflow-hidden">
                                    <button type="button"
                                            @click="open = open === 'renewal' ? null : 'renewal'"
                                            class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                        <span><i class="fas fa-rotate-right mr-2 text-indigo-400"></i>Membership renewal</span>
                                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                           :class="open === 'renewal' ? 'rotate-180' : ''"></i>
                                    </button>
                                    <div x-show="open === 'renewal'" x-collapse class="px-4 py-3 bg-white">
                                        <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                            <li><i class="fas fa-bolt mt-0.5 text-indigo-500"></i><span class="text-indigo-700 font-semibold"> Be proactive!</span> Send renewal reminders before fees expire.</li>
                                            <li>Check <span class="planning-tool-txt">Expiring Membership</span> in Planning Tools for members whose fee is about to lapse.</li>
                                            <li>One well-timed reminder before expiry is enough — keep the message clear and the next step simple.</li>
                                            <li><x-wizard-path wizard-name="Remind about expiring membership" /></li>
                                            <li>Once payment is recorded and approved, the member returns to Active automatically.</li>
                                        </ul>
                                    </div>
                                </div>

                                {{-- Certificates --}}
                                <div class="rounded-md border border-gray-200 overflow-hidden">
                                    <button type="button"
                                            @click="open = open === 'certificates' ? null : 'certificates'"
                                            class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                        <span><i class="fas fa-certificate mr-2 text-green-500"></i>Certificates</span>
                                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                           :class="open === 'certificates' ? 'rotate-180' : ''"></i>
                                    </button>
                                    <div x-show="open === 'certificates'" x-collapse class="px-4 py-3 bg-white">
                                        <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                            <li>Print certificates for completed trainings as recognition. Certificates are a simple but effective way to recognise effort and maintain engagement.</li>
                                            <li>Check <span class="planning-tool-txt">Training Statistics</span> in Planning Tools for an overview of who has and has not received a training certificate.</li>
                                            <li>
                                                To see which certificates have already been printed:
                                                <span class="font-semibold">Persons</span> → apply a filter → open the <u>Certificates tab</u>.
                                            </li>
                                            <li>To print individual certificates: <span class="font-semibold">Persons</span> → Search/filter → View → scroll to the relevant record section → click <span class="font-semibold">Print certificate</span>.</li>
                                            <li>For bulk printing: use <span class="font-semibold">Certificates</span> in the main menu.</li>
                                            <li>Note that certain certificates can only be printed at NRCS Headquarters.</li>
                                        </ul>
                                    </div>
                                </div>

                                {{-- Training --}}
                                <div class="rounded-md border border-gray-200 overflow-hidden">
                                    <button type="button"
                                            @click="open = open === 'training' ? null : 'training'"
                                            class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                        <span><i class="fas fa-graduation-cap mr-2 text-purple-400"></i>Training</span>
                                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                           :class="open === 'training' ? 'rotate-180' : ''"></i>
                                    </button>
                                    <div x-show="open === 'training'" x-collapse class="px-4 py-3 bg-white">
                                        <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                            <li>Invite people to First Aid trainings, refreshers, and skills development sessions.</li>
                                            <li>Check <span class="planning-tool-txt">Training Statistics</span> in Planning Tools for an overview of who has and has not completed a given training type.</li>
                                            <li>Depending on your strategy, you can run different types of campaigns — invitations, expiry reminders, or refresher nudges.</li>
                                            <li><x-wizard-path wizard-name="Invite to upcoming training" /></li>
                                            <li><x-wizard-path wizard-name="Remind about expiring training certification" /></li>
                                            <li><x-wizard-path wizard-name="Refresh stale first-aid training" /></li>
                                            <li><i class="fas fa-bolt mt-0.5 text-indigo-500"></i><span class="text-indigo-700 font-semibold">&nbsp;Log training records promptly</span> — this keeps activity timestamps current and prevents volunteers from slipping into Dormant unnecessarily.</li>
                                        </ul>
                                    </div>
                                </div>

                                {{-- ID-cards --}}
                                <div class="rounded-md border border-gray-200 overflow-hidden">
                                    <button type="button"
                                            @click="open = open === 'idcards' ? null : 'idcards'"
                                            class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                        <span><i class="fas fa-id-card mr-2 text-rose-400"></i>ID Cards</span>
                                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                           :class="open === 'idcards' ? 'rotate-180' : ''"></i>
                                    </button>
                                    <div x-show="open === 'idcards'" x-collapse class="px-4 py-3 bg-white">
                                        <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                            <li>Print ID cards for volunteers. A valid ID card signals belonging and supports field operations. The QR code on the back links to a summary of the volunteer's trainings and activity.</li>
                                            <li>Check <span class="planning-tool-txt">ID Card Expiry Report</span> in Planning Tools for an overview of printed cards and upcoming renewals.</li>
                                            <li>
                                                Before bulk printing, make sure each volunteer's data is complete. Volunteers can check this themselves via <span class="font-semibold">My Team → ID Card Completeness Report</span>, which shows whether:
                                                <ul class="list-circle pl-4 mt-1 space-y-0.5">
                                                    <li>A profile photo is uploaded and not too old.</li>
                                                    <li>A signature is uploaded.</li>
                                                    <li>A national ID number is entered.</li>
                                                    <li>A membership fee has been paid.</li>
                                                </ul>
                                            </li>
                                            <li>Once all data is in order, HQ can proceed with bulk printing.</li>
                                        </ul>
                                    </div>
                                </div>

                                {{-- Fundraiser / Appeals --}}
                                <div class="rounded-md border border-gray-200 overflow-hidden">
                                    <button type="button"
                                            @click="open = open === 'fundraising' ? null : 'fundraising'"
                                            class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                        <span><i class="fas fa-bullhorn mr-2 text-red-400"></i>Fundraiser / Appeals</span>
                                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                           :class="open === 'fundraising' ? 'rotate-180' : ''"></i>
                                    </button>
                                    <div x-show="open === 'fundraising'" x-collapse class="px-4 py-3 bg-white">
                                        <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                            <li>Send an urgent appeal — for an emergency response, a specific need, or a general fundraising push — to a wide audience of members and volunteers.</li>
                                            <li>Use the <span class="planning-tool-txt">All Campaigns</span> planning tool to review previously sent appeals.</li>
                                            <li><x-wizard-path wizard-name="Fundraising appeal" /></li>
                                            <li>The wizard lets you target Volunteers + Members, Members only, high-end members, or previous donors.</li>
                                        </ul>
                                    </div>
                                </div>

                                {{-- Donations --}}
                                <div class="rounded-md border border-gray-200 overflow-hidden">
                                    <button type="button"
                                            @click="open = open === 'donations' ? null : 'donations'"
                                            class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                        <span><i class="fas fa-hand-holding-heart mr-2 text-amber-400"></i>Donation thank-you message</span>
                                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                           :class="open === 'donations' ? 'rotate-180' : ''"></i>
                                    </button>
                                    <div x-show="open === 'donations'" x-collapse class="px-4 py-3 bg-white">
                                        <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                            <li>Send thank-you messages to recent donors — acknowledged donors are more likely to give again.</li>
                                            <li>Check <span class="planning-tool-txt">Donation Appreciation</span> in Planning Tools for an overview of who has donated and who has already received an appreciation message.</li>

                                        </ul>
                                    </div>
                                </div>

                            </div>{{-- end accordion --}}

                        </x-help-popup>
                    </div>
                </div>

                <!-- Dormant -->
                <div class="rounded-lg border border-gray-200 bg-white p-5 flex flex-col">
                    <div class="text-center mb-2"><i class="fas fa-moon text-3xl text-amber-500"></i></div>
                    <h3 class="text-2xl font-semibold text-gray-900 text-center">Dormant</h3>

                    <div class="mt-4 text-center">
                        <p class="text-5xl font-bold tracking-tight text-gray-900">
                            {{ number_format($dashboardData['lifecycleDormant']) }}
                        </p>
                        <p class="mt-2 text-xs text-gray-600">
                            Previously active people with no recent activity.
                        </p>

                    </div>

                    <div class="mt-auto pt-4 text-center">
                        <x-help-popup trigger-class="help-btn">
                            <x-slot:trigger><i class="fas fa-bolt mr-1"></i> Take action!</x-slot:trigger>

                            {{-- Header --}}
                            <div class="-mt-8 mb-4 text-center">
                                <i class="fas fa-moon text-3xl text-amber-500"></i>
                                <h3 class="mt-1 text-base font-semibold text-gray-900">Dormant</h3>
                            </div>

                            {{-- Intro --}}
                            @php $dormantMonths = \App\Models\Setting::getInt('membership.dormant_after_months', 12); @endphp
                            <p class="text-sm text-gray-700 mb-5">
                                <span class="font-semibold">{{ number_format($dashboardData['lifecycleDormant']) }} persons</span>
                                were previously active but have not shown activity for a period of time.
                                For volunteers this means no records have been entered for the last
                                <span class="font-semibold">{{ $dormantMonths }} months</span>.
                                For members it means their membership has expired and not been renewed.
                            </p>

                            {{-- Slogan --}}
                            <div class="mb-4 text-center">
                                <p class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-1">Your mission:</p>
                                <p class="-mt-2 text-2xl font-bold text-amber-500">Bring them back!</p>
                            </div>

                            {{-- Accordion --}}
                            <div x-data="{ open: null }" class="space-y-1 text-sm mb-4">



                                {{-- Membership renewal --}}
                                <div class="rounded-md border border-gray-200 overflow-hidden">
                                    <button type="button"
                                            @click="open = open === 'renewal' ? null : 'renewal'"
                                            class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                        <span><i class="fas fa-rotate-right mr-2 text-indigo-400"></i>Membership renewal</span>
                                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                           :class="open === 'renewal' ? 'rotate-180' : ''"></i>
                                    </button>
                                    <div x-show="open === 'renewal'" x-collapse class="px-4 py-3 bg-white">
                                        <ul class="space-y-1 text-gray-700 list-disc pl-4">

                                            <li><i class="fas fa-bolt mt-0.5 text-indigo-500"></i><span class="text-indigo-700 font-semibold"> Be proactive!</span> Send renewal reminders before fees expire — see the <span class="font-bold">Active</span> section for instructions.</li>
                                            <li>Run a renewal campaign with one clear message about how to make a payment.</li>
                                            <li>Use the <span class="planning-tool-txt">Expiring&nbsp;Membership</span> planning tool to identify who to contact.</li>
                                            <li><x-wizard-path wizard-name="Expiring membership" /></li>
                                            <li>Once payment is recorded and approved, the member returns to Active automatically.</li>
                                        </ul>
                                    </div>
                                </div>

                                {{-- Re-engage volunteers --}}
                                <div class="rounded-md border border-gray-200 overflow-hidden">
                                    <button type="button"
                                            @click="open = open === 'reengage' ? null : 'reengage'"
                                            class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                        <span><i class="fas fa-user-clock mr-2 text-amber-400"></i>Re-engage volunteers</span>
                                        <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                           :class="open === 'reengage' ? 'rotate-180' : ''"></i>
                                    </button>
                                    <div x-show="open === 'reengage'" x-collapse class="px-4 py-3 bg-white">
                                        <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                            <li>Use the <span class="planning-tool-txt">Re-engage&nbsp;Dormant</span> planning tool to identify who to contact and where they are.</li>
                                            <li>Design a reactivation message — for example, an invitation to an upcoming activity or training.</li>
                                            <li><x-wizard-path wizard-name="Re-engage dormant volunteers" /></li>
                                            <li>Once a new record (volunteering, training, or payment) is entered and approved, they return to Active automatically.</li>
                                        </ul>
                                    </div>
                                </div>

                            </div>{{-- end accordion --}}

                            {{-- Archiving — always visible --}}
                            <br>
                            <div class="rounded-md bg-amber-50 border border-amber-200 p-3 mx-8">
                                <p class="text-xs font-semibold uppercase tracking-wide text-amber-700 mb-1">Not reachable? Archive them</p>
                                <ul class="space-y-1 text-sm text-amber-900 list-disc pl-5">
                                    <li><span class="font-semibold">Persons</span> → Edit → Scroll down → Tick <span class="font-semibold">Archive this user</span> → Update Person</li>
                                    <li>Or use the <span class="font-semibold">Archive tool</span> for bulk archiving</li>

                                </ul>
                                <p class="mt-1 text-xs text-gray-500">
                                    Archived users: {{ number_format($dashboardData['lifecycleArchived']) }}
                                </p>
                            </div>

                        </x-help-popup>
                    </div>
                </div>



            </div>
        </div>
    </section>



    {{-- Planning Tools --}}
    <section class="mt-16 max-w-3xl mx-auto">
        <h2 class="dashboard-heading text-center block w-full">Planning Tools</h2>
        <div class="mt-4 flex flex-wrap gap-3 justify-center">
            <a href="{{ route('reports.campaign-planning.welcome') }}" class="btn-primary">
                <i class="fas fa-envelope-open-text mr-2"></i> Welcome Campaign Planner
            </a>
            <a href="{{ route('reports.campaign-planning.expiring-membership') }}" class="btn-primary">
                <i class="fas fa-id-card mr-2"></i> Expiring Membership
            </a>
            <a href="{{ route('reports.campaign-planning.dormant') }}" class="btn-primary">
                <i class="fas fa-user-clock mr-2"></i> Re-engage Dormant
            </a>
            <a href="{{ route('reports.trainings.stats') }}" class="btn-primary">
                <i class="fas fa-graduation-cap mr-2"></i> Training Statistics
            </a>
            <a href="{{ route('reports.id-card-expiry.national') }}" class="btn-primary">
                <i class="fas fa-id-card mr-2"></i> ID Card Expiry Report
            </a>
            <a href="{{ route('reports.red-cross-units.index') }}" class="btn-primary">
                <i class="fas fa-people-group mr-2"></i> Red Cross Units
            </a>
            <a href="{{ route('reports.campaign-planning.donation-appreciation') }}" class="btn-primary">
                <i class="fas fa-hand-holding-heart mr-2"></i> Donation Appreciation
            </a>
            <a href="{{ route('reports.campaign-planning.campaigns') }}" class="btn-primary">
                <i class="fas fa-list mr-2"></i> All Campaigns
            </a>
        </div>
    </section>

    {{-- Trends & Statistics --}}
    <section class="mt-16 max-w-3xl mx-auto">
        <h2 class="dashboard-heading text-center block w-full">Trends &amp; Statistics</h2>
        <div class="mt-4 flex flex-wrap gap-3 justify-center">
            <a href="{{ route('reports.members.national') }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded shadow">
                Membership Reports
            </a>
            <a href="{{ route('reports.volunteers.national') }}" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded shadow">
                Volunteer Reports
            </a>
            <a href="{{ route('reports.financial.national') }}" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded shadow">
                Financial Trends
            </a>
            <a href="{{ route('reports.financial.index') }}" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded shadow">
                Financial Breakdown
            </a>
            <a href="{{ route('reports.donations.national') }}" class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded shadow">
                Donation Reports
            </a>
            <a href="{{ route('reports.trainings.national') }}" class="px-4 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded shadow">
                Training Reports
            </a>
            <a href="{{ route('reports.registrations.national') }}" class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white rounded shadow">
                Registrations Reports
            </a>
            <a href="{{ route('reports.lifecycle.national') }}" class="px-4 py-2 bg-teal-600 hover:bg-teal-700 text-white rounded shadow">
                Lifecycle Report
            </a>
        </div>
    </section>

    {{-- Maps --}}
    <section class="mt-16">
        <h2 class="dashboard-heading text-center block w-full">Heat Maps</h2>
        <div class="mt-4 flex flex-wrap gap-3 justify-center">
            <a href="{{ route('reports.maps.volunteers.branches') }}" class="btn-primary">
                <i class="fas fa-map-location-dot mr-2"></i> Volunteer Map
            </a>
            <a href="{{ route('reports.maps.first-aid.branches') }}" class="btn-primary">
                <i class="fas fa-map-location-dot mr-2"></i> First Aid Coverage
            </a>
        </div>
    </section>

    {{-- Database Administration --}}
    <section class="mt-16 mb-16">
        <h2 class="dashboard-heading text-center block w-full">Database Administration</h2>
        <div class="mt-4 flex flex-wrap gap-3 justify-center">
            <a href="{{ route('reports.database-team.index') }}" class="btn-primary">
                <i class="fas fa-users-gear mr-2"></i> Database Team
            </a>
            <a href="{{ route('reports.admin-activities.index') }}" class="btn-primary">
                <i class="fas fa-chart-line mr-2"></i> Admin Activities
            </a>
            {{-- Migration report hidden; since self-moving between branches/divisions are not recorded in Log any more, the question is if this report is useful; admin could look in Log to see who was moved by admin. --}}
            <a href="{{ route('reports.migration') }}" class="hidden btn-primary">
                <i class="fas fa-route mr-2"></i> Migration Report
            </a>
            @if(in_array(auth()->user()->getAccessLevel(), ['national', 'branch']))
                <a href="{{ route('reports.tutorial-completion') }}" class="btn-primary">
                    <i class="fas fa-graduation-cap mr-2"></i> Tutorial Completion
                </a>
            @endif
        </div>

        {{-- 7-day activity cards --}}
        <div class="mt-8 flex flex-wrap justify-center gap-4">
            <div class="bg-white rounded-lg shadow p-4 text-center w-44">
                <p class="text-3xl font-bold text-indigo-600">{{ number_format($dashboardData['messagesSentLast7']) }}</p>
                <p class="mt-1 text-sm text-gray-600">Campaign messages sent</p>
                <p class="text-xs text-gray-400 mt-0.5">last 7 days</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4 text-center w-44">
                <p class="text-3xl font-bold text-rose-600">{{ number_format($dashboardData['idCardsPrintedLast7']) }}</p>
                <p class="mt-1 text-sm text-gray-600">ID cards printed</p>
                <p class="text-xs text-gray-400 mt-0.5">last 7 days</p>
            </div>
            <div class="bg-white rounded-lg shadow p-4 text-center w-44">
                <p class="text-3xl font-bold text-purple-600">{{ number_format($dashboardData['certificatesPrintedLast7']) }}</p>
                <p class="mt-1 text-sm text-gray-600">Certificates printed</p>
                <p class="text-xs text-gray-400 mt-0.5">last 7 days</p>
            </div>
        </div>

        {{-- Pending approvals --}}
        @php
            $totalPending = $dashboardData['pendingPayments']
                          + $dashboardData['pendingDonations']
                          + $dashboardData['pendingTrainings']
                          + $dashboardData['pendingActivities'];
        @endphp
        <div class="mt-8">
            <h3 class="text-center text-sm font-semibold uppercase tracking-wide
                       text-gray-500 mb-3">
                Pending Approvals — <span class="font-bold text-base">{{ $dashboardData['branchId'] ? $selectedBranchName : 'Nationwide' }}</span>

            </h3>
            <div class="flex flex-wrap justify-center gap-4">

                @php
                    $pendingModules = [
                        [
                            'label'          => 'Payments',
                            'count'          => $dashboardData['pendingPayments'],
                            'self_submitted' => $dashboardData['selfSubmittedPayments'],
                            'icon'           => 'fa-hand-holding-dollar',
                            'color'          => 'indigo',
                            'route'          => 'membership-payments.approvals',
                            'permission'     => 'approve_payments',
                        ],
                        [
                            'label'          => 'Donations',
                            'count'          => $dashboardData['pendingDonations'],
                            'self_submitted' => $dashboardData['selfSubmittedDonations'],
                            'icon'           => 'fa-heart',
                            'color'          => 'amber',
                            'route'          => 'donations.approvals',
                            'permission'     => 'approve_donations',
                        ],
                        [
                            'label'          => 'Trainings',
                            'count'          => $dashboardData['pendingTrainings'],
                            'self_submitted' => $dashboardData['selfSubmittedTrainings'],
                            'icon'           => 'fa-graduation-cap',
                            'color'          => 'purple',
                            'route'          => 'trainings.approvals',
                            'permission'     => 'approve_training',
                        ],
                        [
                            'label'          => 'Volunteering',
                            'count'          => $dashboardData['pendingActivities'],
                            'self_submitted' => $dashboardData['selfSubmittedActivities'],
                            'icon'           => 'fa-hands-helping',
                            'color'          => 'blue',
                            'route'          => 'activities.approvals',
                            'permission'     => 'approve_volunteering',
                        ],
                        [
                            'label'          => 'Campaigns',
                            'count'          => $dashboardData['pendingCampaigns'],
                            'self_submitted' => $dashboardData['selfSubmittedCampaigns'],
                            'icon'           => 'fa-envelope-open-text',
                            'color'          => 'emerald',
                            'route'          => 'campaigns.admin.proposed',
                            'permission'     => 'campaign_request_approve',
                        ],
                    ];
                @endphp

                @foreach($pendingModules as $mod)
                    @php
                        $isZero = $mod['count'] === 0;
                        $colorMap = [
                            'indigo'  => ['ring' => 'ring-indigo-400', 'text' => 'text-indigo-600', 'badge_bg' => 'bg-indigo-100', 'badge_text' => 'text-indigo-700'],
                            'amber'   => ['ring' => 'ring-amber-400',  'text' => 'text-amber-600',  'badge_bg' => 'bg-amber-100',  'badge_text' => 'text-amber-700'],
                            'purple'  => ['ring' => 'ring-purple-400', 'text' => 'text-purple-600', 'badge_bg' => 'bg-purple-100', 'badge_text' => 'text-purple-700'],
                            'blue'    => ['ring' => 'ring-blue-400',   'text' => 'text-blue-600',   'badge_bg' => 'bg-blue-100',   'badge_text' => 'text-blue-700'],
                            'emerald' => ['ring' => 'ring-emerald-400','text' => 'text-emerald-600','badge_bg' => 'bg-emerald-100','badge_text' => 'text-emerald-700'],
                        ];
                        $c = $colorMap[$mod['color']];
                    @endphp
                    <div class="bg-white rounded-lg shadow p-4 text-center w-44
                                {{ !$isZero ? 'ring-2 ' . $c['ring'] : '' }}">
                        <i class="fas {{ $mod['icon'] }} text-2xl
                                   {{ $isZero ? 'text-gray-300' : $c['text'] }} mb-1"></i>
                        <p class="text-3xl font-bold
                                   {{ $isZero ? 'text-gray-400' : 'text-red-600' }}">
                            {{ $mod['count'] }}
                        </p>
                        <p class="mt-1 text-sm text-gray-600">{{ $mod['label'] }}</p>
                        <p class="text-xs text-gray-400">pending approval</p>
                        @can($mod['permission'])
                            @if($mod['self_submitted'] > 0)
                                <p class="mt-1 text-[11px] text-amber-600 leading-snug">
                                    <i class="fas fa-circle-info"></i>
                                    {{ $mod['self_submitted'] }} of these {{ $mod['self_submitted'] === 1 ? 'was' : 'were' }} entered by you —
                                    another admin must approve.
                                </p>
                            @endif
                        @endcan

                    </div>
                @endforeach
            </div>

            {{-- Branch breakdown report link — national view only --}}
            @if(!$dashboardData['branchId'] && $totalPending > 0)
                <div class="mt-4 flex justify-center">
                    <a href="{{ route('reports.pending-approvals') }}"
                       class="inline-flex items-center gap-2 px-4 py-2 bg-white border
                              border-gray-300 rounded-lg shadow-sm text-sm font-medium
                              text-gray-700 hover:bg-gray-50 transition">
                        <i class="fas fa-table text-indigo-500"></i>
                        View breakdown by branch
                    </a>
                </div>
            @endif
        </div>

        {{-- Housekeeping --}}
        <div class="mt-8">
            <h3 class="text-center text-sm font-semibold uppercase tracking-wide text-gray-500 mb-3">
                Housekeeping
            </h3>
            <div class="flex flex-wrap justify-center gap-4">
                <div class="bg-white rounded-lg shadow p-4 w-64 text-center">
                    @if($dashboardData['hangingRegistrationCount'] === null)
                        <div class="block">
                            <p class="text-3xl font-bold text-gray-400">—</p>
                            <p class="mt-1 text-sm text-gray-600">Hanging Registrations</p>
                            <p class="mt-1 text-xs text-gray-400">Not configured — set NRCS_DB_MIGRATION_DATE in .env</p>
                        </div>
                    @else
                        <a href="{{ route('users.index', array_filter([
                            'registration_filter' => 'admin',
                            'archived_filter' => 'pending_engagement',
                            'branch_id' => $dashboardData['branchId'],
                        ])) }}" class="block hover:opacity-80 transition">
                            <p class="text-3xl font-bold text-orange-600">{{ number_format($dashboardData['hangingRegistrationCount']) }}</p>
                            <p class="mt-1 text-sm text-gray-600">Hanging Registrations</p>
                        </a>
                    @endif
                    <div class="mt-2">
                        <x-help-popup trigger-class="help-btn">
                            <x-slot:trigger><i class="fas fa-question-circle mr-1"></i> What is this?</x-slot:trigger>

                            <div class="-mt-8 mb-4 text-center">
                                <i class="fas fa-hourglass-half text-3xl text-orange-500"></i>
                                <h3 class="mt-1 text-base font-semibold text-gray-900">Hanging Registrations</h3>
                            </div>

                            <p class="text-sm text-gray-700 mb-4">
                                When an admin registers a person, that registration must always end in one of
                                two outcomes: the person is assigned to an RC Unit (becoming a volunteer), or
                                the person completes an approved membership payment (becoming a member). This
                                count tracks people, registered after the new database's launch date, who are
                                still stuck in pending lifecycle with neither outcome — no RC Unit and no
                                approved payment.
                            </p>

                            <p class="text-sm text-gray-700 mb-4">
                                Payment approval can take a little time, so this number won't always sit at
                                exactly 0 — that's expected. What matters is that it isn't growing over time.
                            </p>

                            <p class="text-sm font-semibold text-gray-800 mb-2">How to find them:</p>
                            <p class="text-sm text-gray-700 mb-4">
                                <span class="font-semibold">Persons</span> → set filter to
                                <span class="font-semibold">Registered by Admin</span> →
                                <span class="font-semibold">Life-cycle = Pending</span>.
                            </p>

                            <p class="text-sm text-gray-700">
                                Note: this filter has no "registered after date X" option yet, so it will also
                                show some older hanging registrations from before the new database's launch.
                                That's a known minor limitation, not a bug.
                            </p>
                        </x-help-popup>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-4 w-64 text-center">
                    <a href="{{ route('users.index', array_filter(['person_type' => 'unassigned', 'branch_id' => $dashboardData['branchId']])) }}" class="block hover:opacity-80 transition">
                        <p class="text-3xl font-bold text-orange-600">{{ number_format($dashboardData['unassignedGhostCount']) }}</p>
                        <p class="mt-1 text-sm text-gray-600">Volunteers in Limbo</p>
                    </a>
                    <div class="mt-2">
                        <x-help-popup trigger-class="help-btn">
                            <x-slot:trigger><i class="fas fa-question-circle mr-1"></i> What is this?</x-slot:trigger>

                            <div class="-mt-8 mb-4 text-center">
                                <i class="fas fa-user-slash text-3xl text-orange-500"></i>
                                <h3 class="mt-1 text-base font-semibold text-gray-900">Volunteers in Limbo</h3>
                            </div>

                            <p class="text-sm text-gray-700 mb-4">
                                These are volunteers who were once assigned to a Red Cross Unit but have since been
                                removed from it, without being assigned to a new one. This usually happens mid-transfer
                                between branches — someone is unassigned from their old unit, but the process stalls
                                before a new branch picks them up.
                            </p>

                            <p class="text-sm font-semibold text-gray-800 mb-2">How to find them:</p>
                            <p class="text-sm text-gray-700 mb-4">
                                <span class="font-semibold">Persons</span> → <span class="font-semibold">Members/Volunteers filter</span> → <span class="font-semibold">Unassigned</span>.
                            </p>

                            <p class="text-sm font-semibold text-gray-800 mb-2">What to do:</p>
                            <ul class="space-y-1 text-sm text-gray-700 list-disc pl-4">
                                <li>A <span class="font-semibold">National DB Administrator</span> can move the person directly to the correct branch — they aren't limited to their own branch.</li>
                                <li>Or, the volunteer can update their own branch via <span class="font-semibold">My Profile</span> — but they may need a nudge or a call, since they may not realize the move is still incomplete.</li>
                                <li>Once assigned to a unit again, they'll drop off this count automatically.</li>
                            </ul>
                        </x-help-popup>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-4 flex justify-center">
            <a href="{{ route('reports.policies') }}"
               class="inline-flex items-center gap-2 px-5 py-2.5 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-sm text-sm font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                <i class="fas fa-scale-balanced text-indigo-600"></i> Policies &amp; Rules
            </a>
        </div>
    </section>

    @if(auth()->user()->primary_role_name)
        <section class="mt-16 mb-8  px-4 sm:px-6 lg:px-8">
            <h2 class="dashboard-heading text-center block w-full">Your Role</h2>
            <div class="mt-4 max-w-2xl mx-auto">
                <x-role-description-card :user="auth()->user()" />
            </div>
        </section>
    @endif


    @php
        $myRole = auth()->user()->primary_role_name; // machine name, e.g. 'branch_secretary'
        $myExtraPermissions = auth()->user()->getDirectPermissions()->pluck('name');
        $extraPermissionLabels = [
            'send_bulk_messages' => 'Bulk Messaging',
            'print_idcards' => 'Print ID Cards',
            'print_certificates' => 'Print Certificates',
            'campaign_request_approve' => 'Campaign Request Approve',
        ];
    @endphp

    <section class="mt-6 mb-20  px-4 sm:px-6 lg:px-8">
        <div class="max-w-3xl mx-auto">
            <h2 class="text-xl font-bold text-gray-900 mb-1 text-center">Your place in the team</h2>
            <p class="text-sm text-gray-500 text-center mb-5">Your role is highlighted below.</p>

            <div class="space-y-4">

                {{-- National tier --}}
                <div class="flex flex-col md:flex-row md:items-stretch gap-3">
                    <div class="md:w-28 flex items-center justify-center border-l-4 border-red-600 text-red-700 font-bold text-sm uppercase tracking-wide py-2">
                        National
                    </div>
                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @php $on = $myRole === 'national_db_administrator'; @endphp
                        <div class="relative rounded-xl border p-3 text-center transition {{ $on ? 'bg-indigo-50 border-indigo-400 ring-2 ring-indigo-400 shadow-sm' : 'bg-gray-50 border-gray-100 opacity-60' }}">
                            @if($on)<span class="absolute -top-2 left-1/2 -translate-x-1/2 bg-indigo-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full whitespace-nowrap">YOU</span>@endif
                            @if($on && $myExtraPermissions->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-1 justify-center">
                                    @foreach($myExtraPermissions as $perm)
                                        <span class="inline-block px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-semibold">
                                            {{ $extraPermissionLabels[$perm] ?? \Illuminate\Support\Str::headline($perm) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                            <i class="fas fa-user-shield text-2xl {{ $on ? 'text-indigo-500' : 'text-red-500' }} mb-1"></i>
                            <p class="font-semibold text-gray-800 text-sm">National DB Administrator</p>
                            <p class="text-xs text-gray-500">Authorizes &amp; oversees</p>
                        </div>

                        @php $on = $myRole === 'national_db_assistant'; @endphp
                        <div class="relative rounded-xl border p-3 text-center transition {{ $on ? 'bg-indigo-50 border-indigo-400 ring-2 ring-indigo-400 shadow-sm' : 'bg-gray-50 border-gray-100 opacity-60' }}">
                            @if($on)<span class="absolute -top-2 left-1/2 -translate-x-1/2 bg-indigo-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full whitespace-nowrap">YOU</span>@endif
                            @if($on && $myExtraPermissions->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-1 justify-center">
                                    @foreach($myExtraPermissions as $perm)
                                        <span class="inline-block px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-semibold">
                                            {{ $extraPermissionLabels[$perm] ?? \Illuminate\Support\Str::headline($perm) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                            <i class="fas fa-keyboard text-2xl {{ $on ? 'text-indigo-500' : 'text-red-400' }} mb-1"></i>
                            <p class="font-semibold text-gray-800 text-sm">National DB Assistant</p>
                            <p class="text-xs text-gray-500">National data entry</p>
                        </div>

                        @php $on = $myRole === 'observer_national_level'; @endphp
                        <div class="relative rounded-xl border p-3 text-center transition {{ $on ? 'bg-indigo-50 border-indigo-400 ring-2 ring-indigo-400 shadow-sm' : 'bg-gray-50 border-gray-100 opacity-60' }}">
                            @if($on)<span class="absolute -top-2 left-1/2 -translate-x-1/2 bg-indigo-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full whitespace-nowrap">YOU</span>@endif
                            @if($on && $myExtraPermissions->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-1 justify-center">
                                    @foreach($myExtraPermissions as $perm)
                                        <span class="inline-block px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-semibold">
                                            {{ $extraPermissionLabels[$perm] ?? \Illuminate\Support\Str::headline($perm) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                            <i class="fas fa-eye text-2xl {{ $on ? 'text-indigo-500' : 'text-gray-400' }} mb-1"></i>
                            <p class="font-semibold text-gray-800 text-sm">Observer</p>
                            <p class="text-xs text-gray-500">Read-only reports</p>
                        </div>
                    </div>
                </div>

                {{-- Branch tier --}}
                <div class="flex flex-col md:flex-row md:items-stretch gap-3">
                    <div class="md:w-28 flex items-center justify-center border-l-4 border-red-400 text-red-500 font-bold text-sm uppercase tracking-wide py-2">
                        Branch
                    </div>
                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-3 gap-3">
                        @php $on = $myRole === 'branch_secretary'; @endphp
                        <div class="relative rounded-xl border p-3 text-center transition {{ $on ? 'bg-indigo-50 border-indigo-400 ring-2 ring-indigo-400 shadow-sm' : 'bg-gray-50 border-gray-100 opacity-60' }}">
                            @if($on)<span class="absolute -top-2 left-1/2 -translate-x-1/2 bg-indigo-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full whitespace-nowrap">YOU</span>@endif
                            @if($on && $myExtraPermissions->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-1 justify-center">
                                    @foreach($myExtraPermissions as $perm)
                                        <span class="inline-block px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-semibold">
                                            {{ $extraPermissionLabels[$perm] ?? \Illuminate\Support\Str::headline($perm) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                            <i class="fas fa-user-tie text-2xl {{ $on ? 'text-indigo-500' : 'text-red-500' }} mb-1"></i>
                            <p class="font-semibold text-gray-800 text-sm">Branch Secretary</p>
                            <p class="text-xs text-gray-500">Branch administration</p>
                        </div>

                        @php $on = $myRole === 'branch_db_administrator'; @endphp
                        <div class="relative rounded-xl border p-3 text-center transition {{ $on ? 'bg-indigo-50 border-indigo-400 ring-2 ring-indigo-400 shadow-sm' : 'bg-gray-50 border-gray-100 opacity-60' }}">
                            @if($on)<span class="absolute -top-2 left-1/2 -translate-x-1/2 bg-indigo-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full whitespace-nowrap">YOU</span>@endif
                            @if($on && $myExtraPermissions->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-1 justify-center">
                                    @foreach($myExtraPermissions as $perm)
                                        <span class="inline-block px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-semibold">
                                            {{ $extraPermissionLabels[$perm] ?? \Illuminate\Support\Str::headline($perm) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                            <i class="fas fa-user-shield text-2xl {{ $on ? 'text-indigo-500' : 'text-red-500' }} mb-1"></i>
                            <p class="font-semibold text-gray-800 text-sm">Branch DB Administrator</p>
                            <p class="text-xs text-gray-500">Same authority</p>
                        </div>

                        @php $on = $myRole === 'branch_db_assistant'; @endphp
                        <div class="relative rounded-xl border p-3 text-center transition {{ $on ? 'bg-indigo-50 border-indigo-400 ring-2 ring-indigo-400 shadow-sm' : 'bg-gray-50 border-gray-100 opacity-60' }}">
                            @if($on)<span class="absolute -top-2 left-1/2 -translate-x-1/2 bg-indigo-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full whitespace-nowrap">YOU</span>@endif
                            @if($on && $myExtraPermissions->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-1 justify-center">
                                    @foreach($myExtraPermissions as $perm)
                                        <span class="inline-block px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-semibold">
                                            {{ $extraPermissionLabels[$perm] ?? \Illuminate\Support\Str::headline($perm) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                            <i class="fas fa-keyboard text-2xl {{ $on ? 'text-indigo-500' : 'text-red-400' }} mb-1"></i>
                            <p class="font-semibold text-gray-800 text-sm">Branch DB Assistant</p>
                            <p class="text-xs text-gray-500">Branch data entry</p>
                        </div>
                    </div>
                </div>

                {{-- Division tier --}}
                <div class="flex flex-col md:flex-row md:items-stretch gap-3">
                    <div class="md:w-28 flex items-center justify-center border-l-4 border-red-300 text-red-700 font-bold text-sm uppercase tracking-wide py-2">
                        Division
                    </div>
                    <div class="flex-1 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @php $on = $myRole === 'division_db_assistant_finance'; @endphp
                        <div class="relative rounded-xl border p-3 text-center transition {{ $on ? 'bg-indigo-50 border-indigo-400 ring-2 ring-indigo-400 shadow-sm' : 'bg-gray-50 border-gray-100 opacity-60' }}">
                            @if($on)<span class="absolute -top-2 left-1/2 -translate-x-1/2 bg-indigo-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full whitespace-nowrap">YOU</span>@endif
                            @if($on && $myExtraPermissions->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-1 justify-center">
                                    @foreach($myExtraPermissions as $perm)
                                        <span class="inline-block px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-semibold">
                                            {{ $extraPermissionLabels[$perm] ?? \Illuminate\Support\Str::headline($perm) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                            <i class="fas fa-coins text-2xl {{ $on ? 'text-indigo-500' : 'text-amber-500' }} mb-1"></i>
                            <p class="font-semibold text-gray-800 text-sm">Division Assistant — Finance</p>
                            <p class="text-xs text-gray-500">Payments, Donations, Trainings &amp; Volunteering</p>
                        </div>

                        @php $on = $myRole === 'division_db_assistant_operations'; @endphp
                        <div class="relative rounded-xl border p-3 text-center transition {{ $on ? 'bg-indigo-50 border-indigo-400 ring-2 ring-indigo-400 shadow-sm' : 'bg-gray-50 border-gray-100 opacity-60' }}">
                            @if($on)<span class="absolute -top-2 left-1/2 -translate-x-1/2 bg-indigo-600 text-white text-[10px] font-bold px-2 py-0.5 rounded-full whitespace-nowrap">YOU</span>@endif
                            @if($on && $myExtraPermissions->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-1 justify-center">
                                    @foreach($myExtraPermissions as $perm)
                                        <span class="inline-block px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-semibold">
                                            {{ $extraPermissionLabels[$perm] ?? \Illuminate\Support\Str::headline($perm) }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                            <i class="fas fa-clipboard-list text-2xl {{ $on ? 'text-indigo-500' : 'text-indigo-400' }} mb-1"></i>
                            <p class="font-semibold text-gray-800 text-sm">Division Assistant — Operations</p>
                            <p class="text-xs text-gray-500">Trainings &amp; Volunteering</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>





@if (app()->environment('local'))
        @php
            $loadTime = microtime(true) - LARAVEL_START;
        @endphp
        <div style="position: fixed; bottom: 10px; right: 10px;
                background: #111; color: #fff; padding: 6px 10px;
                border-radius: 6px; font-size: 12px; opacity: 0.85; z-index: 9999;">
            Loaded in {{ number_format($loadTime, 3) }}s
        </div>
    @endif
</x-layouts.admin>
