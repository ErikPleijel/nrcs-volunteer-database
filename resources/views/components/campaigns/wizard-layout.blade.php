@props([
  'campaign',
  'step' => 1,
  'title' => 'Campaign wizard',
  'subtitle' => null,
])

<x-layouts.admin title="Campaign Wizard">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-gray-900">{{ $title }}</h1>
                @if($subtitle)
                    <p class="mt-1 text-sm text-gray-600">{{ $subtitle }}</p>
                @endif
            </div>
            <div class="text-right">
                <div class="text-xs text-gray-500">Status</div>
                <div class="text-sm font-semibold text-gray-900">{{ ucfirst($campaign->status) }}</div>
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-md bg-green-50 border border-green-200 p-4 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="rounded-md bg-red-50 border border-red-200 p-4 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif

        <div class="rounded-lg border border-gray-200 bg-yellow-100 p-4">
            <div class="flex flex-wrap gap-2 text-xl">
                @foreach([
                  1 => 'Purpose',
                  2 => 'Audience',
                  3 => 'Throttling',
                  4 => 'Message',
                  5 => 'Review',
                ] as $i => $label)
                    <span class="inline-flex items-center rounded-full px-3 py-1 ring-1 ring-inset
            {{ $i === $step ? 'bg-slate-700 text-white ring-slate-700' : 'bg-gray-50 text-gray-700 ring-gray-200' }}">
            {{ $i }}. {{ $label }}
          </span>
                @endforeach
            </div>
        </div>

        {{ $slot }}
    </div>
</x-layouts.admin>
