{{-- This file replaces your original resources/views/reports/layout.blade.php --}}
@props(['title', 'pageHeader', 'breadcrumbs'])

<x-layouts.admin :title="$title ?? 'Reports'">
    {{-- This slot passes content to the 'pageHeader' slot of the x-layouts.admin component --}}
    <x-slot  name="pageHeader">
        <div class="mb-6">
            {!! $pageHeader ?? 'Reports' !!}
        </div>
    </x-slot>

    {{-- Include breadcrumbs if provided. Using the new x-reports.breadcrumbs component. --}}
    @isset($breadcrumbs)
        <x-reports.breadcrumbs :breadcrumbs="$breadcrumbs" />
    @endisset

    {{-- This is the default slot for the main content of the report --}}
    <div>
        {{ $slot }}
    </div>


    {{-- This is a named slot for any filter forms --}}
    <div class="mb-4">
        {{ $filters ?? '' }}
    </div>

    @push('scripts')
        {{-- Chart.js script is pushed to the 'scripts' stack defined in x-layouts.admin --}}
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    @endpush

</x-layouts.admin>
