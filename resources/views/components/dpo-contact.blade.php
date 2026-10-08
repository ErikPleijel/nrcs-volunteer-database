@if($name !== '')
    <div {{ $attributes->merge(['class' => 'rounded-md border border-gray-200 bg-white p-4 text-sm text-gray-800']) }}>
        <p class="font-semibold">
            <i class="fas fa-user-shield mr-1 text-gray-500"></i>Data Protection Officer (DPO)
        </p>
        <div class="mt-1 space-y-1">
            <p>{{ $name }}</p>
            @if($address !== '')
                <p>{!! nl2br(e($address)) !!}</p>
            @endif
            @if($email !== '')
                <p>
                    <i class="fas fa-envelope mr-1 text-gray-400"></i>
                    <a href="mailto:{{ $email }}" class="text-blue-700 hover:underline">{{ $email }}</a>
                </p>
            @endif
            @if($phone !== '')
                <p>
                    <i class="fas fa-phone mr-1 text-gray-400"></i>
                    <a href="tel:{{ $phoneHref }}" class="text-blue-700 hover:underline">{{ $phone }}</a>
                </p>
            @endif
        </div>
    </div>
@elseif($public)
    <div {{ $attributes->merge(['class' => 'rounded-md border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700']) }}>
        Contact details for the Data Protection Officer will be published here soon.
    </div>
@else
    <div {{ $attributes->merge(['class' => 'rounded-md border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900']) }}>
        <p class="font-semibold">
            <i class="fas fa-triangle-exclamation mr-1 text-amber-500"></i>No Data Protection Officer is registered.
        </p>
        <p class="mt-1">
            @if($canEditSettings)
                Go to <a href="{{ route('admin.settings.index') }}" class="font-medium underline hover:text-amber-700">Settings</a>
                and enter the DPO's name and contact details.
            @else
                Please inform the National DB Administrator.
            @endif
        </p>
    </div>
@endif
