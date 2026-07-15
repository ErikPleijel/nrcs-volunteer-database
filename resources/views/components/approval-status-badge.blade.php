@props(['status'])

@php
    $map = [
        'pending'  => ['Pending',  'bg-yellow-100 text-yellow-800', 'fa-clock'],
        'approved' => ['Approved', 'bg-green-100 text-green-800',   'fa-check'],
        'rejected' => ['Rejected', 'bg-red-100 text-red-800',       'fa-xmark'],
    ];
    [$label, $cls, $icon] = $map[$status] ?? ['Unknown', 'bg-gray-100 text-gray-700', 'fa-question'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {$cls}"]) }}
      title="Approval status: {{ $label }}">
    <i class="fas {{ $icon }} mr-1"></i>{{ $label }}
</span>
