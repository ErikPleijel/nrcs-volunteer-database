<x-layouts.admin :title="'Campaign: ' . ($campaign->title ?? 'N/A')">
    <x-slot name="pageHeader">
        <i class="fas fa-paper-plane mr-3"></i> Campaign Details: {{ $campaign->title ?? 'N/A' }}
    </x-slot>
    <x-slot name="subHeader">
        Overview of sent messages and their recipients
    </x-slot>

    <div class="container mx-auto px-4 py-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <!-- Campaign Summary Card -->
            <div class="md:col-span-1 bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Campaign Information</h3>
                <dl class="space-y-2 text-sm text-gray-700">
                    <div>
                        <dt class="font-medium text-gray-500">Title:</dt>
                        <dd class="mt-0.5 text-gray-900">{{ $campaign->title ?? 'N/A' }}</dd>
                    </div>
                    @if($campaign->subject)
                        <div>
                            <dt class="font-medium text-gray-500">Subject:</dt>
                            <dd class="mt-0.5 text-gray-900">{{ $campaign->subject }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="font-medium text-gray-500">Channel:</dt>
                        <dd class="mt-0.5 text-gray-900">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $campaign->channel === 'email' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                <i class="mr-1 fas fa-{{ $campaign->channel === 'email' ? 'envelope' : 'comment-dots' }}"></i> {{ ucfirst($campaign->channel) }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Audience Type:</dt>
                        <dd class="mt-0.5 text-gray-900">{{ ucfirst(str_replace('_', ' ', $campaign->audience_type)) }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Current Status:</dt>
                        <dd class="mt-0.5 text-gray-900">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                @if($campaign->status === 'sent') bg-green-100 text-green-800
                                @elseif($campaign->status === 'failed' || $campaign->status === 'cancelled') bg-red-100 text-red-800
                                @elseif($campaign->status === 'sending' || $campaign->status === 'queued' || $campaign->status === 'partially_sent') bg-yellow-100 text-yellow-800
                                @else bg-gray-100 text-gray-800
                                @endif">
                                {{ ucfirst(str_replace('_', ' ', $campaign->status)) }}
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Created At:</dt>
                        <dd class="mt-0.5 text-gray-900">{{ $campaign->created_at->format('M d, Y H:i') }}</dd>
                    </div>
                    <div>
                        <dt class="font-medium text-gray-500">Created By:</dt>
                        <dd class="mt-0.5 text-gray-900">{{ $campaign->creator->full_name ?? 'N/A' }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Campaign Statistics Card -->
            <div class="md:col-span-2 bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Delivery Statistics</h3>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-center">
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-sm text-gray-500">Total Recipients</p>
                        <p class="text-2xl font-bold text-gray-900">{{ $campaign->stats_total }}</p>
                    </div>
                    <div class="p-4 bg-green-50 rounded-lg">
                        <p class="text-sm text-green-700">Successfully Sent</p>
                        <p class="text-2xl font-bold text-green-900">{{ $campaign->stats_sent }}</p>
                    </div>
                    <div class="p-4 bg-red-50 rounded-lg">
                        <p class="text-sm text-red-700">Failed to Send</p>
                        <p class="text-2xl font-bold text-red-900">{{ $campaign->stats_failed }}</p>
                    </div>
                </div>

                <h4 class="text-md font-semibold text-gray-800 mt-6 mb-2">Message Body</h4>
                <div class="bg-gray-50 p-4 rounded-lg text-sm text-gray-800 break-words max-h-60 overflow-y-auto">
                    {!! nl2br(e($campaign->body)) !!} {{-- Use nl2br and e to handle newlines and escape HTML --}}
                </div>

                @if($campaign->filter_json)
                    <h4 class="text-md font-semibold text-gray-800 mt-6 mb-2">Applied Filters</h4>
                    <div class="bg-gray-50 p-4 rounded-lg text-sm text-gray-800 max-h-40 overflow-y-auto">
                        <pre class="whitespace-pre-wrap">{{ json_encode($campaign->filter_json, JSON_PRETTY_PRINT) }}</pre>
                    </div>
                @endif
            </div>
        </div>

        <!-- Recipients List -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recipients ({{ $recipients->total() }})</h3>

            <form method="GET" action="{{ route('messaging.campaigns.show', $campaign) }}" class="space-y-4 mb-4">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search Recipient</label>
                        <input type="text" name="search" id="search" value="{{ request('search') }}"
                               placeholder="Search by name, email, phone, error"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                        <select name="status" id="status"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Statuses</option>
                            @foreach($availableRecipientStatuses as $status)
                                <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center">
                        <i class="fas fa-filter mr-2"></i>Apply Filter
                    </button>
                    <a href="{{ route('messaging.campaigns.show', $campaign) }}" class="font-medium px-6 py-2 rounded-lg shadow-sm bg-gray-300 text-gray-500 hover:bg-gray-400">
                        <i class="fas fa-times mr-2"></i>Clear
                    </a>
                </div>
            </form>

            @if($recipients->count() > 0)
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recipient</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sent At</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Error</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($recipients as $recipient)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        @if($recipient->recipient)
                                            <a href="{{ route('users.show', $recipient->recipient->id) }}" class="text-blue-600 hover:text-blue-900">
                                                {{ $recipient->recipient->full_name ?? 'N/A' }}
                                            </a>
                                            <p class="text-xs text-gray-500">{{ $recipient->recipient->user_id_reference ?? '' }}</p>
                                        @else
                                            N/A (Deleted User)
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $recipient->email ?? 'N/A' }} <br>
                                        {{ $recipient->phone ?? 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            @if($recipient->status === 'sent') bg-green-100 text-green-800
                                            @elseif($recipient->status === 'failed' || $recipient->status === 'bounced' || $recipient->status === 'undeliverable') bg-red-100 text-red-800
                                            @else bg-gray-100 text-gray-800
                                            @endif">
                                            {{ ucfirst($recipient->status) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $recipient->sent_at ? $recipient->sent_at->format('M d, Y H:i') : 'N/A' }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-red-700 max-w-xs truncate">
                                        {{ $recipient->last_error ?? 'N/A' }}
                                        @if($recipient->last_error)
                                            <i class="fas fa-exclamation-triangle text-red-500 ml-1" title="{{ $recipient->last_error }}"></i>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-3 bg-gray-50 border-t border-gray-200">
                    {{ $recipients->appends(request()->query())->links() }}
                </div>
            @else
                <div class="px-6 py-12 text-center">
                    <i class="fas fa-user-slash text-4xl text-gray-400 mb-4"></i>
                    <h3 class="text-lg font-medium text-gray-900 mb-2">No recipients found for this campaign</h3>
                    <p class="text-gray-500 mb-4">Try adjusting your search or filter criteria for recipients.</p>
                </div>
            @endif
        </div>
    </div>
</x-layouts.admin>
