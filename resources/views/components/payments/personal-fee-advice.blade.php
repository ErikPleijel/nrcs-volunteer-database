{{-- Explainers below the personal fee list, shared by the online payment page
     (audience "self") and the staff payment form (audience "staff"). Every fee
     can be chosen (Decisions.md 2026-10-09); these guide the choice instead.

     The two amber boxes are shown and hidden by each page's JS:
     - [data-fee-advice="volunteer-fee-no-unit"]: a volunteer fee is selected
       and the person is not in an active Red Cross Unit;
     - [data-fee-advice="left-unit"]: the person left their unit
       (User::isUnassignedGhost()), shown as soon as the person is known.
     $leftUnit sets the left-unit box's starting state for a page that already
     knows the person. --}}
@props(['audience' => 'self', 'leftUnit' => false])

@php($staff = $audience === 'staff')

<div {{ $attributes->merge(['class' => 'mt-2 space-y-2']) }}>
    <p class="text-xs text-gray-500" data-fee-advice="general">
        @if($staff)
            Member fees are for supporting members who support the Red Cross financially. Volunteer fees are for volunteers who serve in a Red Cross Unit. If this person is a volunteer, choose a volunteer fee — or a member fee if they wish to give more.
        @else
            Member fees are for supporting members who support the Red Cross financially. Volunteer fees are for volunteers who serve in a Red Cross Unit. If you are a volunteer, choose a volunteer fee — or a member fee if you wish to give more.
        @endif
    </p>

    <div data-fee-advice="left-unit"
         class="{{ $leftUnit ? '' : 'hidden' }} flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
        <i class="fas fa-triangle-exclamation mt-0.5 text-amber-600"></i>
        <span>
            @if($staff)
                This person has left their Red Cross Unit. If they want to continue as a volunteer, assign a unit before recording a volunteer fee.
            @else
                You are no longer in a Red Cross Unit. If you want to continue as a volunteer, please contact your branch so they can add you to a unit before you pay.
            @endif
        </span>
    </div>

    <div data-fee-advice="volunteer-fee-no-unit"
         class="hidden flex items-start gap-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
        <i class="fas fa-triangle-exclamation mt-0.5 text-amber-600"></i>
        <span>
            @if($staff)
                This person is not in a Red Cross Unit. Volunteer fees are for unit members — consider assigning a unit before recording this payment.
            @else
                Volunteer fees are for volunteers in a Red Cross Unit, and you are not in a unit at the moment. Please contact your branch so they can add you to a unit before you pay. If you want to support us without volunteering, choose a member fee.
            @endif
        </span>
    </div>
</div>
