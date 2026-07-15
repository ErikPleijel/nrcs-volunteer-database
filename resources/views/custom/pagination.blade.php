@if ($paginator->hasPages())
    <div class="px-6 py-4 border-t bg-gray-50">
        <!-- Navigation Buttons -->
        <div class="flex justify-center space-x-4 mb-4">
            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center px-6 py-3 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-400 bg-gray-200 cursor-not-allowed">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    Previous
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="inline-flex items-center px-6 py-3 border border-red-500 shadow-lg text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 hover:border-red-600 transition-colors" rel="prev" aria-label="@lang('pagination.previous')">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                    </svg>
                    Previous
                </a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="inline-flex items-center px-6 py-3 border border-red-500 shadow-lg text-sm font-medium rounded-md text-white bg-red-600 hover:bg-red-700 hover:border-red-600 transition-colors" rel="next" aria-label="@lang('pagination.next')">
                    Next
                    <svg class="ml-2 -mr-1 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                    </svg>
                </a>
            @else
                <span class="inline-flex items-center px-6 py-3 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-400 bg-gray-200 cursor-not-allowed">
                    Next
                    <svg class="ml-2 -mr-1 h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                    </svg>
                </span>
            @endif
        </div>

        <!-- Page Numbers -->
        <div class="flex justify-center items-center space-x-1 mb-4">
            @php
                $start = max($paginator->currentPage() - 2, 1);
                $end = min($start + 4, $paginator->lastPage());
                $start = max($end - 4, 1);
            @endphp

            {{-- First page --}}
            @if($start > 1)
                <a href="{{ $paginator->url(1) }}" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-red-600">
                    1
                </a>
                @if($start > 2)
                    <span class="px-3 py-2 text-sm text-gray-500">...</span>
                @endif
            @endif

            {{-- Page numbers --}}
            @for ($i = $start; $i <= $end; $i++)
                @if ($i == $paginator->currentPage())
                    <span class="px-3 py-2 text-sm font-medium text-white bg-red-600 border border-red-600 rounded-md" aria-current="page">
                        {{ $i }}
                    </span>
                @else
                    <a href="{{ $paginator->url($i) }}" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-red-600">
                        {{ $i }}
                    </a>
                @endif
            @endfor

            {{-- Last page --}}
            @if($end < $paginator->lastPage())
                @if($end < $paginator->lastPage() - 1)
                    <span class="px-3 py-2 text-sm text-gray-500">...</span>
                @endif
                <a href="{{ $paginator->url($paginator->lastPage()) }}" class="px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 hover:text-red-600">
                    {{ $paginator->lastPage() }}
                </a>
            @endif
        </div>

        <!-- Results Information -->
        <div class="text-center text-sm text-gray-700">
            <div>
                {!! __('Showing') !!}
                @if ($paginator->firstItem())
                    <span class="font-medium">{{ $paginator->firstItem() }}</span>
                    {!! __('to') !!}
                    <span class="font-medium">{{ $paginator->lastItem() }}</span>
                @else
                    {{ $paginator->count() }}
                @endif
                {!! __('of') !!}
                <span class="font-medium">{{ $paginator->total() }}</span>
                {!! __('results') !!}
            </div>
            <div class="mt-1 font-medium text-gray-800">
                (Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }})
            </div>
        </div>
    </div>
@endif
