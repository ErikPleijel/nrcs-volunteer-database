<div class="mt-8 space-y-8">
    @if($links !== [])
        <section class="bg-white rounded-lg shadow-lg p-6" data-useful-links>
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-link text-blue-600 text-xl"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-900">Useful links</h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($links as $link)
                    <a href="{{ $link['url'] }}" target="_blank" rel="noopener noreferrer"
                       class="group flex flex-col h-full p-4 bg-gray-50 border border-gray-200 rounded-lg hover:bg-white hover:border-blue-300 hover:shadow-md transition">
                        <span class="flex items-start justify-between gap-3">
                            <span class="font-semibold text-gray-900 group-hover:text-blue-700">{{ $link['title'] }}</span>
                            <i class="fas fa-arrow-up-right-from-square text-sm text-gray-400 group-hover:text-blue-600 mt-1" aria-hidden="true"></i>
                        </span>
                        @if($link['description'] !== '')
                            <span class="mt-1 text-sm text-gray-600">{{ $link['description'] }}</span>
                        @endif
                        <span class="mt-auto pt-3 text-xs text-gray-500">
                            <i class="fas fa-globe mr-1" aria-hidden="true"></i>{{ $link['domain'] }}
                        </span>
                        <span class="sr-only">(opens in a new tab)</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if($downloads !== [])
        <section class="bg-white rounded-lg shadow-lg p-6" data-downloads>
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-download text-blue-600 text-xl"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-900">Downloads</h2>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                @foreach($downloads as $download)
                    <a href="{{ $download['href'] }}" target="_blank" rel="noopener noreferrer"
                       class="group flex items-start gap-3 h-full p-4 bg-gray-50 border border-gray-200 rounded-lg hover:bg-white hover:border-blue-300 hover:shadow-md transition">
                        @php
                            $iconColour = match ($download['icon']) {
                                'fa-file-pdf' => 'text-red-600',
                                'fa-file-word' => 'text-blue-700',
                                'fa-file-excel' => 'text-green-700',
                                default => 'text-gray-500',
                            };
                        @endphp
                        <i class="fas {{ $download['icon'] }} {{ $iconColour }} text-3xl mt-0.5" aria-hidden="true"></i>
                        <span class="flex flex-col flex-1 min-w-0 h-full">
                            <span class="font-semibold text-gray-900 group-hover:text-blue-700">{{ $download['title'] }}</span>
                            @if($download['description'] !== '')
                                <span class="mt-1 text-sm text-gray-600">{{ $download['description'] }}</span>
                            @endif
                            <span class="mt-auto pt-3 flex items-center justify-between gap-2 text-xs">
                                <span class="text-gray-500 truncate">
                                    {{ $download['type'] }}@if($download['note'] !== '') · {{ $download['note'] }}@endif
                                </span>
                                <span class="font-semibold text-blue-700 whitespace-nowrap">
                                    <i class="fas fa-download mr-1" aria-hidden="true"></i>Download
                                </span>
                            </span>
                        </span>
                        <span class="sr-only">(opens in a new tab)</span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>
