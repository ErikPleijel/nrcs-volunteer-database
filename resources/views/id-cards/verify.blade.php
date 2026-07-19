<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NRCS Member Verification</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f7fafc;
        }
    </style>
</head>
<body class="font-sans antialiased">
<div class="container mx-auto p-4 md:p-8 max-w-lg">
    <div class="bg-white rounded-lg shadow-xl overflow-hidden">
        <div class="p-6 bg-red-700 text-white text-center">
            <div class="flex justify-center mb-4">
                <div class="w-28 h-28 rounded-full overflow-hidden border-4 border-white bg-white">
                    <img src="{{ asset('images/id-card/NRCS_logo.jpg') }}"
                         alt="NRCS Logo"
                         class="object-cover w-full h-full pr-[2px] pb-[2px]">
                </div>
            </div>
            <h1 class="text-2xl font-bold">Nigerian Red Cross Society</h1>
            <p class="text-md">ID-Card Verification</p>
        </div>

        <div class="p-8">
            <div class="text-center">
                <h2 class="text-3xl font-extrabold text-gray-800 flex items-center justify-center gap-3">
                    @if($user['is_membership_valid'])
                        <span class="inline-flex bg-green-500 rounded-full p-1.5">
                            <i class="fas fa-check text-white text-sm"></i>
                        </span>
                    @else
                        <span class="inline-flex bg-red-500 rounded-full p-1.5">
                            <i class="fas fa-times text-white text-sm"></i>
                        </span>
                    @endif
                    {{ $user['full_name'] }}
                </h2>
            </div>

            <div class="mt-8 border-t border-gray-200 pt-8">
                <dl class="grid grid-cols-1 gap-x-4 gap-y-8 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                        <dt class="text-sm font-medium text-gray-500">
                            <i class="fas fa-id-badge mr-2 text-gray-400"></i>Membership Status
                        </dt>
                        @if($user['is_membership_valid'])
                            <dd class="mt-1 text-lg font-semibold text-green-600">
                                VALID
                            </dd>
                        @else
                            <dd class="mt-1 text-lg font-semibold text-red-600">
                                EXPIRED or INACTIVE
                            </dd>
                        @endif
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">
                            <i class="fas fa-layer-group mr-2 text-gray-400"></i>Membership Type
                        </dt>
                        <dd class="mt-1 text-lg text-gray-900">{{ $user['membership_type'] }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">
                            <i class="far fa-calendar-times mr-2 text-gray-400"></i>Expires
                        </dt>
                        <dd class="mt-1 text-lg text-gray-900">{{ $user['membership_expiry'] }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">
                            <i class="fas fa-map-marker-alt mr-2 text-gray-400"></i>Branch
                        </dt>
                        <dd class="mt-1 text-lg text-gray-900">{{ $user['branch'] }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500">
                            <i class="fas fa-map-pin mr-2 text-gray-400"></i>Division
                        </dt>
                        <dd class="mt-1 text-lg text-gray-900">{{ $user['division'] }}</dd>
                    </div>
                </dl>
            </div>
        </div>
        <div class="px-6 py-4 bg-gray-50 text-center text-sm text-gray-500">
            This is an automated verification check. For any enquiries, please contact the Nigerian Red Cross Society.
        </div>
    </div>

    @if($user['first_aid_trainings']->isNotEmpty())
        <div class="mt-8">
            <div class="p-4 bg-white rounded-lg shadow-xl">
                <h3 class="text-xl font-semibold text-gray-800 flex items-center mb-4">
                    <i class="fas fa-briefcase-medical mr-3 text-red-600"></i>
                    First Aid Certifications
                </h3>
                <div class="space-y-4">
                    @foreach($user['first_aid_trainings'] as $training)
                        <div class="border border-gray-200 rounded-lg p-4 flex flex-col sm:flex-row justify-between items-start sm:items-center">
                            <div class="flex-grow mb-4 sm:mb-0">
                                <p class="font-bold text-gray-900">{{ $training->trainingType->name ?? 'N/A' }}</p>
                                <div class="text-sm text-gray-600 mt-2">
                                    <p><i class="fas fa-calendar-alt w-4 mr-1"></i> Date: <span class="font-medium">{{ $training->training_date ? $training->training_date->format('d M Y') : 'N/A' }}</span></p>
                                    <p><i class="fas fa-hourglass-end w-4 mr-1"></i> Expiry: <span class="font-medium">{{ $training->expiry_date ? $training->expiry_date->format('d M Y') : 'Permanent' }}</span></p>
                                </div>
                            </div>
                            <div class="flex-shrink-0">
                                @if($training->isValid())
                                    <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        <i class="fas fa-check-circle mr-2"></i>Valid
                                    </span>
                                @else
                                    <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        <i class="fas fa-times-circle mr-2"></i>Expired
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Other (non first-aid) trainings --}}
    @if($user['other_trainings']->isNotEmpty())
        <div class="mt-8">
            <div class="p-4 bg-white rounded-lg shadow-xl">
                <h3 class="text-xl font-semibold text-gray-800 flex items-center mb-4">
                    <i class="fas fa-graduation-cap mr-3 text-red-600"></i>
                    Other Trainings
                </h3>
                <div class="space-y-3">
                    @foreach($user['other_trainings'] as $training)
                        <div class="border border-gray-200 rounded-lg p-3 flex justify-between items-center">
                            <p class="font-medium text-gray-900">{{ $training->trainingType->name ?? 'N/A' }}</p>
                            <p class="text-sm text-gray-500 whitespace-nowrap ml-3">
                                <i class="fas fa-calendar-alt mr-1"></i>
                                {{ $training->training_date ? $training->training_date->format('d M Y') : 'N/A' }}
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- Total volunteering hours --}}
    @if($user['total_volunteering_hours'])
        <div class="mt-8 mb-4">
            <div class="p-4 bg-white rounded-lg shadow-xl flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                    <i class="fas fa-hands-helping mr-3 text-red-600"></i>
                    Total Volunteering Hours
                </h3>
                <span class="text-2xl font-extrabold text-gray-900">{{ $user['total_volunteering_hours'] }}</span>
            </div>
        </div>
    @endif
</div>
</body>
</html>
