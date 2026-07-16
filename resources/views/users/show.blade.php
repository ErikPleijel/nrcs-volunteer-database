<x-layouts.admin :title="'User: ' . ($user->full_name ?? 'Unknown User')">

    <x-slot name="pageHeader">
        <i class="fas fa-user mr-3 mb-4"></i>Personal Details
    </x-slot>



    @can('edit_user')
        <x-slot name="button1">
            <a href="{{ route('users.edit', $user) }}"
               class="btn-edit">
                <i class="fas fa-edit mr-1"></i>Edit Person
            </a>
        </x-slot>
    @endcan

    @if($user->isArchived())
        <div class="max-w-4xl mx-auto mb-6">
            <div class="flex items-center justify-center gap-3 bg-red-50 border-2 border-red-300 rounded-lg py-4 px-6">
                <i class="fas fa-box-archive text-red-500 text-3xl"></i>
                <span class="text-3xl font-bold tracking-wide text-red-700">ARCHIVED</span>
            </div>
        </div>
    @endif

    <div class="w-full px-4 py-6">

        @if($user->isUnassignedGhost())
            <div class="max-w-4xl mx-auto mb-6">
                <div class="bg-amber-50 border-2 border-amber-300 rounded-lg py-4 px-6 text-center">
                    <div class="flex items-center justify-center gap-3">
                        <i class="fas fa-triangle-exclamation text-amber-500 text-2xl"></i>
                        <span class="text-lg text-amber-800">
                            This volunteer is currently unassigned to a Red Cross Unit.
                            <strong class="font-bold">Please ensure they are assigned to a new unit as soon as possible.</strong>
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-amber-800">
                        Moving to another branch? They can update their branch on My Profile, then the new branch assigns a unit — or National HQ can move them directly.
                    </p>
                    <p class="mt-1 text-sm text-amber-800">
                        Prefer they become a paying member instead? Make payment for them.
                    </p>
                </div>
            </div>
        @endif

        {{-- Detail table --}}
        <div class="bg-white rounded-lg shadow p-6 mt-6">
        <div class="grid grid-cols-1 lg:grid-cols-2 lg:gap-12 xl:gap-16">

        {{-- LEFT COLUMN --}}
        <div>

            {{-- IDENTITY --}}
            <div class="flex items-center mt-0 mb-2">
                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-id-badge text-gray-600 text-lg"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-900">IDENTITY</h2>
            </div>
            <table class="detail-table">
                <tbody>

                    {{-- Full Name --}}
                    <tr>
                        <td>Full Name</td>
                        <td class="text-xl font-medium">
                            @php
                                $nameParts = array_filter([
                                    $user->first_name,
                                    $user->middle_name ? '(' . $user->middle_name . ')' : null,
                                    $user->last_name,
                                ]);
                                $displayName = implode(' ', $nameParts) ?: 'Not provided';
                            @endphp
                            <span class="inline-flex items-center gap-2">
                                {{ $displayName }}
                                @if(auth()->id() === $user->id)
                                    <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded-full">You</span>
                                @endif
                            </span>
                        </td>
                    </tr>

                    {{-- DB Number --}}
                    <tr>
                        <td>DB Number</td>
                        <td>
                            <span class="db-code">{{ $user->user_id_reference_short }}</span>
                        </td>
                    </tr>

                    {{-- Profile Photo --}}
                    <tr>
                        <td>Profile Photo</td>
                        <td>
                            <div class="w-28 h-36 rounded-xl overflow-hidden flex items-center justify-center border-4 border-white shadow-lg
                                @if(($user->gender ?? 'male') === 'female') bg-gradient-to-br from-pink-400 to-purple-500
                                @else bg-gradient-to-br from-blue-400 to-blue-600 @endif">
                                @if($user->picture)
                                    <img src="{{ $user->profile_photo_url }}" alt="Profile Photo" class="w-full h-full object-cover">
                                @else
                                    <i class="fas fa-user text-4xl text-white"></i>
                                @endif
                            </div>
                            @if(!$user->picture)
                                <p class="text-sm text-gray-400 mt-1">No photo uploaded</p>
                            @endif
                        </td>
                    </tr>

                    {{-- Gender --}}
                    <tr>
                        <td>Gender</td>
                        <td>
                            @if($user->gender)
                                <span class="flex items-center gap-1">
                                    {{ ucfirst($user->gender) }}
                                    @if($user->gender === 'female')
                                        <i class="fas fa-venus text-pink-500"></i>
                                    @elseif($user->gender === 'male')
                                        <i class="fas fa-mars text-blue-500"></i>
                                    @endif
                                </span>
                            @else
                                Not provided
                            @endif
                        </td>
                    </tr>

                    {{-- Age --}}
                    <tr>
                        <td>Age</td>
                        <td>
                            @if($user->birth_year)
                                {{ date('Y') - $user->birth_year }} years
                                <div class="text-sm text-gray-400 mt-0.5">({{ $user->birth_year }})</div>
                            @else
                                Not provided
                            @endif
                        </td>
                    </tr>

                    {{-- Marital Status --}}
                    <tr>
                        <td>Marital Status</td>
                        <td>{{ $user->marital_status ? ucfirst($user->marital_status) : 'Not provided' }}</td>
                    </tr>

                    {{-- National ID --}}
                    <tr>
                        <td>National ID</td>
                        <td>{{ $user->national_id_number ?? 'Not provided' }}</td>
                    </tr>

                    {{-- Signature --}}
                    <tr>
                        <td>Signature</td>
                        <td>
                            @if($user->hasSignature())
                                <div class="w-36 h-24 overflow-hidden flex items-center justify-center border border-gray-300 bg-white shadow">
                                    <img src="{{ $user->getSignatureUrlAttribute() }}" alt="User Signature"
                                         class="w-full h-full object-contain">
                                </div>
                            @else
                                <div class="w-36 h-24 flex items-center justify-center border border-gray-300 bg-gray-50 text-gray-400 text-xs text-center p-2">
                                    No signature uploaded
                                </div>
                            @endif
                        </td>
                    </tr>

                </tbody>
            </table>

            {{-- CONTACT --}}
            <div class="flex items-center mt-6 mb-2">
                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-phone text-gray-600 text-lg"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-900">CONTACT</h2>
            </div>
            <table class="detail-table">
                <tbody>

                    {{-- Contact --}}
                    <tr>
                        <td>Contact</td>
                        <td>
                            <div>{{ $user->email ?? 'No email' }}</div>
                            @if($user->email && !$user->email_verified_at)
                                <div class="text-sm text-orange-500 mt-0.5">
                                    <i class="fas fa-exclamation-circle mr-1"></i>Not verified
                                </div>
                            @endif
                            <div>{{ $user->primary_phone ?? 'No phone' }}</div>
                            @if($user->telephone2)
                                <div>{{ $user->telephone2 }}</div>
                            @endif

                        </td>
                    </tr>

                    {{-- Residential Address --}}
                    <tr>
                        <td>Residential Address</td>
                        <td>{{ $user->residential_address ?? 'Not provided' }}</td>
                    </tr>

                </tbody>
            </table>

            {{-- PROFESSIONAL --}}
            <div class="flex items-center mt-6 mb-2">
                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-briefcase text-gray-600 text-lg"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-900">PROFESSIONAL</h2>
            </div>
            <table class="detail-table">
                <tbody>

                    {{-- Profession --}}
                    <tr>
                        <td>Profession</td>
                        <td>{{ $user->occupation ?? 'Not provided' }}</td>
                    </tr>

                    {{-- Education/Training --}}
                    <tr>
                        <td>Education/Training</td>
                        <td>{{ $user->disciplin ?? 'Not provided' }}</td>
                    </tr>

                    {{-- Organization --}}
                    <tr>
                        <td>Organization</td>
                        <td>{{ $user->organisation ?? 'Not provided' }}</td>
                    </tr>

                    {{-- Personal Info --}}
                    <tr>
                        <td>Personal Info</td>
                        <td class="whitespace-pre-line">{{ $user->personal_info ?? 'Not provided' }}</td>
                    </tr>

                </tbody>
            </table>

        </div>{{-- end left column --}}

        {{-- RIGHT COLUMN --}}
        <div>

            {{-- RED CROSS AFFILIATION --}}
            <div class="flex items-center mt-6 mb-2">
                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-hand-holding-heart text-gray-600 text-lg"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-900">RED CROSS AFFILIATION</h2>
            </div>
            <table class="detail-table">
                <tbody>

                    {{-- Branch --}}
                    <tr>
                        <td>Branch</td>
                        <td>{{ $user->branch->name ?? 'Not provided' }}</td>
                    </tr>

                    {{-- Division --}}
                    <tr>
                        <td>Division</td>
                        <td>{{ $user->division->name ?? 'Not provided' }}</td>
                    </tr>

                    {{-- Role --}}
                    <tr>
                        <td>Role</td>
                        <td><x-user-membership-status-badge :user="$user"/></td>
                    </tr>

                    <tr>
                        <td>Contribution Preference</td>
                        <td>
                            @if($user->can_contribute_volunteering)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <i class="fas fa-hands-helping mr-1"></i>Volunteer Services
                                </span>
                            @elseif($user->can_contribute_member)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    <i class="fas fa-id-card mr-1"></i>Active Membership
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    <i class="fas fa-minus mr-1"></i>Not specified
                                </span>
                            @endif
                        </td>
                    </tr>

                    {{-- RC Unit --}}
                    <tr>
                        <td>RC Unit</td>
                        <td>
                            @if($user->redCrossUnit)
                                @php
                                    $rcu = $user->redCrossUnit;
                                    if ($rcu->team_leader_user_id === $user->id) {
                                        $rcuRole = 'Team Leader';
                                        $rcuRoleClass = 'bg-red-100 text-red-800';
                                    } elseif ($rcu->assistant_team_leader_user_id === $user->id) {
                                        $rcuRole = 'Assistant Team Leader';
                                        $rcuRoleClass = 'bg-orange-100 text-orange-800';
                                    } else {
                                        $rcuRole = 'Member';
                                        $rcuRoleClass = 'bg-gray-100 text-gray-600';
                                    }
                                @endphp
                                <div class="flex items-center gap-2">
                                    <div class="rcunit-style flex flex-col">
                                        <span>{{ $rcu->name }}</span>
                                        <span class="text-xs text-center">{{ $rcu->users_count }} members</span>
                                        <span class="text-xs text-center font-medium text-gray-700 rounded px-1 mt-0.5" style="background-color: #DCB9B9;">{{ str_replace('Assistant', 'Asst.', $rcuRole) }}</span>
                                    </div>
                                    <a href="{{ route('red-cross-units.show', $rcu->id) }}"
                                       class="btn-view">
                                        View
                                    </a>
                                </div>
                            @else
                                <span class="text-sm italic text-gray-400">No RC Unit</span>
                            @endif
                        </td>
                    </tr>

                    {{-- Task Force --}}
                    <tr>
                        <td>Task Force</td>
                        <td>
                            @php $taskForces = $user->taskForces; @endphp
                            @forelse($taskForces as $taskForce)
                                @php
                                    if ($taskForce->team_leader_user_id === $user->id) {
                                        $tfRole = 'Team Leader';
                                        $tfRoleClass = 'bg-red-100 text-red-800';
                                    } elseif ($taskForce->assist_team_leader_user_id === $user->id) {
                                        $tfRole = 'Asst. Team Leader';
                                        $tfRoleClass = 'bg-orange-100 text-orange-800';
                                    } else {
                                        $tfRole = 'Member';
                                        $tfRoleClass = 'bg-gray-100 text-gray-600';
                                    }
                                @endphp
                                <div class="flex items-center gap-2 mb-1">
                                    <div class="taskforce-style w-fit flex flex-col">
                                        <span>{{ $taskForce->name }}</span>
                                        <span>{{ $taskForce->branch->code }}</span>
                                        <span class="text-xs text-center">{{ $taskForce->users->count() }} members</span>
                                        <span class="text-xs text-center font-medium text-gray-700 rounded px-1 mt-0.5" style="background-color: #cce3ff;">
                                            {{  $tfRole }}
                                        </span>
                                    </div>
                                    @if(auth()->user()->getAccessLevel() === 'national' || (auth()->user()->branch && $taskForce->branch->code === auth()->user()->branch->code))
                                        <a href="{{ route('task-forces.show', $taskForce) }}" class="btn-view w-fit">View</a>
                                    @endif
                                </div>
                            @empty
                                <span class="text-sm italic text-gray-400">No task-force</span>
                            @endforelse
                        </td>
                    </tr>

                    {{-- Organisation(s) --}}
                    <tr>
                        <td>Organisation(s)</td>
                        <td>
                            @forelse($user->organisations as $organisation)
                                <div class="flex items-center gap-2 mb-1">
                                    <div class="taskforce-style w-fit flex flex-col">
                                        <span>{{ $organisation->name }}</span>
                                        @if($organisation->pivot->is_primary_contact)
                                            <span class="text-xs font-semibold text-amber-700">Primary contact</span>
                                        @endif
                                    </div>
                                    <a href="{{ route('organisations.show', $organisation) }}" class="btn-view w-fit">View</a>
                                </div>
                            @empty
                                <span class="text-sm italic text-gray-400">No linked organisations</span>
                            @endforelse
                        </td>
                    </tr>



                </tbody>
            </table>

            {{-- ACTIVITY STATUS --}}
            <div class="flex items-center mt-6 mb-2">
                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-chart-line text-gray-600 text-lg"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-900">ACTIVITY STATUS</h2>
            </div>
            <table class="detail-table">
                <tbody>

                    {{-- Status --}}
                    <tr>
                        <td>Status</td>
                        <td><x-user-lifecycle-status-badge :user="$user"/></td>
                    </tr>

                    {{-- Last Activity --}}
                    <tr>
                        <td>Last Activity</td>
                        <td>
                            <x-time-ago :date="$user->last_activity_at" />
                        </td>
                    </tr>



                    {{-- Donations badge --}}
                    <tr>
                        <td>Donations</td>
                        <td><x-user-donation-status-badge :user="$user"/></td>
                    </tr>

                    {{-- Training badge --}}
                    <tr>
                        <td>Training</td>
                        <td><x-user-training-status-badge :user="$user"/></td>
                    </tr>

                    {{-- First Aid badge --}}
                    <tr>
                        <td>First Aid</td>
                        <td><x-user-first-aid-status-badge :user="$user"/></td>
                    </tr>

                    {{-- Last First Aid --}}
                    @php $lastFa = $user->latestFirstAidTraining(); @endphp
                    <tr>
                        <td>Last First Aid</td>
                        <td>
                            @if ($lastFa)
                                <span class="font-medium text-gray-900">{{ $lastFa->trainingType?->name }}</span>
                                <span class="text-gray-500">- <x-time-ago :date="$lastFa->training_date" /></span>
                            @else
                                <span class="text-gray-400">None recorded</span>
                            @endif
                        </td>
                    </tr>

                </tbody>
            </table>

            {{-- SYSTEM & REGISTRATION --}}
            <div class="flex items-center mt-6 mb-2">
                <div class="w-10 h-10 bg-gray-100 rounded-lg flex items-center justify-center mr-3">
                    <i class="fas fa-sliders text-gray-600 text-lg"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-900">SYSTEM & REGISTRATION</h2>
            </div>
            <table class="detail-table">
                <tbody>

                    {{-- Registration --}}
                    <tr>
                        <td>Registration</td>
                        <td>
                            {{ $user->is_form_registration ? 'Registered by Admin' : 'Self-registered' }}
                            <div class="text-sm text-gray-500 mt-0.5"><x-time-ago :date="$user->created_at"  /></div>

                            @if($user->formRegistrar)
                                <div class="text-sm text-gray-400 mt-0.5">
                                    Entered by: {{ $user->formRegistrar->full_name }} DB-{{ $user->formRegistrar->id }} {{ $user->created_at?->format('M d, Y') ?? 'N/A' }}
                                </div>
                            @endif
                        </td>
                    </tr>

                    {{-- Email --}}
                    <tr>
                        <td>Email</td>
                        <td>
                            @if($user->email)
                                @if($user->email_verified_at)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <i class="fas fa-envelope-circle-check mr-1"></i>Email verified
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        <i class="fas fa-envelope mr-1"></i>Email not verified
                                    </span>
                                @endif
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                    <i class="fas fa-envelope-open-text mr-1"></i>No email
                                </span>
                            @endif
                        </td>
                    </tr>

                    {{-- Password --}}
                    <tr>
                        <td>Password</td>
                        <td>
                            @if($user->password || $user->legacy_password_hash)
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    <i class="fas fa-lock mr-1"></i>Password set
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    <i class="fas fa-unlock mr-1"></i>No password
                                </span>
                            @endif
                        </td>
                    </tr>

                    {{-- Login --}}
                    <tr>
                        <td>Last Login</td>
                        <td>
                            @if($user->last_login_at)
                                <x-time-ago :date="$user->last_login_at" />
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-600">
                                    <i class="fas fa-ban mr-1"></i>Never logged in
                                </span>
                            @endif
                        </td>
                    </tr>



                    {{-- Digital Status --}}
                    <tr>
                        <td>Digital Status</td>
                        <td><x-user-digital-status-badge :user="$user"/></td>
                    </tr>

                    {{-- Role / Admin Status (only if admin) --}}
                    @if($user->can('manage-admin-panel'))
                        <tr>
                            <td>Role</td>
                            <td>
                                {{ $user->role_display_name }}
                                @php $directPerms = $user->getDirectPermissions(); @endphp
                                @if($directPerms->isNotEmpty())
                                    <div class="mt-1 text-sm text-gray-600">
                                        <span class="font-medium">Special permissions:</span>
                                        {{ $directPerms->pluck('name')->map(fn($n) => str_replace('_', ' ', $n))->join(', ') }}
                                    </div>
                                @endif
                            </td>
                        </tr>

                        <tr>
                            <td>Admin Status</td>
                            <td><x-user-admin-status-badge :user="$user"/></td>
                        </tr>

                        <tr>
                            <td>Last Admin Activity</td>
                            <td>
                                <x-time-ago :date="$user->last_admin_activity_at" />
                            </td>
                        </tr>
                    @endif

                </tbody>
            </table>

        </div>{{-- end right column --}}
        </div>{{-- end grid --}}

        </div>

        {{-- E. Data sections --}}
        <div class="max-w-4xl mx-auto">

        <!-- Membership Section -->
        <div class="bg-white rounded-lg shadow-lg p-6 mt-6">
            <div class="flex justify-between items-center">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-id-card text-blue-600 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">MEMBERSHIP</h2>
                </div>
                @can('add_payments')
                <a href="{{ route('membership-payments.create', ['user' => $user->id]) }}"
                   class="btn-primary" target="_blank">
                    <i class="fas fa-plus mr-2"></i>
                    Add Payment <i class="fa-solid fa-up-right-from-square ml-1"></i>
                </a>
                @endcan
            </div>

            <!-- Current Valid Membership Status -->
            <div class="mt-4 mb-6 p-4 border-2 border-dashed border-gray-200 rounded-lg bg-gray-50">
                <h3 class="text-lg font-semibold text-gray-900 mb-3">Current Membership Status</h3>

                @if($currentMembership)
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm text-gray-600">Current Plan:</p>
                            <p class="font-medium text-gray-900">{{ $currentMembership['membership_type'] }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Amount Paid:</p>
                            <p class="font-medium text-gray-900">{{ $currentMembership['formatted_amount'] }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">Valid Until:</p>
                            <p class="font-medium text-green-600">{{ $currentMembership['expiry_date'] }}</p>
                        </div>
                        <div>
                                <span
                                    class="bg-green-100 text-green-800 px-3 py-2 rounded-full text-sm font-medium">
                                    <i class="fas fa-check-circle mr-1"></i>Active
                                </span>
                        </div>
                    </div>
                @else
                    <div class="flex items-center justify-center py-4">
                        <div class="text-center">
                            <i class="fas fa-exclamation-triangle text-orange-500 text-2xl mb-2"></i>
                            <p class="text-orange-700 font-medium">No Active Membership</p>
                            <p class="text-sm text-gray-600 mt-1">This user has no active membership.</p>
                        </div>
                    </div>
                @endif
            </div>


            <!-- Payment History with Scrollable Container -->
            @if($showingLimitMessage)
                <div class="mb-2 p-2 bg-blue-50 border border-blue-200 rounded text-center">
                        <span class="text-blue-800 text-xs font-medium">
                            <i class="fas fa-info-circle mr-1"></i>Showing recent payments - scroll to view more
                        </span>
                </div>
            @endif

            <div class="bg-gray-50 rounded-lg p-3">
                <h4 class="text-sm font-semibold text-gray-500 mb-2">Payment history</h4>
                <div class="overflow-x-auto">
                    <div class="@if($showingLimitMessage) max-h-64 overflow-y-auto @endif">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-gray-50">
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-1 text-xs text-gray-500 bg-gray-50">Payment date</th>
                                <th class="text-left py-1 text-xs text-gray-500 bg-gray-50">Membership Type</th>
                                <th class="text-left py-1 text-xs text-gray-500 bg-gray-50">Amount</th>
                                <th class="text-left py-1 text-xs text-gray-500 bg-gray-50">Status</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($membershipPayments as $payment)
                                <tr class="border-b border-gray-100">
                                    <td class="py-1 text-xs text-gray-500">{{ $payment['payment_date'] }}</td>
                                    <td class="py-1 text-xs text-gray-500">{{ $payment['membership_type'] }}</td>
                                    <td class="py-1 text-xs text-gray-500">{{ $payment['formatted_amount'] }}</td>
                                    <td class="py-1">
                                                <span
                                                    class="{{ $payment['status']['class'] }} px-2 py-1 rounded-full text-xs">
                                                    {{ $payment['status']['text'] }}
                                                </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-gray-500 italic">
                                        No membership payments found
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

                @can('print_certificates')
                <div class="mt-3">
                    <a
                        @if($membershipPayments->count() === 0)
                            href="#"
                            class="btn-certificates opacity-40 pointer-events-none"
                            aria-disabled="true"
                            tabindex="-1"
                        @else
                            href="{{ route('certificates.index', [
                            'certificate_type' => 'membership',
                            'search' => $user->id,
                            'branch_id' => '',
                            'training_type_id' => '',
                        ]) }}"
                            class="btn-certificates"
                        @endif
                    >
                        <i class="fas fa-certificate"></i>
                        Print membership certificate
                    </a>
                </div>
                @endcan

        </div>

        <!-- Volunteering Section -->
        <div class="bg-white rounded-lg shadow-lg p-6 mt-6">
            <div class="flex justify-between items-center mb-4">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-hands-helping text-orange-600 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">VOLUNTEERING</h2>
                </div>
                @can('add_volunteering')
                <a href="{{ route('activities.create', ['user' => $user->id]) }}"
                   class="btn-primary" target="_blank">
                    <i class="fas fa-plus mr-2"></i>
                    Add Volunteering <i class="fa-solid fa-up-right-from-square ml-1"></i>
                </a>
                @endcan
            </div>
            <p class="text-gray-600 text-sm mb-4">
                This section displays the user's volunteering activities and hours.
            </p>

            <!-- Volunteering History with Scrollable Container -->
            @if($activitiesLimitMessage)
                <div class="mb-2 p-2 bg-orange-50 border border-orange-200 rounded text-center">
                        <span class="text-orange-800 text-xs font-medium">
                            <i class="fas fa-info-circle mr-1"></i>Showing recent activities - scroll to view more
                        </span>
                </div>
            @endif

            <div class="overflow-x-auto">
                <div class="@if($activitiesLimitMessage) max-h-64 overflow-y-auto @endif">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-white">
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-2 text-gray-600 bg-white">Date</th>
                            <th class="text-left py-2 text-gray-600 bg-white">Activity</th>
                            <th class="text-left py-2 text-gray-600 bg-white">Hours</th>
                            <th class="text-left py-2 text-gray-600 bg-white">Unit</th>
                            <th class="text-left py-2 text-gray-600 bg-white">Unit type</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($activities as $activity)
                            <tr class="border-b border-gray-100">
                                <td class="py-2">{{ $activity['date'] }}</td>
                                <td class="py-2">
                                    <div class="flex items-center">

                                        {{ $activity['activity'] }}
                                    </div>
                                    @if($activity['reference'])
                                        <div class="text-xs text-gray-500 mt-1">
                                            Ref: {{ $activity['reference'] }}
                                        </div>
                                    @endif
                                </td>
                                <td class="py-2 font-medium">{{ $activity['hours_display'] }}</td>
                                <td class="py-2 break-words whitespace-normal">
                                    @if($activity['unit'] && $activity['unit'] !== 'Unit not specified')
                                        <span class="table-field-main">
                                                    {{ $activity['unit'] }}
                                                </span>
                                    @else
                                        <span class="text-gray-400 text-xs">—</span>
                                    @endif
                                </td>
                                <td class="py-2">
                                    @if($activity['unit_type'])
                                        <span class="table-field-main">
                                            {{ ucfirst(str_replace('_', ' ', $activity['unit_type'])) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400 text-xs">—</span>
                                    @endif
                                </td>

                                <td class="table-body-cell-no-wrap">
                                    <a href="{{ route('activities.show', $activity['id']) }}" target="_blank"
                                       class="btn-primary text-xs !py-1">
                                        View<i class="fa-solid fa-up-right-from-square ml-1"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-center text-gray-500 italic">
                                    No volunteering activities found
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Volunteering Summary -->
            @if($activities->count() > 0)
                @php
                    $totalHours = $activities->sum('hours');
                    $redCrossUnitActivities = $activities->where('unit', '!=', 'Unit not specified')->count();
                    $generalActivities = $activities->count() - $redCrossUnitActivities;
                @endphp
                <div class="mt-4 p-3 bg-orange-50 border border-orange-200 rounded-lg">
                    <div class="flex items-center justify-between text-sm">
                        <div class="text-orange-800">
                            <i class="fas fa-clock mr-1"></i>
                            <strong>Total:</strong> {{ $totalHours }} {{ $totalHours == 1 ? 'hour' : 'hours' }}
                        </div>
                        <div class="text-orange-600">
                            <p> {{ $activities->count() }} activities logged</p>
                        </div>
                    </div>
                </div>
            @endif

                @can('print_certificates')
                <div class="mt-3">
                    <a
                        @if($activities->count() === 0)
                            href="#"
                            class="btn-certificates opacity-40 pointer-events-none"
                            aria-disabled="true"
                            tabindex="-1"
                        @else
                            href="{{ route('certificates.index', [
                            'certificate_type' => 'volunteering',
                            'search' => $user->id,
                            'branch_id' => '',
                            'training_type_id' => '',
                        ]) }}"
                            class="btn-certificates"
                        @endif
                    >
                        <i class="fas fa-certificate"></i>
                        Print volunteering certificate
                    </a>
                </div>
                @endcan

        </div>

        <!-- Training Section -->
        <div class="bg-white rounded-lg shadow-lg p-6 mt-6">
            <div class="flex justify-between items-center mb-4">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-graduation-cap text-purple-600 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">TRAINING</h2>
                </div>
                @can('add_trainings')
                <a href="{{ route('trainings.create', ['user' => $user->id]) }}"
                   class="btn-primary" target="_blank">
                    <i class="fas fa-plus mr-2"></i>
                    Add Training <i class="fa-solid fa-up-right-from-square ml-1"></i>
                </a>
                @endcan
            </div>
            <p class="text-gray-600 text-sm mb-4">
                Training courses completed by the user.
            </p>

            <!-- Training History with Scrollable Container -->
            @if($trainingsLimitMessage)
                <div class="mb-2 p-2 bg-purple-50 border border-purple-200 rounded text-center">
                        <span class="text-purple-800 text-xs font-medium">
                            <i class="fas fa-info-circle mr-1"></i>Showing recent training - scroll to view more
                        </span>
                </div>
            @endif

            <div class="overflow-x-auto">
                <div class="@if($trainingsLimitMessage) max-h-64 overflow-y-auto @endif">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-white">
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-2 text-gray-600 bg-white">Date</th>
                            <th class="text-left py-2 text-gray-600 bg-white">Training</th>
                            <th class="text-left py-2 text-gray-600 bg-white">Duration</th>
                            <th class="text-left py-2 text-gray-600 bg-white">Status</th>
                            <th class="text-left py-2 text-gray-600 bg-white">Print at</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($trainings as $training)
                            <tr class="training-row border-b border-gray-100 cursor-pointer hover:bg-purple-50 transition-colors"
                                data-training-type-id="{{ $training['training_type_id'] }}">
                                <td class="py-2">{{ $training['date'] }}</td>
                                <td class="py-2">
                                    <div class="flex items-center">
                                        <i class="fas fa-certificate text-purple-600 mr-2"></i>
                                        {{ $training['activity'] }}
                                    </div>
                                </td>
                                <td class="py-2">
                                    {{ $training['duration'] }} day{{ $training['duration'] != 1 ? 's' : '' }}
                                </td>
                                <td class="py-2">
                                            <span
                                                class="{{ $training['status']['class'] }} px-2 py-1 rounded-full text-xs">
                                                {{ $training['status']['text'] }}
                                            </span>
                                </td>
                                <td class="py-2 text-xs text-gray-600">
                                    {{ $training['certificate_hq_only'] ? 'HQ' : 'Branch' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-gray-500 italic">
                                    No training records found
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

                @can('print_certificates')
                <div class="mt-3">
                    <p class="text-base text-gray-500 mb-2 italic">
                        Select a training row above to enable printing.
                    </p>
                    <div class="flex gap-3">
                        <a
                            id="btn-print-attendance"
                            href="#"
                            data-base-url="{{ route('certificates.index', ['certificate_type' => 'training_attendance', 'search' => $user->id, 'branch_id' => '', 'training_type_id' => '']) }}"
                            class="btn-certificates opacity-40 pointer-events-none"
                            aria-disabled="true"
                        >
                            <i class="fas fa-certificate"></i>
                            Print attendance certificate
                        </a>

                        <a
                            id="btn-print-competence"
                            href="#"
                            data-base-url="{{ route('certificates.index', ['certificate_type' => 'training_competence', 'search' => $user->id, 'branch_id' => '', 'training_type_id' => '']) }}"
                            class="btn-certificates opacity-40 pointer-events-none"
                            aria-disabled="true"
                        >
                            <i class="fas fa-certificate"></i>
                            Print competence certificate
                        </a>
                    </div>
                </div>
                <script>
                    (function () {
                        const rows = document.querySelectorAll('.training-row');
                        const btnAttendance = document.getElementById('btn-print-attendance');
                        const btnCompetence = document.getElementById('btn-print-competence');

                        if (!btnAttendance || !btnCompetence) return;

                        rows.forEach(function (row) {
                            row.addEventListener('click', function () {
                                rows.forEach(r => r.classList.remove('bg-purple-100', 'ring-1', 'ring-purple-400'));
                                row.classList.add('bg-purple-100', 'ring-1', 'ring-purple-400');

                                const typeId = row.dataset.trainingTypeId;

                                [btnAttendance, btnCompetence].forEach(function (btn) {
                                    const base = btn.dataset.baseUrl;
                                    btn.href = base + '&training_type_id=' + encodeURIComponent(typeId);
                                    btn.classList.remove('opacity-40', 'pointer-events-none');
                                    btn.removeAttribute('aria-disabled');
                                });
                            });
                        });
                    })();
                </script>
                @endcan

        </div>

        <!-- Donations Section -->
        <div class="bg-white rounded-lg shadow-lg p-6 mt-6">
            <div class="flex justify-between items-center mb-4">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-hand-holding-heart text-green-600 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">DONATIONS</h2>
                </div>
                @can('add_donations')
                <a href="{{ route('donations.create', ['user' => $user->id]) }}" class="btn-primary" target="_blank">
                    <i class="fas fa-plus mr-2"></i>
                    Add Donation <i class="fa-solid fa-up-right-from-square ml-1"></i>
                </a>
                @endcan
            </div>
            <p class="text-gray-600 text-sm mb-4">
                Records of financial and in-kind contributions made by this user.
            </p>

            <!-- Donation History with Scrollable Container -->
            @if($donationsLimitMessage)
                <div class="mb-2 p-2 bg-green-50 border border-green-200 rounded text-center">
                        <span class="text-green-800 text-xs font-medium">
                            <i class="fas fa-info-circle mr-1"></i>Showing recent donations - scroll to view more
                        </span>
                </div>
            @endif

            <div class="overflow-x-auto">
                <div class="@if($donationsLimitMessage) max-h-64 overflow-y-auto @endif">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-white">
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-2 text-gray-600 bg-white">Donation date</th>
                            <th class="text-left py-2 text-gray-600 bg-white">Donated item</th>
                            <th class="text-left py-2 text-gray-600 bg-white">Amount</th>
                            <th class="text-left py-2 text-gray-600 bg-white">Type</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($donations as $donation)
                            <tr class="border-b border-gray-100">
                                <td class="py-2">{{ $donation['date'] }}</td>
                                <td class="py-2">
                                    <div class="flex items-center">
                                        @if($donation['type'] === 'in-kind')
                                            <i class="fas fa-box text-green-600 mr-2"></i>
                                        @else
                                            <i class="fas fa-money-bill text-green-600 mr-2"></i>
                                        @endif
                                        {{ $donation['item'] }}
                                    </div>
                                </td>
                                <td class="py-2">{{ $donation['amount'] }}</td>
                                <td class="py-2">
                                            <span class="px-2 py-1 rounded-full text-xs
                                                @if($donation['type'] === 'in-kind')
                                                    bg-blue-100 text-blue-800
                                                @else
                                                    bg-green-100 text-green-800
                                                @endif">
                                                {{ $donation['type'] === 'in-kind' ? 'In-Kind' : 'Cash' }}
                                            </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-center text-gray-500 italic">
                                    No donations found
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

                @can('print_certificates')
                <div class="mt-3">
                    <a
                        @if($donations->count() === 0)
                            href="#"
                            class="btn-certificates opacity-40 pointer-events-none"
                            aria-disabled="true"
                            tabindex="-1"
                        @else
                            href="{{ route('certificates.index', [
                            'certificate_type' => 'donation',
                            'search' => $user->id,
                            'branch_id' => '',
                            'training_type_id' => '',
                        ]) }}"
                            class="btn-certificates"
                        @endif
                    >
                        <i class="fas fa-certificate"></i>
                        Print donation certificate
                    </a>
                </div>
                @endcan

        </div>

        <!-- Messages Sent Section -->
        <div class="bg-white rounded-lg shadow-lg p-6 mt-6">
            <div class="flex items-center mb-4">
                <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mr-4">
                    <i class="fas fa-envelope text-indigo-600 text-xl"></i>
                </div>
                <h2 class="text-xl font-bold text-gray-900">MESSAGES SENT</h2>
            </div>

            @if($campaignRecipientsLimitMessage)
                <div class="mb-2 p-2 bg-indigo-50 border border-indigo-200 rounded text-center">
                    <span class="text-indigo-800 text-xs font-medium">
                        <i class="fas fa-info-circle mr-1"></i>Showing recent messages - scroll to view more
                    </span>
                </div>
            @endif

            <div class="overflow-x-auto">
                <div class="@if($campaignRecipientsLimitMessage) max-h-64 overflow-y-auto @endif">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-white">
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-2 text-gray-600 bg-white">Sent at</th>
                            <th class="text-left py-2 text-gray-600 bg-white">Campaign</th>
                            <th class="text-left py-2 text-gray-600 bg-white">Channel</th>
                            <th class="text-left py-2 text-gray-600 bg-white">Status</th>
                            <th class="text-left py-2 text-gray-600 bg-white">Contact used</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($campaignRecipients as $msg)
                            @php
                                $channelClass = match($msg['channel']) {
                                    'email'              => 'bg-blue-100 text-blue-800',
                                    'sms'                => 'bg-green-100 text-green-800',
                                    'both'               => 'bg-purple-100 text-purple-800',
                                    'email_fallback_sms' => 'bg-indigo-100 text-indigo-800',
                                    default              => 'bg-gray-100 text-gray-700',
                                };
                                $channelLabel = match($msg['channel']) {
                                    'email'              => 'Email',
                                    'sms'                => 'SMS',
                                    'both'               => 'Both',
                                    'email_fallback_sms' => 'Email / SMS',
                                    default              => $msg['channel'],
                                };
                                $statusClass = match(strtolower((string) $msg['status'])) {
                                    'sent'    => 'bg-green-100 text-green-800',
                                    'failed'  => 'bg-red-100 text-red-800',
                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                    'bounced' => 'bg-gray-100 text-gray-700',
                                    default   => 'bg-gray-100 text-gray-700',
                                };
                            @endphp
                            <tr class="border-b border-gray-100">
                                <td class="py-2 whitespace-nowrap">
                                    @if($msg['sent_at'])
                                        {{ $msg['sent_at'] }}
                                    @elseif(strtolower((string) $msg['status']) === 'pending')
                                        <span class="text-yellow-600 text-xs font-medium">Pending</span>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="py-2">{{ $msg['campaign_title'] }}</td>
                                <td class="py-2">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $channelClass }}">
                                        {{ $channelLabel }}
                                    </span>
                                </td>
                                <td class="py-2">
                                    <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $statusClass }}">
                                        {{ ucfirst((string) $msg['status']) }}
                                    </span>
                                </td>
                                <td class="py-2 text-xs text-gray-600 break-all">
                                    {{ $msg['email'] ?? $msg['phone'] ?? '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-4 text-center text-gray-500 italic">
                                    No messages sent yet
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-lg p-6 mt-6">
            {{-- Printed Certificates --}}
            <div class="bg-white rounded-lg shadow-lg p-6 mt-8">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-print text-indigo-600 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">PRINTED CERTIFICATES</h2>
                </div>

                @if($certificatePrintsLimitMessage)
                    <div class="mb-2 p-2 bg-indigo-50 border border-indigo-200 rounded text-center">
                        <span class="text-indigo-800 text-xs font-medium">
                            <i class="fas fa-info-circle mr-1"></i>Showing recent printed certificates - scroll to view more
                        </span>
                    </div>
                @endif

                <div class="overflow-x-auto">
                    <div class="@if($certificatePrintsLimitMessage) max-h-64 overflow-y-auto @endif">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-white">
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-2 text-gray-600 bg-white">Printed at</th>
                                <th class="text-left py-2 text-gray-600 bg-white">Training</th>
                                <th class="text-left py-2 text-gray-600 bg-white">Certificate type</th>
                                <th class="text-left py-2 text-gray-600 bg-white">Printed by</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($certificatePrints as $print)
                                <tr class="border-b border-gray-100">
                                    <td class="py-2">{{ $print['printed_at'] }}</td>
                                    <td class="py-2">
                                        <div class="flex items-center">
                                            <i class="fas fa-certificate text-indigo-600 mr-2"></i>
                                            {{ $print['training'] }}
                                        </div>
                                        @if(!empty($print['notes']))
                                            <div class="text-xs text-gray-500 mt-1">
                                                Notes: {{ $print['notes'] }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="py-2">{{ $print['certificate_type'] }}</td>
                                    <td class="py-2">{{ $print['printed_by'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="py-4 text-center text-gray-500 italic">
                                        No printed certificates found
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- Printed ID Cards --}}
            <div class="bg-white rounded-lg shadow-lg p-6 mt-8">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center mr-4">
                        <i class="fas fa-id-card text-emerald-600 text-xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900">PRINTED ID CARDS</h2>
                </div>

                @if($idCardPrintsLimitMessage)
                    <div class="mb-2 p-2 bg-emerald-50 border border-emerald-200 rounded text-center">
                    <span class="text-emerald-800 text-xs font-medium">
                        <i class="fas fa-info-circle mr-1"></i>Showing recent printed ID cards - scroll to view more
                    </span>
                    </div>
                @endif

                <div class="overflow-x-auto">
                    <div class="@if($idCardPrintsLimitMessage) max-h-64 overflow-y-auto @endif">
                        <table class="w-full text-sm">
                            <thead class="sticky top-0 bg-white">
                            <tr class="border-b border-gray-200">
                                <th class="text-left py-2 text-gray-600 bg-white">Printed at</th>
                                <th class="text-left py-2 text-gray-600 bg-white">Status</th>
                                <th class="text-left py-2 text-gray-600 bg-white">Validity</th>
                                <th class="text-left py-2 text-gray-600 bg-white">Expiry</th>
                                <th class="text-left py-2 text-gray-600 bg-white">Printed by</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($idCardPrints as $print)
                                <tr class="border-b border-gray-100">
                                    <td class="py-2">{{ $print['printed_at'] }}</td>

                                    <td class="py-2">
                                        @php
                                            $status = strtolower((string) ($print['status'] ?? ''));
                                            $statusClass = 'bg-gray-100 text-gray-800';

                                            if (in_array($status, ['printed', 'active', 'valid'], true)) {
                                                $statusClass = 'bg-green-100 text-green-800';
                                            } elseif (in_array($status, ['expired', 'invalid'], true)) {
                                                $statusClass = 'bg-red-100 text-red-800';
                                            } elseif (in_array($status, ['pending'], true)) {
                                                $statusClass = 'bg-yellow-100 text-yellow-800';
                                            }
                                        @endphp

                                        <span class="{{ $statusClass }} px-2 py-1 rounded-full text-xs">
                            {{ $print['status'] }}
                        </span>

                                        @if(!empty($print['notes']))
                                            <div class="text-xs text-gray-500 mt-1">
                                                Notes: {{ $print['notes'] }}
                                            </div>
                                        @endif
                                    </td>

                                    <td class="py-2">
                                        @if(!empty($print['validity_months']))
                                            {{ $print['validity_months'] }} month{{ (int)$print['validity_months'] !== 1 ? 's' : '' }}
                                        @else
                                            <span class="text-gray-400 text-xs">—</span>
                                        @endif
                                    </td>

                                    <td class="py-2">{{ $print['expiry_date'] }}</td>
                                    <td class="py-2">{{ $print['printed_by'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="py-4 text-center text-gray-500 italic">
                                        No printed ID cards found
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

                @can('view_idcards')
                <div class="mt-4">
                    <a
                        href="{{ route('id-cards.prepare-bulk-print', [
                                'search' => $user->id,
                                'branch_id' => '',
                                'expires_in_months' => '',
                            ]) }}"
                        class="btn-certificates"
                    >
                        <i class="fas fa-id-card"></i>
                        Print ID card
                    </a>
                </div>
                @endcan


        </div>

        </div>{{-- end data sections constrained width --}}

    </div>
</x-layouts.admin>
