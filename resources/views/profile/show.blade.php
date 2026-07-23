<x-layouts.app title="My NRCS Profile">
    <x-slot name="styles">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    </x-slot>

    <x-slot name="pageHeader">
        <h1 class="text-2xl font-bold text-gray-900">My Profile</h1>
    </x-slot>

    @auth

        @if (session('success'))
            <div class="mb-6 flex justify-center">
                <div class="w-full max-w-md rounded-lg border border-green-300 bg-green-50 px-6 py-3 text-green-900 shadow-sm">
                    <div class="flex items-center justify-center gap-3">
                        <i class="fas fa-circle-check text-green-600"></i>
                        <div class="text-sm font-medium">
                            {{ session('success') }}
                        </div>
                    </div>
                </div>
            </div>
        @endif
            @if(!auth()->user()->hasVerifiedEmail() && auth()->user()->email)
                <div class="flex justify-center mb-6">
                    <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mx-4 sm:mx-6 lg:mx-8 max-w-2xl">
                        <div class="flex items-center">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-triangle !text-yellow-400 text-xl"></i>
                            </div>

                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800">
                                    Email Verification Required
                                </h3>
                                <div class="mt-2 text-sm text-yellow-700">
                                    <p>Please check your email and click the verification link to activate your account.</p>
                                    <p class="mt-2 text-base font-semibold text-yellow-900">{{ auth()->user()->email }}</p>
                                    <p class="mt-1 text-xs text-yellow-600"><strong>Can't find the email?</strong> Check your spam or junk folder.</p>
                                    <p class="mt-1 text-xs text-yellow-600"><strong>Not the right address?</strong> You can update it below, click 'Update personal details' button </p>

                                </div>
                                <div class="mt-3">
                                    <form method="POST" action="{{ route('verification.resend') }}">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center px-3 py-1.5 rounded-md text-xs font-medium bg-yellow-100 hover:bg-yellow-200 text-yellow-800 border border-yellow-300 transition">
                                            <i class="fas fa-envelope mr-1"></i>
                                            Resend verification email
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if (blank(auth()->user()->email))
                <div class="mb-6 flex justify-center">
                    <div class="w-full max-w-2xl rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-amber-900">
                        <div class="flex items-start gap-3">
                            <i class="fas fa-triangle-exclamation mt-0.5 text-amber-600"></i>

                            <div class="text-sm leading-relaxed">
                                <div class="font-semibold">
                                    Email address missing
                                </div>

                                <p class="mt-1">
                                    It is strongly recommended that you add an email address if you have one.
                                    This helps us contact you about important updates and membership matters.
                                </p>

                                <p class="mt-2">
                                    Please scroll down and click
                                    <span class="font-semibold">“Update personal details”</span>
                                    to add your email.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif



    @endauth

    <div class="py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Header Section -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                <div class="flex flex-col md:flex-row justify-around items-start md:items-center">
                    <div class="flex items-start space-x-4">
                        <div class="flex flex-col items-center">
                            <div class="w-32 h-44 rounded-xl overflow-hidden flex items-center justify-center border-4 border-white shadow-lg
                                @if(($user->gender ?? 'male') === 'female') bg-gradient-to-br from-pink-400 to-purple-500
                                @else bg-gradient-to-br from-blue-400 to-blue-600 @endif">
                                @if($user->picture)
                                    <img src="{{ $user->profile_photo_url }}" alt="Profile Photo" class="w-full h-full object-cover">
                                @else
                                    <i class="fas fa-user text-4xl text-white"></i>
                                @endif
                            </div>

                            @if(!$user->picture)
                                <p class="text-sm text-gray-500 mt-2 text-center">No profile<br>photo uploaded</p>
                            @endif

                            <div class="text-center text-sm mt-1">
                                <a href="{{ route('profile.edit-photo') }}" class="plain-link">Update photo</a>
                            </div>
                        </div>


                        <!-- Name and Info -->
                        <div class="flex flex-col justify-center">
                            <h1 class="text-3xl font-bold text-gray-900">{{ $user->full_name ?? '---' }}</h1>
                            <p class="text-xl font-bold">{{ $user->getUserIdReferenceShortAttribute() }}</p>

                            <!-- Current Membership Status -->
                            @if($currentMembership)
                                <p class="text-green-600 text-sm font-medium">
                                    <i class="fas fa-id-card mr-1"></i>Membership: {{ $currentMembership['membership_type'] }}
                                    <span class="text-xs text-gray-500">(Valid until {{ $currentMembership['expiry_date'] }})</span>
                                </p>
                            @else
                                <p class="text-orange-600 text-sm font-medium">
                                    <i class="fas fa-exclamation-circle mr-1"></i>Membership: Not Active
                                </p>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4 md:mt-0">
                        <div class="flex flex-col items-center space-y-2">
                            <span class="bg-blue-600 text-white px-3 py-3 rounded-full text-lg font-medium">
                                {{ strtoupper($user->branch->name ?? '-') }}
                            </span>
                            <span class="bg-blue-600 text-white px-1 py-3 rounded-full text-sm font-medium">
                                {{ strtoupper($user->division->name ?? '-') }}
                            </span>


                        </div>
                    </div>
                </div>

                <!-- Mission Statement -->
                <div class="mt-6 p-6 bg-gray-50 rounded-lg">
                    <div class="max-w-2xl mx-auto"> {{-- Limits width and centers the container --}}
                        <p class="text-sm text-gray-700 italic text-left leading-relaxed"> {{-- text-center fixes the stretching --}}
                            Your primary responsibility as a member or volunteer of the Nigerian Red Cross Society is to serve those most at risk. We encourage you to actively participate in our core activities, including first aid and emergency preparedness and response.
                        </p>

                        <p class="text-sm text-gray-700 italic text-left leading-relaxed mt-4">
                            When an emergency strikes anywhere in Nigeria, you may be called upon. In all of your actions, you are expected to uphold our seven fundamental principles: Humanity, Impartiality, Neutrality, Independence, Voluntary Service, Unity, and Universality.
                        </p>

                        @php
                            $wantsVolunteering = (bool) $user->can_contribute_volunteering;
                            $wantsMember = (bool) $user->can_contribute_member && !$wantsVolunteering; // prefer volunteering if both are set
                        @endphp

                        @if($wantsVolunteering && !$user->redCrossUnit)
                            <div class="mt-6 p-5 bg-sky-50 border border-sky-200 rounded-lg">
                                <div class="flex items-start gap-3">
                                    <i class="fas fa-seedling text-sky-500 mt-1"></i>
                                    <div class="text-lg text-sky-900">
                                        <p><strong>Next step:</strong> Contact your branch so we can place you in a Red Cross Unit.</p>
                                        <p>See contact details below.</p>
                                    </div>
                                </div>
                            </div>
                        @elseif($wantsMember && !$currentMembership && !$hasEverHadPersonalPayment)
                            <div class="mt-6 p-5 bg-blue-50 border border-blue-200 rounded-lg">
                                <div class="flex items-start gap-3">
                                    <i class="fas fa-id-card text-blue-500 mt-1"></i>
                                    <div class="text-sm text-blue-900">
                                        <p><strong>Pay your membership fee to activate your membership.</strong></p>
                                        <p class="mt-1 text-blue-700 italic">Online payment via Paystack — to be implemented.</p>

                                    </div>
                                </div>
                            </div>
                        @elseif($wantsMember && !$currentMembership && $hasEverHadPersonalPayment)
                            <div class="mt-6 p-5 bg-blue-50 border border-blue-200 rounded-lg">
                                <div class="flex items-start gap-3">
                                    <i class="fas fa-id-card text-blue-500 mt-1"></i>
                                    <div class="text-sm text-blue-900">
                                        <p><strong>Your membership has expired.</strong> Pay your membership fee to activate it.</p>
                                        <p class="mt-1 text-blue-700 italic">Online payment via Paystack — to be implemented.</p>

                                    </div>
                                </div>
                            </div>
                        @elseif($wantsMember && $currentMembership && ($currentMembership['expiring_soon'] ?? false))
                            <div class="mt-6 p-5 bg-blue-50 border border-blue-200 rounded-lg">
                                <div class="flex items-start gap-3">
                                    <i class="fas fa-id-card text-blue-500 mt-1"></i>
                                    <div class="text-sm text-blue-900">
                                        <p><strong>Membership expires in {{ $currentMembership['days_until_expiry'] }} days</strong> ({{ $currentMembership['expiry_date'] }}).</p>
                                        <p class="mt-1 text-blue-700 italic">Online renewal via Paystack — to be implemented.</p>
                                        {{-- Note for implementation of Paystack: For persons linke to an organisation(s), list payments to organisation if renewal needed, and also show if the payment was recently done; there might be a conflict since 2 or more persons might be linked to an org, all of them got a reminder by renewal membership campaigns, and we have do make sure they pay again to same org, but get a msg, that X already did a payment --}}
                                    </div>
                                </div>
                            </div>
                        @elseif(!$wantsVolunteering && !$wantsMember)
                            <div class="mt-6 p-5 bg-sky-50 border border-sky-200 rounded-lg">
                                <div class="flex items-start gap-3">
                                    <i class="fas fa-seedling text-sky-500 mt-1"></i>
                                    <div class="text-lg text-sky-900">
                                        <p>Please update your contribution preference below so we can guide your next step. </p>

                                    </div>
                                </div>
                            </div>
                        @endif

                        @if($user->primary_role_name)
                            <div class="mt-6">
                                <x-role-description-card :user="$user" />
                            </div>
                        @endif

                        {{-- RC Unit & Task Force membership --}}
                        @if($user->redCrossUnit || $user->taskForces->isNotEmpty())
                            <div class="mt-6 p-5 bg-blue-50 border border-blue-200 rounded-lg">
                                <div class="flex items-start gap-3">
                                    <div class="flex-shrink-0 w-9 h-9 bg-blue-100 rounded-lg flex items-center justify-center">
                                        <i class="fas fa-shield-alt text-red-600 text-sm"></i>
                                    </div>
                                    <div class="flex-1">

                                        {{-- RC Unit --}}
                                        @if($user->redCrossUnit)
                                            @php
                                                $rcu = $user->redCrossUnit;
                                                if ($rcu->team_leader_user_id === $user->id) {
                                                    $rcuRole = 'Team Leader';
                                                } elseif ($rcu->assistant_team_leader_user_id === $user->id) {
                                                    $rcuRole = 'Assistant Team Leader';
                                                } else {
                                                    $rcuRole = 'member';
                                                }
                                            @endphp
                                            <p class="text-sm text-blue-900">
                                                @if($rcuRole === 'member')
                                                    You are a member of <strong>{{ $rcu->name }}</strong> Red Cross Unit.
                                                @else
                                                    You serve as <strong>{{ $rcuRole }}</strong> of <strong>{{ $rcu->name }}</strong> Red Cross Unit.
                                                @endif
                                                <a href="{{ route('red-cross-units.my-unit') }}"
                                                   class="ml-2 text-xs text-blue-600 hover:text-blue-800 underline underline-offset-2">
                                                    View unit &rarr;
                                                </a>
                                            </p>
                                        @endif

                                        {{-- Task Forces --}}
                                        @if($user->taskForces->isNotEmpty())
                                            <div class="{{ $user->redCrossUnit ? 'mt-3 pt-3 border-t border-blue-200' : '' }}">
                                                <p class="text-xs font-semibold text-blue-700 uppercase tracking-wide mb-1">
                                                    {{ $user->taskForces->count() === 1 ? 'Task Force' : 'Task Forces' }}
                                                </p>
                                                <ul class="space-y-1">
                                                    @foreach($user->taskForces as $tf)
                                                        @php
                                                            if ($tf->team_leader_user_id === $user->id) {
                                                                $tfRole = 'Team Leader';
                                                            } elseif ($tf->assist_team_leader_user_id === $user->id) {
                                                                $tfRole = 'Assistant Team Leader';
                                                            } else {
                                                                $tfRole = 'member';
                                                            }
                                                        @endphp
                                                        <li class="flex items-start gap-2 text-sm text-blue-800">
                                                            <i class="fas fa-circle-dot text-blue-400 text-xs mt-1 flex-shrink-0"></i>
                                                            <span>
                                        @if($tfRole === 'member')
                                                                    Member of <strong>{{ $tf->name }}</strong>
                                                                @else
                                                                    <strong>{{ $tfRole }}</strong> of <strong>{{ $tf->name }}</strong>
                                                                @endif
                                        <a href="{{ route('my-task-force.show', $tf) }}"
                                           class="ml-2 text-xs text-blue-600 hover:text-blue-800 underline underline-offset-2">
                                            View &rarr;
                                        </a>
                                    </span>
                                                        </li>
                                                    @endforeach
                                                </ul>
                                            </div>
                                        @endif

                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                </div>


            </div>


            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <!-- Left Column -->
                <div class="space-y-8">
                    <!-- Membership Section -->
                    <div class="bg-white rounded-lg shadow-lg p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-id-card text-blue-600 text-xl"></i>
                            </div>
                            <h2 class="text-xl font-bold text-gray-900">YOUR MEMBERSHIP</h2>
                        </div>

                        <!-- Current Valid Membership Status -->
                        <div class="mb-6 p-4 border-2 border-dashed border-gray-200 rounded-lg bg-gray-50">
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
                                        <span class="bg-green-100 text-green-800 px-3 py-2 rounded-full text-sm font-medium">
                                            <i class="fas fa-check-circle mr-1"></i>Active
                                        </span>
                                    </div>
                                </div>
                            @else
                                <div class="flex items-center justify-center py-4">
                                    <div class="text-center">
                                        <i class="fas fa-exclamation-triangle text-orange-500 text-2xl mb-2"></i>
                                        <p class="text-orange-700 font-medium">No Active Membership</p>
                                        <p class="text-sm text-gray-600 mt-1">Please renew your membership to continue enjoying member benefits</p>
                                    </div>
                                </div>
                            @endif
                        </div>

                        <p class="text-gray-600 text-sm mb-4">
                            In order to be a member of the Nigerian Red Cross, you should contribute with an annual membership fee.
                            This fee shall be paid to your Red Cross branch or division.
                        </p>

                        <!-- Payment History with Scrollable Container -->
                        @if($showingLimitMessage)
                            <div class="mb-2 p-2 bg-blue-50 border border-blue-200 rounded text-center">
                                <span class="text-blue-800 text-xs font-medium">
                                    <i class="fas fa-info-circle mr-1"></i>Showing recent payments - scroll to view more
                                </span>
                            </div>
                        @endif

                        <div class="overflow-x-auto">
                            <div class="@if($showingLimitMessage) max-h-64 overflow-y-auto @endif">
                                <table class="w-full text-sm">
                                    <thead class="sticky top-0 bg-white">
                                    <tr class="border-b border-gray-200">
                                        <th class="text-left py-2 text-gray-600 bg-white">Payment date</th>
                                        <th class="text-left py-2 text-gray-600 bg-white">Membership Type</th>
                                        <th class="text-left py-2 text-gray-600 bg-white">Amount</th>
                                        <th class="text-left py-2 text-gray-600 bg-white">Status</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($membershipPayments as $payment)
                                        <tr class="border-b border-gray-100">
                                            <td class="py-2">{{ $payment['payment_date'] }}</td>
                                            <td class="py-2">
                                                {{ $payment['membership_type'] }}
                                                @if($payment['organisation_name'])
                                                    <span class="block text-xs text-indigo-600 italic">On behalf of {{ $payment['organisation_name'] }}</span>
                                                @endif
                                            </td>
                                            <td class="py-2">{{ $payment['formatted_amount'] }}</td>
                                            <td class="py-2">
                                                    <span class="{{ $payment['status']['class'] }} px-2 py-1 rounded-full text-xs">
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

                    <!-- Donations Section -->
                    <div class="bg-white rounded-lg shadow-lg p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-hand-holding-heart text-green-600 text-xl"></i>
                            </div>
                            <h2 class="text-xl font-bold text-gray-900">YOUR DONATIONS</h2>
                        </div>
                        <p class="text-gray-600 text-sm mb-4">
                            Your contribution can help achieve solutions to problems among vulnerable people in our society.
                            You can donate money, food, clothes, hygiene materials etc. Contact your branch for more information.
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
                                                    <div>
                                                        {{ $donation['item'] }}
                                                        @if($donation['organisation_name'])
                                                            <span class="block text-xs text-indigo-600 italic">On behalf of {{ $donation['organisation_name'] }}</span>
                                                        @endif
                                                    </div>
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
                    </div>

                    <!-- Volunteering Section -->
                    @if($user->isVolunteer())
                    <div class="bg-white rounded-lg shadow-lg p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-hands-helping text-orange-600 text-xl"></i>
                            </div>
                            <h2 class="text-xl font-bold text-gray-900">YOUR VOLUNTEERING</h2>
                        </div>
                        <p class="text-gray-600 text-sm mb-4">
                            Nobody can do everything, but everyone can do something. Can you spare 2 or 3 hours per week?
                            The Nigerian Red Cross needs people, from all walks of life. As a volunteer you will be assigned to a Red Cross Unit or a Task Force.
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
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($activities as $activity)
                                        <tr class="border-b border-gray-100">
                                            <td class="py-2 px-1 whitespace-nowrap">{{ $activity['date'] }}</td>
                                            <td class="py-2 px-1">
                                                <div class="flex items-center">

                                                    {{ $activity['activity'] }}
                                                </div>

                                            </td>
                                            <td class="py-2 px-1 font-medium">{{ $activity['hours_numeric'] }}</td>
                                            <td class="py-2 px-1 break-words whitespace-normal">
                                                @if($activity['unit'])
                                                    <span class="text-xs">
                                                           {{ $activity['unit'] }}
                                                        </span>
                                                @else
                                                    <span class="text-gray-400 text-xs">—</span>
                                                @endif
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
                                $totalHours = $activities->sum('hours_numeric');
                                $totalActivities = $activities->count();
                                $redCrossUnitActivities = $activities->where('unit', '!=', '')->count();
                                $generalActivities = $totalActivities - $redCrossUnitActivities;
                            @endphp
                            <div class="mt-4 p-3 bg-orange-50 border border-orange-200 rounded-lg">
                                <div class="flex items-center justify-between text-sm">
                                    <div class="text-orange-800">
                                        <i class="fas fa-clock mr-1"></i>
                                        <strong>Total:</strong> {{ $totalHours }} {{ $totalHours == 1 ? 'hour' : 'hours' }}
                                    </div>
                                    <div class="text-orange-600">
                                         {{ $totalActivities }} logged activities

                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                    @endif {{-- can_contribute_volunteering --}}

                    <!-- Training Section -->
                    <div class="bg-white rounded-lg shadow-lg p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-graduation-cap text-purple-600 text-xl"></i>
                            </div>
                            <h2 class="text-xl font-bold text-gray-900">YOUR TRAINING</h2>
                        </div>
                        <p class="text-gray-600 text-sm mb-4">
                            As a member you are entitled to attend training courses, such as First Aid, Leadership and Disaster Management. Please note that First Aid certifications must be renewed periodically to remain valid.
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
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($trainings as $training)
                                        <tr class="border-b border-gray-100">
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
                                                    <span class="{{ $training['status']['class'] }} px-2 py-1 rounded-full text-xs">
                                                        {{ $training['status']['text'] }}
                                                    </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="py-4 text-center text-gray-500 italic">
                                                No training records found
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Printed Certificates --}}
                    <div class="bg-white rounded-lg shadow-lg p-6 mt-8">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-print text-indigo-600 text-xl"></i>
                            </div>
                            <h2 class="text-xl font-bold text-gray-900">PRINTED CERTIFICATES</h2>
                        </div>

                        <p class="text-gray-600 text-sm mb-4">
                            This section shows certificates that have been printed for you.
                        </p>

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
                    @if($user->isVolunteer())
                    <div class="bg-white rounded-lg shadow-lg p-6 mt-8">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-id-card text-emerald-600 text-xl"></i>
                            </div>
                            <h2 class="text-xl font-bold text-gray-900">PRINTED ID CARDS</h2>
                        </div>

                        <p class="text-gray-600 text-sm mb-4">
                            This section shows ID cards that have been printed for you.
                        </p>

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
                                                        $statusClass = 'bg-blue-100 text-blue-800';
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
                    @endif {{-- can_contribute_volunteering / redCrossUnit --}}
                </div>

                <!-- Right Column -->
                <div class="space-y-8">

                    <!-- Branch Contact Details -->
                    @if($user->branch)
                        <div class="bg-white rounded-lg shadow-lg p-6">
                            <h2 class="text-xl font-bold text-gray-900 mb-4">
                                {{ $user->branch->name ?? '-' }} Branch Contact Details
                            </h2>

                            <div class="space-y-3 text-sm">
                                @if($user->branch->physical_address)
                                    <div class="grid grid-cols-3 gap-2">
                                        <span class="text-gray-600">Physical Address:</span>
                                        <span class="col-span-2 text-gray-900">{{ $user->branch->physical_address }}</span>
                                    </div>
                                @endif
                                @if($user->branch->postal_address)
                                    <div class="grid grid-cols-3 gap-2">
                                        <span class="text-gray-600">Postal Address:</span>
                                        <span class="col-span-2 text-gray-900">{{ $user->branch->postal_address }}</span>
                                    </div>
                                @endif
                                @if($user->branch->telephone)
                                    <div class="grid grid-cols-3 gap-2">
                                        <span class="text-gray-600">Telephone:</span>
                                        <span class="col-span-2 text-gray-900">{{ $user->branch->telephone }}</span>
                                    </div>
                                @endif
                                @if($user->branch->email)
                                    <div class="grid grid-cols-3 gap-2">
                                        <span class="text-gray-600">Email:</span>
                                        <span class="col-span-2 text-gray-900">{{ $user->branch->email }}</span>
                                    </div>
                                @endif
                            </div>

                            {{-- Contact persons --}}
                            @php
                                $branchContacts = $user->branch->publicContacts();
                            @endphp

                            @if(!empty($branchContacts))
                                <div class="mt-6 border-t border-gray-200 pt-4">
                                    <h3 class="text-lg font-semibold text-gray-900 mb-3 flex items-center">
                                        <i class="fas fa-users mr-2 text-blue-600"></i>
                                        Contact Persons
                                    </h3>

                                    <div class="space-y-3 text-sm">
                                        @foreach($branchContacts as $contact)
                                            @php $contactUser = $contact['user']; @endphp
                                            <div class="flex items-start gap-3">
                                                {{-- Avatar --}}
                                                @php
                                                    $grad = ($contactUser->gender ?? 'male') === 'female'
                                                        ? 'from-pink-400 to-purple-500'
                                                        : 'from-blue-400 to-blue-600';
                                                @endphp
                                                <div class="w-10 h-15 rounded-xl overflow-hidden flex items-center justify-center border-2 border-white shadow bg-gradient-to-br {{ $grad }}">
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
                                                            <span class="px-2 py-0.5 rounded-full text-xs bg-blue-100 text-blue-800">
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
                    @endif

                    {{-- Your Organisations --}}
                    @if($user->organisations->isNotEmpty())
                        <div class="bg-white rounded-lg shadow-lg p-6">
                            <div class="flex items-center mb-4">
                                <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                                    <i class="fas fa-building text-blue-600 text-xl"></i>
                                </div>
                                <h2 class="text-xl font-bold text-gray-900">YOUR ORGANISATIONS</h2>
                            </div>
                            <p class="text-gray-600 text-sm mb-4">
                                You are registered as a contact person for the following organisation(s):
                            </p>
                            <div class="space-y-3">
                                @foreach($user->organisations as $linkedOrg)
                                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                                        <div>
                                            <a href="{{ route('profile.organisation', $linkedOrg) }}"
                                               class="font-medium text-blue-600 hover:text-blue-800">
                                                {{ $linkedOrg->name }}
                                            </a>
                                            @if($linkedOrg->pivot->is_primary_contact)
                                                <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                    <i class="fas fa-star mr-1"></i>Primary Contact
                                                </span>
                                            @endif
                                        </div>
                                        <a href="{{ route('profile.organisation', $linkedOrg) }}"
                                           class="text-sm text-blue-600 hover:text-blue-800 whitespace-nowrap ml-4">
                                            View &rarr;
                                        </a>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Consolidated Details -->
                    <div class="bg-white rounded-lg shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Your Details</h2>
                        <div class="space-y-3 text-sm">
                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-gray-600">Title:</span>
                                <span class="col-span-2 text-gray-900">{{ $user->title ?? 'Not provided' }}</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-gray-600">First name:</span>
                                <span class="col-span-2 text-gray-900">{{ $user->first_name ?? 'Not provided' }}</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-gray-600">Middle name:</span>
                                <span class="col-span-2 text-gray-900">{{ $user->middle_name ?? 'Not provided' }}</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-gray-600">Last name:</span>
                                <span class="col-span-2 text-gray-900">{{ $user->last_name ?? 'Not provided' }}</span>
                            </div>

                            <div class="mb-4">
                                <div class="grid grid-cols-3 gap-2 items-center {{ !$user->email ? 'border-2 border-blue-500 rounded-md p-2' : '' }}">
                                    <span class="text-gray-600">Email:</span>
                                    <span class="col-span-2 text-gray-900">{{ $user->email ?? 'Not provided' }}</span>
                                </div>
                                @if(!$user->email)
                                    <p class="mt-1 text-xs text-blue-600 font-bold italic">Please provide an Email Address</p>
                                @endif
                            </div>

                            <div class="mb-2">
                                <div class="grid grid-cols-3 gap-2 items-center {{ !$user->telephone1 ? 'border-2 border-blue-500 rounded-md p-2' : '' }}">
                                    <span class="text-gray-600">Telephone:</span>
                                    <span class="col-span-2 text-gray-900">{{ $user->telephone1 ?? 'Not provided' }}</span>
                                </div>
                                @if(!$user->telephone1)
                                    <p class="mt-1 text-xs text-blue-600 font-bold italic">Please provide a Telephone Number</p>
                                @endif
                            </div>

                            <div class="grid grid-cols-3 gap-2 items-center">
                                <span class="text-gray-600">Telephone 2:</span>
                                <span class="col-span-2 text-gray-900">{{ $user->telephone2 ?? 'Not provided' }}</span>
                            </div>

                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-gray-600">Residential address:</span>
                                <span class="col-span-2 text-gray-900">{{ $user->residential_address ?? 'Not provided' }}</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-gray-600">Workplace name:</span>
                                <span class="col-span-2 text-gray-900">{{ $user->organisation ?? 'Not provided' }}</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-gray-600">State/Branch:</span>
                                <span class="col-span-2 text-gray-900">{{ $user->branch->name ?? 'Not assigned' }}</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-gray-600">LGA/Division:</span>
                                <span class="col-span-2 text-gray-900">{{ $user->division->name ?? 'Not assigned' }}</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-gray-600">Red Cross Unit:</span>
                                <span class="col-span-2 text-gray-900">
                                   @if($user->redCrossUnit)
                                        {{ $user->redCrossUnit->name }}
                                    @else
                                        <span class="text-gray-500 italic">Not assigned</span>
                                    @endif
                                </span>
                            </div>

                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-gray-600">Birth year:</span>
                                <span class="col-span-2 text-gray-900">{{ $user->birth_year ?? 'Not provided' }}</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-gray-600">Gender:</span>
                                <span class="col-span-2 text-gray-900">
                                   @if($user->gender)
                                        {{ ucfirst($user->gender) }}
                                        @if($user->gender === 'female')
                                            <i class="fas fa-venus text-pink-500 ml-1"></i>
                                        @elseif($user->gender === 'male')
                                            <i class="fas fa-mars text-blue-500 ml-1"></i>
                                        @endif
                                    @else
                                        Not provided
                                    @endif
                                 </span>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-gray-600">Marital status:</span>
                                <span class="col-span-2 text-gray-900">{{ $user->marital_status ? ucfirst($user->marital_status) : 'Not provided' }}</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-gray-600">Education/Training:</span>
                                <span class="col-span-2 text-gray-900"> {{ $user->disciplin }}</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-gray-600">Profession:</span>
                                <span class="col-span-2 text-gray-900">{{ $user->occupation ?? 'Not provided' }}</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-gray-600">Workplace address:</span>
                                <span class="col-span-2 text-gray-900">{{ $user->workplace_address ?? 'Not provided' }}</span>
                            </div>

                            <div>
                                <div class="grid grid-cols-3 gap-2 items-center {{ !$user->national_id_number ? 'border-2 border-blue-500 rounded-md p-2' : '' }}">
                                    <span class="text-gray-600">National ID:</span>
                                    <span class="col-span-2 text-gray-900">
                {{ $user->national_id_number ?? 'Not provided' }}
            </span>
                                </div>
                                @if(!$user->national_id_number)
                                    <p class="mt-1 text-xs text-blue-600 font-bold">
                                        Please provide a National ID
                                    </p>
                                @endif
                            </div>

                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-gray-600">Personal info:</span>
                                <span class="col-span-2 text-gray-900">
            @if($user->personal_info)
                                        {{ $user->personal_info }}
                                    @else
                                        <span class="text-gray-500 italic">Not provided</span>
                                    @endif
        </span>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <span class="text-gray-600">Contribution preference:</span>
                                <span class="col-span-2 text-gray-900">
                                    @if($user->can_contribute_volunteering)
                                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded-full text-xs">
                                            <i class="fas fa-hands-helping mr-1"></i>Volunteer Services
                                        </span>
                                    @elseif($user->can_contribute_member)
                                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded-full text-xs">
                                            <i class="fas fa-id-card mr-1"></i>Active Membership
                                        </span>
                                    @else
                                        <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded-full text-xs">
                                            <i class="fas fa-minus mr-1"></i>Not specified
                                        </span>
                                    @endif
                                </span>
                            </div>
                        </div>
                        <div class="mt-6 flex flex-wrap gap-3 justify-start">
                            <a href="{{ route('profile.edit') }}" class="btn-primary">
                                <i class="fas fa-edit mr-1"></i>Update personal details
                            </a>
                            <a href="{{ route('profile.edit-photo') }}" class="btn-primary">
                                <i class="fas fa-camera mr-1"></i>Update photo
                            </a>
                        </div>
                    </div>

                    <!-- Signature Section -->
                    @if($user->isVolunteer())
                    <div class="bg-white rounded-lg shadow-lg p-6">
                        <h2 class="text-xl font-bold text-gray-900 mb-4">Signature</h2>
                        <p class="text-sm text-gray-600 mb-4">The signature image will be used for making ID-cards for volunteers.</p>
                        <div class="flex flex-col items-center justify-center border border-gray-300 bg-gray-50 p-4 rounded-lg w-48 h-24 mx-auto">
                            @if($user->hasSignature())
                                <img src="{{ $user->getSignatureUrlAttribute() }}" alt="User Signature" class="max-w-full h-auto max-h-48 object-contain">
                            @else
                                <div class="text-gray-500 italic text-center py-4">No signature uploaded</div>
                            @endif
                        </div>
                        <div class="mt-4 flex justify-start"> {{-- Changed from justify-center --}}
                            <a href="{{ route('profile.edit-signature') }}" class="btn-primary">
                                <i class="fas fa-signature mr-2"></i>Update Signature
                            </a>
                        </div>
                    </div>
                    @endif {{-- can_contribute_volunteering / redCrossUnit --}}

                    <!-- Communication Preferences -->
                    <div class="bg-white rounded-lg shadow-lg p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-bell text-gray-600 text-xl"></i>
                            </div>
                            <h2 class="text-xl font-bold text-gray-900">Communication Preferences</h2>
                        </div>

                        <form method="POST" action="{{ route('profile.communication.update') }}">
                            @csrf
                            <div class="space-y-4">
                                {{-- Email campaigns --}}
                                <div>
                                    <div class="flex items-center">
                                        <input type="hidden" name="email_opt_out" value="0">
                                        <input type="checkbox" id="pref_email_opt_out" name="email_opt_out" value="1"
                                               @checked(!$user->email_opt_out)
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <label for="pref_email_opt_out" class="ml-2 block text-sm text-gray-900">
                                            Receive email messages from the Red Cross
                                        </label>
                                    </div>
                                    @if($user->email_opt_out && $user->email_opt_out_at)
                                        <p class="mt-1 ml-6 text-xs text-gray-400">
                                            You unsubscribed on {{ $user->email_opt_out_at->format('M d, Y') }}. You can re-subscribe at any time.
                                        </p>
                                    @endif
                                </div>

                                {{-- SMS campaigns --}}
                                <div>
                                    <div class="flex items-center">
                                        <input type="hidden" name="sms_opt_out" value="0">
                                        <input type="checkbox" id="pref_sms_opt_out" name="sms_opt_out" value="1"
                                               @checked(!$user->sms_opt_out)
                                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                                        <label for="pref_sms_opt_out" class="ml-2 block text-sm text-gray-900">
                                            Receive SMS messages from the Red Cross
                                        </label>
                                    </div>
                                    @if($user->sms_opt_out && $user->sms_opt_out_at)
                                        <p class="mt-1 ml-6 text-xs text-gray-400">
                                            You unsubscribed on {{ $user->sms_opt_out_at->format('M d, Y') }}. You can re-subscribe at any time.
                                        </p>
                                    @endif
                                </div>
                            </div>

                            <div class="mt-6">
                                <button type="submit" class="btn-primary">
                                    Save preferences
                                </button>
                            </div>
                        </form>

                        <p class="mt-4 text-xs text-gray-400 italic">
                            You can also unsubscribe directly from any campaign message you receive.
                        </p>
                    </div>

                    <!-- Archive My Account -->
                    <button type="button" id="archiveAccountRevealBtn"
                            class="inline-flex items-center px-3 py-1.5 rounded-md border border-gray-300 text-sm text-gray-500 hover:text-red-600 hover:border-red-300 focus:outline-none">
                        <i class="fas fa-user-slash mr-2"></i>Archive My Account
                    </button>

                    <div id="archiveAccountCard" class="hidden mt-4 bg-white rounded-lg shadow-lg p-6 border border-red-200">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                                <i class="fas fa-user-slash text-red-600 text-xl"></i>
                            </div>
                            <h2 class="text-xl font-bold text-gray-900">Archive My Account</h2>
                        </div>
                        <p class="text-sm text-gray-600 mb-4">
                            This will deactivate your account and log you out. You will not be able to log in again until an administrator or your branch reactivates you.
                        </p>
                        @if(auth()->user()->getRoleNames()->isNotEmpty())
                            <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded p-3">
                                You hold an administrative role. Ask another administrator to remove it before you can archive your own account.
                            </p>
                        @else
                            <form method="POST" action="{{ route('profile.self-archive') }}" onsubmit="return confirm('Are you sure? This cannot be undone by you — you will need to contact your branch to be reactivated.');">
                                @csrf
                                <label for="archive_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                                    Type <strong>archive</strong> to confirm:
                                </label>
                                <input type="text" name="confirmation" id="archive_confirmation" required
                                       class="border-gray-300 rounded-md shadow-sm w-full max-w-xs mb-3"
                                       placeholder="archive" autocomplete="off">
                                <button type="submit" class="inline-flex items-center px-4 py-2 rounded-md text-sm font-medium bg-red-600 text-white hover:bg-red-700">
                                    <i class="fas fa-user-slash mr-2"></i>Archive My Account
                                </button>
                            </form>
                        @endif
                    </div>

                </div>
            </div>
        </div>
    </div>
    <script>
        (function () {
            const revealBtn = document.getElementById('archiveAccountRevealBtn');
            const card = document.getElementById('archiveAccountCard');
            if (!revealBtn || !card) return;
            revealBtn.addEventListener('click', function () {
                revealBtn.classList.add('hidden');
                card.classList.remove('hidden');
            });
        })();
    </script>
</x-layouts.app>
