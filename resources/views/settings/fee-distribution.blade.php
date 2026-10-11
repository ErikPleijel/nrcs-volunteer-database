<x-layouts.admin title="Fee Distribution">
    <x-slot name="pageHeader">
        <i class="fas fa-percent mr-3"></i> Fee Distribution
    </x-slot>
    <x-audit-notice />

    <x-slot name="subHeader">Settings — How online membership income is shared</x-slot>

    <div class="container mx-auto px-4 py-8 max-w-3xl">

        @if(session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p class="font-bold">Success</p>
                <p>{{ session('success') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p class="font-bold">Fee distribution not saved</p>
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        <p class="text-sm text-gray-500 mb-2">
            How the net income from online (Paystack) membership payments — the amount paid minus the Paystack charge — is shared between HQ, the branch and the division.
            Each set of percentages must add up to exactly 100. A payment with no branch goes 100% to HQ.
        </p>
        <p class="text-sm font-semibold text-gray-700 mb-6">
            Changes apply only to payments made after saving. Earlier payments keep the percentages that applied when they were paid.
        </p>

        <form method="POST" action="{{ route('admin.settings.fee-distribution.update') }}" class="space-y-8">
            @csrf

            @foreach($percentages as $rule => $parties)
                @php
                    $values = collect($parties)->map(fn ($value, $party) => old("percentages.{$rule}.{$party}", $value));
                @endphp
                <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden"
                     x-data="{ values: @js($values) }">
                    <div class="bg-gray-50 border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                        <h3 class="text-base font-semibold text-gray-900">{{ $ruleLabels[$rule] }}</h3>
                        <span class="text-sm font-semibold"
                              :class="Object.values(values).reduce((sum, v) => sum + (parseInt(v) || 0), 0) === 100 ? 'text-green-700' : 'text-red-700'">
                            Total: <span x-text="Object.values(values).reduce((sum, v) => sum + (parseInt(v) || 0), 0)">{{ $values->sum() }}</span>%
                        </span>
                    </div>

                    <div class="px-6 py-5 space-y-4">
                        @foreach($parties as $party => $value)
                            <div class="flex items-center gap-3">
                                <label for="pct-{{ $rule }}-{{ $party }}" class="w-24 text-sm font-medium text-gray-700">{{ $partyLabels[$party] }}</label>
                                <input type="number"
                                       id="pct-{{ $rule }}-{{ $party }}"
                                       name="percentages[{{ $rule }}][{{ $party }}]"
                                       value="{{ $values[$party] }}"
                                       x-model="values.{{ $party }}"
                                       min="0" max="100" step="1" required
                                       class="w-24 rounded-md border-2 border-gray-300 px-2 py-1 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                                <span class="text-sm text-gray-500">%</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="flex justify-end pt-4">
                <button type="submit"
                        class="inline-flex items-center px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-md shadow transition">
                    <i class="fas fa-save mr-2"></i> Save Fee Distribution
                </button>
            </div>
        </form>

        <div class="mt-6">
            <a href="{{ route('admin.settings.index') }}" class="btn-backlink">← Back to Settings</a>
        </div>

    </div>
</x-layouts.admin>
