{{-- The personal fee <optgroup>s shared by the online payment page and the
     staff payment form (see MembershipFee::personalFeeGroups()). Each group
     carries data-fee-group so the staff form can reorder the groups once a
     person is selected; each option keeps the data attributes the ID card
     fee, payment summary and volunteer-fee advice read. --}}
@props(['groups', 'selected' => null])

@foreach($groups as $group)
    <optgroup label="{{ $group['label'] }}" data-fee-group="{{ $group['key'] }}">
        @foreach($group['fees'] as $fee)
            <option value="{{ $fee->id }}"
                    data-validity="{{ $fee->validity_years }}"
                    data-amount="{{ $fee->amount }}"
                    data-id-card-fee="{{ $fee->id_card_fee }}"
                    data-volunteer-fee="{{ $fee->is_volunteer_fee ? '1' : '0' }}"
                    {{ (string) $selected === (string) $fee->id ? 'selected' : '' }}>
                {{ $fee->name }} — ₦{{ number_format($fee->amount, 2) }} ({{ $fee->validity_years }} {{ $fee->validity_years === 1 ? 'year' : 'years' }})
            </option>
        @endforeach
    </optgroup>
@endforeach
