<x-layouts.admin title="{{ $moduleLabel }} Approvals">
    <x-slot name="pageHeader">
        <i class="fas fa-clipboard-check mr-3"></i>{{ $moduleLabel }} Approvals
    </x-slot>
    <x-slot name="subHeader">
        Pending {{ \Illuminate\Support\Str::plural(strtolower($moduleLabel)) }} awaiting your review
    </x-slot>

    @push('styles')
        <style>[x-cloak]{ display:none !important; }</style>
    @endpush

    <div class="container mx-auto px-4 py-6">

        <x-approval-tabs
            active="approvals"
            :records-route="route($routeName . '.index')"
            :approvals-route="route($routeName . '.approvals')"
            :permission="$permission"
            :pending-count="$pendingCount" />

        @include('approvals._flash')

        {{-- Bulk-approve result summary --}}
        @if(session('bulk_result'))
            @php $br = session('bulk_result'); @endphp
            <div class="mb-5 rounded-lg border px-4 py-3 text-sm {{ count($br['skipped']) ? 'border-yellow-200 bg-yellow-50 text-yellow-800' : 'border-green-200 bg-green-50 text-green-800' }}">
                <p class="font-medium">
                    <i class="fas fa-check-double mr-1"></i>
                    Approved {{ $br['approved'] }}; skipped {{ count($br['skipped']) }}@if(count($br['skipped'])) — open individually:@endif
                </p>
                @if(count($br['skipped']))
                    <ul class="mt-2 space-y-1">
                        @foreach($br['skipped'] as $s)
                            <li class="flex items-start gap-2">
                                <a href="{{ route($br['routeName'] . '.review', $s['id']) }}" class="text-blue-700 hover:text-blue-900 underline whitespace-nowrap">{{ $br['label'] }} #{{ $s['id'] }}</a>
                                <span>— {{ $s['reason'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif

        {{-- National-only narrowing filter --}}
        @if($accessLevel === 'national')
            <form method="GET" action="{{ route($routeName . '.approvals') }}"
                  class="mb-5 flex flex-wrap items-end gap-3 bg-white shadow-sm border border-gray-200 rounded-lg p-4">
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1">Branch</label>
                    <select name="branch_id" onchange="this.form.submit()"
                            class="border border-gray-300 rounded-md text-sm px-3 py-2">
                        <option value="">All branches</option>
                        @foreach($branches as $branch)
                            <option value="{{ $branch->id }}" {{ request('branch_id') == $branch->id ? 'selected' : '' }}>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                @if($divisions->isNotEmpty())
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1">Division</label>
                        <select name="division_id" onchange="this.form.submit()"
                                class="border border-gray-300 rounded-md text-sm px-3 py-2">
                            <option value="">All divisions</option>
                            @foreach($divisions as $division)
                                <option value="{{ $division->id }}" {{ request('division_id') == $division->id ? 'selected' : '' }}>
                                    {{ $division->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif
                @if(request('branch_id') || request('division_id'))
                    <a href="{{ route($routeName . '.approvals') }}" class="text-sm text-gray-500 hover:text-gray-700 underline">Clear</a>
                @endif
            </form>
        @endif

        @php
            // Members who are archived are excluded from bulk selection — they must be
            // reviewed individually so the approver consciously confirms reactivation.
            $selectableIds = $records->getCollection()
                ->reject(fn ($r) => $r->user && $r->user->lifecycle_status === 'archived')
                ->pluck('id')->values();
        @endphp

        <div x-data="{ selected: [], allIds: @js($selectableIds) }">

            @if($records->count() > 0)
                {{-- Bulk toolbar --}}
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3 bg-white border border-gray-200 rounded-lg px-4 py-2">
                    <label class="inline-flex items-center text-sm text-gray-700 select-none">
                        <input type="checkbox" class="mr-2 rounded border-gray-300"
                               @change="selected = $event.target.checked ? allIds.slice() : []"
                               :checked="allIds.length > 0 && selected.length === allIds.length">
                        Select all approvable (<span x-text="allIds.length"></span>)
                    </label>
                    <form method="POST" action="{{ route($routeName . '.bulk-approve') }}"
                          @submit="if (! confirm('Approve ' + selected.length + ' selected record(s)?')) $event.preventDefault()">
                        @csrf
                        <template x-for="id in selected" :key="id">
                            <input type="hidden" name="ids[]" :value="id">
                        </template>
                        <button type="submit" :disabled="selected.length === 0"
                                class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 disabled:opacity-40 disabled:cursor-not-allowed">
                            <i class="fas fa-check-double mr-2"></i>Approve selected (<span x-text="selected.length"></span>)
                        </button>
                    </form>
                </div>
            @endif

        <div class="table-container">
            @if($records->count() > 0)
                <div class="hidden lg:block table-wrapper">
                    <table class="data-table">
                        <thead class="table-header">
                        <tr class="table-header-row">
                            <th class="table-header-cell w-10"></th>
                            <th class="table-header-cell">Member</th>
                            <th class="table-header-cell">Details</th>
                            <th class="table-header-cell">Location</th>
                            <th class="table-header-cell">Submitted by</th>
                            <th class="table-header-cell">Submitted</th>
                            <th class="table-header-cell">Actions</th>
                        </tr>
                        </thead>
                        <tbody class="table-body">
                        @foreach($records as $record)
                            @php
                                $rowArchived = $record->user && $record->user->lifecycle_status === 'archived';
                                $rowSelfDirected = $record->isSelfDirected;
                            @endphp
                            <tr class="table-body-row {{ ($rowArchived || $rowSelfDirected) ? 'bg-yellow-50' : '' }}">
                                <td class="table-body-cell text-center">
                                    @if($rowArchived)
                                        <span class="text-yellow-600" title="Archived member — review individually"><i class="fas fa-lock"></i></span>
                                    @else
                                        <input type="checkbox" class="rounded border-gray-300" :value="{{ $record->id }}" x-model="selected">
                                    @endif
                                </td>
                                <td class="table-body-cell">
                                    <div class="table-field-main">{{ $record->user?->full_name ?? 'N/A' }}</div>
                                    @if($record->user)
                                        <div class="table-field-sub">{{ $record->user->user_id_reference_short }}</div>
                                        @if($rowArchived)
                                            <span class="inline-flex items-center mt-1 px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                <i class="fas fa-box-archive mr-1"></i>Archived — review individually
                                            </span>
                                        @endif
                                        @if($rowSelfDirected)
                                            <span class="inline-flex items-center mt-1 px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                                <i class="fas fa-user-check mr-1"></i>Self-submitted — same person
                                            </span>
                                        @endif
                                    @endif
                                </td>
                                <td class="table-body-cell">
                                    <div class="table-field-main">{{ $record->approvalSummary() }}</div>
                                </td>
                                <td class="table-body-cell">
                                    <div class="table-field-main">{{ $record->branch->name ?? '—' }}</div>
                                    @if($record->division)
                                        <div class="table-field-sub">{{ $record->division->name }}</div>
                                    @endif
                                </td>
                                <td class="table-body-cell">
                                    <div class="table-field-main">{{ $record->submittedByUser?->full_name ?? 'N/A' }}</div>
                                </td>
                                <td class="table-body-cell">
                                    <div class="table-field-main"><x-time-ago :date="$record->created_at" :today="true" /></div>
                                </td>
                                <td class="table-body-cell-nowrap">
                                    <div class="flex items-center gap-3">
                                        <x-approval-actions :record="$record" :route-name="$routeName" />
                                        <a href="{{ route($routeName . '.review', $record->id) }}"
                                           class="text-sm text-blue-600 hover:text-blue-800 whitespace-nowrap">
                                            View details<i class="fa-solid fa-up-right-from-square ml-1 text-xs"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Mobile cards --}}
                <div class="lg:hidden divide-y divide-gray-200">
                    @foreach($records as $record)
                        @php $cardSelfDirected = $record->isSelfDirected; @endphp
                        <div class="p-4 {{ $cardSelfDirected ? 'bg-yellow-50' : '' }}">
                            <div class="flex items-center justify-between mb-2">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">{{ $record->user?->full_name ?? 'N/A' }}</div>
                                    <div class="text-xs text-gray-500">{{ $record->user?->user_id_reference_short }}</div>
                                </div>
                                <x-approval-status-badge status="pending" />
                            </div>
                            @if($cardSelfDirected)
                                <span class="inline-flex items-center mb-2 px-2 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    <i class="fas fa-user-check mr-1"></i>Self-submitted — same person
                                </span>
                            @endif
                            <div class="text-sm text-gray-700 mb-1">{{ $record->approvalSummary() }}</div>
                            <div class="text-xs text-gray-500 mb-3">
                                {{ $record->branch->name ?? '—' }}@if($record->division) – {{ $record->division->name }}@endif
                                · by {{ $record->submittedByUser?->full_name ?? 'N/A' }}
                            </div>
                            <div class="flex items-center justify-between">
                                <x-approval-actions :record="$record" :route-name="$routeName" />
                                <a href="{{ route($routeName . '.review', $record->id) }}" class="text-sm text-blue-600 hover:text-blue-800">Details</a>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="table-pagination">
                    {{ $records->links() }}
                </div>
            @else
                <div class="table-empty-state">
                    <i class="fas fa-clipboard-check text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">Nothing awaiting your approval</h3>
                    <p class="text-gray-500">Pending records you are eligible to review will appear here.</p>
                </div>
            @endif
        </div>{{-- /.table-container --}}
        </div>{{-- /x-data bulk wrapper --}}
    </div>
</x-layouts.admin>
