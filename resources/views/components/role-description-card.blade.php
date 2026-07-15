@props(['user'])

@php
    $roleKey = $user->primary_role_name ?? null;
    $roleInfo = $roleKey ? config("role_descriptions.{$roleKey}") : null;
@endphp

@if($roleInfo)
    <div class="p-5 bg-blue-50 border border-blue-200 rounded-lg">
        <div class="flex items-start gap-3">
            <div class="flex-shrink-0 w-9 h-9 bg-blue-100 rounded-lg flex items-center justify-center">
                <i class="fas fa-shield-alt text-blue-600 text-sm"></i>
            </div>
            <div class="flex-1">
                <p class="text-base text-blue-900 mb-2"> YOUR DATABASE ROLE: </p>
                <p class="text-xl font-semibold text-blue-900 mb-2">
                    {{ $roleInfo['title'] }}
                </p>
                <p class="text-xs font-semibold text-blue-700 uppercase tracking-wide mb-1">You can:</p>
                <ul class="space-y-1 mb-3">
                    @foreach($roleInfo['points'] as $point)
                        <li class="flex items-start gap-2 text-sm text-blue-800">
                            <i class="fas fa-circle-dot text-blue-400 text-xs mt-1 flex-shrink-0"></i>
                            <span>{!! $point !!}</span>
                        </li>
                    @endforeach
                </ul>
                @if(!empty($roleInfo['notice']))
                    <div class="mt-3 flex items-start gap-2 bg-yellow-50 border border-yellow-200 rounded-md px-3 py-2">
                        <i class="fas fa-triangle-exclamation text-yellow-500 text-xs mt-0.5 flex-shrink-0"></i>
                        <p class="text-xs text-yellow-800">{{ $roleInfo['notice'] }}</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif
