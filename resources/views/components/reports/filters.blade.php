@props(['action', 'years', 'selectedYear', 'trendOptions', 'selectedTrendKey'])

<form method="GET" action="{{ $action }}" class="flex flex-wrap items-center gap-3">


    {{-- New filter for trend months --}}
    <div>
        <label for="trend_months" class="text-sm font-medium text-gray-700 dark:text-gray-300">Trend</label>
        <select name="trend_months" id="trend_months"
                class="form-select text-sm rounded-md border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 py-1.5 px-3 text-gray-800 dark:text-gray-100 focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                onchange="this.form.submit()">
            @foreach($trendOptions as $key => $months)
                <option value="{{ $key }}" @selected($key == $selectedTrendKey)>
                    {{ ($months / 12) }} years
                </option>
            @endforeach
        </select>
    </div>

    {{-- The 'Apply' button is removed --}}
</form>
