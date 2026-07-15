<x-layouts.admin title="Tutorials">
    <x-slot name="pageHeader">
        <i class="fas fa-graduation-cap mr-3 mb-6"></i> Tutorials
    </x-slot>

    <div class="p-4 md:p-6 max-w-3xl mx-auto">

        <a href="{{ route('reports.dashboard') }}"
           class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 mb-8">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

        @php
            $lockLabels = [
                'branch'   => 'Available to branch and national administrators',
                'national' => 'Available to national administrators only',
            ];
        @endphp

        <div class="space-y-10">

            @foreach($levels as $levelData)
                @php $unlocked = $levelData['unlocked']; @endphp

                <section>

                    {{-- Level section heading --}}
                    <div class="flex items-center gap-3 mb-4">
                        <i class="fas {{ $levelData['icon'] }} text-xl {{ $unlocked ? 'text-indigo-500' : 'text-gray-300' }}"></i>
                        <h2 class="text-lg font-bold {{ $unlocked ? 'text-gray-800' : 'text-gray-400' }}">
                            @if($levelData['level'] === 0)
                                {{ $levelData['title'] }}
                            @else
                                Level {{ $levelData['level'] }}: {{ $levelData['title'] }}
                            @endif
                        </h2>
                        @if(!$unlocked)
                            <i class="fas fa-lock text-gray-300 text-sm"></i>
                        @endif
                    </div>

                    @if(!$unlocked)
                        {{-- Locked level: greyed placeholder matching dashboard locked-card style --}}
                        <div class="flex items-center gap-3 p-4 bg-white rounded-xl border border-gray-100 opacity-50 cursor-not-allowed"
                             title="{{ $lockLabels[$levelData['min_access'][0]] ?? '' }}">
                            <i class="fas fa-lock text-gray-400 flex-shrink-0"></i>
                            <span class="text-sm text-gray-500">
                                {{ $lockLabels[$levelData['min_access'][0]] ?? 'Restricted access' }}
                            </span>
                        </div>

                    @elseif($levelData['lessons']->isEmpty())
                        <div class="p-4 bg-gray-50 rounded-xl border border-gray-100 text-gray-400 text-sm">
                            <i class="fas fa-clock mr-1"></i> No lessons available yet — check back soon.
                        </div>

                    @else
                        <div class="space-y-3">
                            @foreach($levelData['lessons'] as $lesson)
                                <a href="{{ route('tutorials.lesson', $lesson['key']) }}"
                                   class="flex items-center gap-4 p-4 bg-white rounded-xl shadow-sm hover:shadow-md transition border border-gray-100">

                                    <div class="flex-shrink-0">
                                        @if($lesson['completed'])
                                            <span class="flex items-center justify-center w-9 h-9 bg-green-100 text-green-700 rounded-full">
                                                <i class="fas fa-check"></i>
                                            </span>
                                        @else
                                            <span class="flex items-center justify-center w-9 h-9 bg-gray-100 text-gray-400 rounded-full text-xs font-bold">
                                                {{ $lesson['order'] }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-gray-900">{{ $lesson['title'] }}</p>
                                        <p class="text-xs mt-0.5 {{ $lesson['completed'] ? 'text-green-600' : 'text-gray-400' }}">
                                            {{ $lesson['completed'] ? 'Completed' : 'Not yet started' }}
                                        </p>
                                    </div>

                                    <i class="fas fa-chevron-right text-gray-300 flex-shrink-0"></i>
                                </a>
                            @endforeach
                        </div>
                    @endif

                </section>
            @endforeach

        </div>

    </div>
</x-layouts.admin>
