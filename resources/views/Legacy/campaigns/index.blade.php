<x-layouts.admin title="Messaging Campaigns">
    <x-slot name="pageHeader">
        <i class="fas fa-paper-plane mr-3"></i> Messaging Campaigns
    </x-slot>
    <x-slot name="subHeader">
        View and manage sent messages
    </x-slot>

    <div class="container mx-auto px-4 py-6">
        <div class="bg-white rounded-lg shadow-sm mb-6 border border-gray-200">
            <div class="p-6">
                <form method="GET" action="{{ route('messaging.campaigns.index') }}" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Search -->
                        <div class="md:col-span-2">
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-search mr-1 text-gray-400"></i>Search Campaigns
                            </label>
                            <input type="text"
                                   name="search"
                                   id="search"
                                   value="{{ request('search') }}"
                                   placeholder="Search by title, subject, or body"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors duration-200">
                        </div>

                        <!-- Channel Filter -->
                        <div>
                            <label for="channel" class="block text-sm font-medium text-gray-700 mb-2">Channel</label>
                            <select name="channel" id="channel"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Channels</option>
                                @foreach($availableChannels as $channel)
                                    <option value="{{ $channel }}" {{ request('channel') == $channel ? 'selected' : '' }}>
                                        {{ ucfirst($channel) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select name="status" id="status"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="">All Statuses</option>
                                @foreach($availableStatuses as $status)
                                    <option value="{{ $status }}" {{ request('status') == $status ? 'selected' : '' }}>
                                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Sort By -->
                        <div class="md:col-span-2">
                            <label for="sort_by" class="block text-sm font-medium text-gray-700 mb-2">Sort By</label>
                            <select name="sort_by" id="sort_by"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="created_at_desc" {{ request('sort_by', 'created_at_desc') == 'created_at_desc' ? 'selected' : '' }}>Newest First</option>
                                <option value="created_at_asc" {{ request('sort_by') == 'created_at_asc' ? 'selected' : '' }}>Oldest First</option>
                                <option value="title_asc" {{ request('sort_by') == 'title_asc' ? 'selected' : '' }}>Title (A-Z)</option>
                                <option value="title_desc" {{ request('sort_by') == 'title_desc' ? 'selected' : '' }}>Title (Z-A)</option>
                            </select>
                        </div>

                        <!-- Action Buttons -->
                        <div class="md:col-span-2 flex items-end gap-2">
                            <button type="submit"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg inline-flex items-center transition-colors duration-200">
                                <i class="fas fa-filter mr-2"></i>Apply Filters
                            </button>
                            <a href="{{ route('messaging.campaigns.index') }}"
                               class="font-medium px-6 py-2 rounded-lg shadow-sm transition-colors duration-200 bg-gray-300 text-gray-500 hover:bg-gray-400">
                                <i class="fas fa-times mr-2"></i>Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        @if($campaigns->count() > 0)
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Channel</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Audience</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Sent / Total / Failed</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created At</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($campaigns as $campaign)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                        {{ $campaign->title ?? 'N/A' }}
                                        @if($campaign->subject)
                                            <p class="text-xs text-gray-500">Subject: {{ $campaign->subject }}</p>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ $campaign->channel === 'email' ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800' }}">
                                            <i class="mr-1 fas fa-{{ $campaign->channel === 'email' ? 'envelope' : 'comment-dots' }}"></i> {{ ucfirst($campaign->channel) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ ucfirst(str_replace('_', ' ', $campaign->audience_type)) }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                            @if($campaign->status === 'sent') bg-green-100 text-green-800
                                            @elseif($campaign->status === 'failed' || $campaign->status === 'cancelled') bg-red-100 text-red-800
                                            @elseif($campaign->status === 'sending' || $campaign->status === 'queued') bg-yellow-100 text-yellow-800
                                            @else bg-gray-100 text-gray-800
                                            @endif">
                                            {{ ucfirst(str_replace('_', ' ', $campaign->status)) }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <span class="text-green-600 font-semibold">{{ $campaign->stats_sent }}</span> /
                                        <span class="font-semibold">{{ $campaign->stats_total }}</span> /
                                        <span class="text-red-600 font-semibold">{{ $campaign->stats_failed }}</span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        {{ $campaign->created_at->format('M d, Y H:i') }}
                                        @if($campaign->creator)
                                            <p class="text-xs text-gray-400">by {{ $campaign->creator->full_name }}</p>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        <a href="{{ route('messaging.campaigns.show', $campaign) }}" class="text-blue-600 hover:text-blue-900">View Details</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="px-6 py-3 bg-gray-50 border-t border-gray-200">
                    {{ $campaigns->appends(request()->query())->links() }}
                </div>
            </div>
        @else
            <div class="px-6 py-12 text-center bg-white rounded-lg shadow-sm border border-gray-200">
                <i class="fas fa-box-open text-4xl text-gray-400 mb-4"></i>
                <h3 class="text-lg font-medium text-gray-900 mb-2">No messaging campaigns found</h3>
                <p class="text-gray-500 mb-4">Try adjusting your search or filter criteria.</p>
            </div>
        @endif
    </div>
</x-layouts.admin>
