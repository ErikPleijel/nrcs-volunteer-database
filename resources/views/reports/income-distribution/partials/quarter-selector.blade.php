{{-- Quarter selector shared by the branch and division levels, plus the
     warning for a quarter that has not finished yet.
     Expects: $quarterOptions, $selectedQuarter, $quarterInProgress,
     $action (form URL), $hidden (extra query params to keep). --}}
<div class="flex flex-col items-center gap-2 mb-4">
    <form action="{{ $action }}" method="GET" class="flex items-center gap-2">
        @foreach($hidden as $name => $value)
            <input type="hidden" name="{{ $name }}" value="{{ $value }}">
        @endforeach
        <label for="quarter" class="filter-label-small mb-0">Quarter</label>
        <select name="quarter" id="quarter" class="filter-select-small" onchange="this.form.submit()">
            @foreach($quarterOptions as $key => $label)
                <option value="{{ $key }}" @selected($selectedQuarter === $key)>{{ $label }}</option>
            @endforeach
        </select>
    </form>
</div>

@if($quarterInProgress)
    <div class="bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 p-4 mb-6" role="alert" data-testid="in-progress-note">
        <p class="font-semibold">
            <i class="fas fa-hourglass-half mr-1"></i>{{ \App\Services\Reports\IncomeDistributionStatsService::IN_PROGRESS_NOTE }}
        </p>
    </div>
@endif
