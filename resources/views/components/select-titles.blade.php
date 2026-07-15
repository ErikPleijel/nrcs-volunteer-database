@props([
    'name' => 'title',   // ✅ default
    'label' => 'Title',
    'options' => null,
    'value' => null,
    'placeholder' => 'Select title',
    'required' => false,
    'disabled' => false,
    'hint' => null,
])


@php
    if ($name === 'title' && is_null($options)) {
         $options = [
             'Mr'    => 'Mr',
             'Mrs'   => 'Mrs',
             'Ms'    => 'Ms',
             'Miss'  => 'Miss',
             'Prof.' => 'Prof.',
             'Chief' => 'Chief',
             'Dr.'   => 'Dr.',
             'Hon.'  => 'Hon.',
         ];
     }
     // Resolve selected value (old() > passed value)
     $selected = old($name, $value);
@endphp

<div class="space-y-1">
    @if($label)
        <label for="{{ $name }}"
               class="block text-sm font-medium text-gray-700">
            {{ $label }}
            @if($required)
                <span class="text-red-500">*</span>
            @endif
        </label>
    @endif

    <select
        id="{{ $name }}"
        name="{{ $name }}"
        @if($required) required @endif
        @if($disabled) disabled @endif
        {{ $attributes->merge([
            'class' =>
                'w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500'
        ]) }}
    >
        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach($options as $key => $option)
            @php
                // Support both ['Mr', 'Mrs'] and ['mr' => 'Mr']
                $optionValue = is_int($key) ? $option : $key;
                $optionLabel = $option;
            @endphp

            <option value="{{ $optionValue }}"
                {{ (string)$selected === (string)$optionValue ? 'selected' : '' }}>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>

    @if($hint)
        <p class="text-xs text-gray-500">
            {{ $hint }}
        </p>
    @endif

    @error($name)
    <p class="text-xs text-red-600">
        {{ $message }}
    </p>
    @enderror
</div>
