@props(['large' => false])

@php
    $classes = $large
        ? 'inline-flex items-center px-3 py-1 rounded-full text-sm font-semibold bg-red-100 text-red-800'
        : 'inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800';
    $icon = $large ? 'fa-triangle-exclamation' : 'fa-copy';
    $label = $large ? 'Duplicate? Same person has another pending payment' : 'Repeated';
    $title = $large
        ? 'This member has another pending membership payment — verify this is not a duplicate submission before approving.'
        : 'This member has another pending record awaiting approval in this list.';
@endphp

<span {{ $attributes->merge(['class' => $classes]) }} title="{{ $title }}">
    <i class="fas {{ $icon }} mr-1"></i>{{ $label }}
</span>
