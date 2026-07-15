@props([
    'title' => 'Lifecycle',
    'subtitle' => null,
    'statusLabel' => null,   // e.g. "Dormant"
    'statusHint' => null,    // e.g. "Re-engage & confirm interest"
])

<x-layouts.admin>
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        {{-- Header --}}
        <div class="mb-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-gray-900">{{ $title }}</h1>

                    @if($subtitle)
                        <p class="mt-1 text-sm text-gray-600">{{ $subtitle }}</p>
                    @endif
                </div>

                @if($statusLabel)
                    <div class="text-right">
                        <span class="inline-flex items-center rounded-full bg-slate-50 px-3 py-1 text-xs font-medium text-slate-700 ring-1 ring-inset ring-slate-200">
                            {{ $statusLabel }}
                        </span>
                        @if($statusHint)
                            <div class="mt-1 text-xs text-gray-500">{{ $statusHint }}</div>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Tabs / quick nav (optional) --}}
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('lifecycle.awaiting_engagement') }}"
                   class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-200 hover:bg-gray-50">
                    Awaiting engagement
                </a>

                <a href="{{ route('lifecycle.active') }}"
                   class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-200 hover:bg-gray-50">
                    Active
                </a>

                <a href="{{ route('lifecycle.dormant') }}"
                   class="inline-flex items-center rounded-md bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-800 ring-1 ring-inset ring-slate-200">
                    Dormant
                </a>

                <a href="{{ route('lifecycle.archived') }}"
                   class="inline-flex items-center rounded-md bg-white px-3 py-2 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-200 hover:bg-gray-50">
                    Archived
                </a>
            </div>

        </div>

        {{-- Page content --}}
        <div {{ $attributes->merge(['class' => 'space-y-6']) }}>
            {{ $slot }}
        </div>
    </div>
</x-layouts.admin>
