<x-layouts.admin :title="'Branch: ' . $branch->name">

    <x-slot name="pageHeader">
        <i class="fas fa-sitemap mr-3 mb-6"></i> Branch Details
    </x-slot>



    @can('edit_branch_information')
        <x-slot name="button1">
            <a href="{{ route('branches.edit', $branch) }}" class="btn-edit">
                <i class="fas fa-edit mr-1"></i>Edit Branch
            </a>
        </x-slot>
    @endcan

    <div class="show-page-container space-y-6">

        <div class="bg-white rounded-lg shadow p-6">
            <table class="detail-table">
                <tbody>

                    <tr>
                        <td>Branch Name</td>
                        <td>
                            <span class="text-xl font-medium">{{ $branch->name }}</span>
                        </td>
                    </tr>

                    <tr>
                        <td>Branch Code</td>
                        <td>
                            <span class="db-code">{{ $branch->code }}</span>
                        </td>
                    </tr>

                    <tr>
                        <td>Zone</td>
                        <td>{{ $branch->zone ?? 'Not provided' }}</td>
                    </tr>

                    <tr>
                        <td>Total Divisions</td>
                        <td>{{ $branch->divisions->count() }}</td>
                    </tr>

                    <tr>
                        <td>Divisions</td>
                        <td>
                            @forelse($branch->divisions as $division)
                                <a href="{{ route('divisions.show', $division) }}"
                                   class="text-sm underline text-gray-700 hover:text-blue-600">{{ $division->name }}</a>@unless($loop->last)&nbsp;&nbsp;@endunless
                            @empty
                                <span class="text-gray-400 text-xs">No divisions</span>
                            @endforelse
                        </td>
                    </tr>

                    <tr>
                        <td>Physical Address</td>
                        <td>{{ $branch->physical_address ?: 'Not provided' }}</td>
                    </tr>

                    <tr>
                        <td>Postal Address</td>
                        <td>{{ $branch->postal_address ?: 'Not provided' }}</td>
                    </tr>

                    <tr>
                        <td>Projects</td>
                        <td>{{ is_null($branch->projects) ? 'Not provided' : $branch->projects }}</td>
                    </tr>

                    <tr>
                        <td>Telephone</td>
                        <td>{{ $branch->telephone ?: 'Not provided' }}</td>
                    </tr>

                    <tr>
                        <td>Email</td>
                        <td>
                            @if($branch->email)
                                <a href="mailto:{{ $branch->email }}" class="text-blue-600 hover:text-blue-800">
                                    {{ $branch->email }}
                                </a>
                            @else
                                Not provided
                            @endif
                        </td>
                    </tr>

                    @if($branch->hasCoordinates())
                        <tr>
                            <td>Location</td>
                            <td>Lat: {{ $branch->latitude }}, Long: {{ $branch->longitude }}</td>
                        </tr>
                    @endif

                </tbody>
            </table>
        </div>

        {{-- Contact persons (same style as profile.show) --}}
        @php
            $branchContacts = $branch->publicContacts();
        @endphp

        @if(!empty($branchContacts))
            <div class="bg-white rounded-lg shadow mb-6">
                <div class="px-6 py-4 bg-gray-50 border-b">
                    <h2 class="text-xl font-semibold text-gray-800 flex items-center">
                        <i class="fas fa-users mr-2 text-red-600"></i>
                        Contact Persons
                    </h2>
                </div>

                <div class="p-6 space-y-3 text-sm">
                    @foreach($branchContacts as $contact)
                        @php $contactUser = $contact['user']; @endphp
                        <div class="flex items-start gap-3">
                            {{-- Avatar --}}
                            @php
                                $grad = ($contactUser->gender ?? 'male') === 'female'
                                    ? 'from-pink-400 to-purple-500'
                                    : 'from-blue-400 to-blue-600';
                            @endphp
                            <div class="w-10 h-10 rounded-full overflow-hidden flex items-center justify-center border-2 border-white shadow bg-gradient-to-br {{ $grad }}">
                                @if($contactUser->picture)
                                    <img src="{{ route('photos.show', [$contactUser->id, 'profile', 'context' => 'branch_contact']) }}"
                                         alt="{{ $contactUser->full_name }}"
                                         class="w-full h-full object-cover">
                                @else
                                    <i class="fas fa-user text-white text-sm"></i>
                                @endif
                            </div>

                            {{-- Info --}}
                            <div class="flex-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-gray-900">
                                        {{ $contactUser->full_name ?? 'Unnamed contact' }}
                                    </span>
                                    @if(!empty($contact['position']))
                                        <span class="px-2 py-0.5 rounded-full text-xs bg-red-100 text-red-800">
                                            {{ $contact['position'] }}
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-1 space-y-0.5 text-gray-700">
                                    @if($contactUser->email)
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-envelope text-gray-400 text-xs"></i>
                                            <span class="break-all">{{ $contactUser->email }}</span>
                                        </div>
                                    @endif

                                    @if($contactUser->telephone1 || $contactUser->telephone2)
                                        <div class="flex items-center gap-2">
                                            <i class="fas fa-phone text-gray-400 text-xs"></i>
                                            <span>
                                                {{ $contactUser->telephone1 ?? $contactUser->telephone2 }}
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

    </div>
</x-layouts.admin>
