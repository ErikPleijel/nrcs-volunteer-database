{{-- Manual payments: the go-live notes shared by Tab 2's branch and division
     levels. Expects $goLivePhase, $goLiveDate and $goLiveDateInvalid (only
     ever true for national admins). --}}
@php
    $statsClass = \App\Services\Reports\IncomeDistributionStatsService::class;
@endphp

@if($goLiveDateInvalid)
    <div class="bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 p-4 mb-6" role="alert" data-testid="invalid-go-live-note">
        <p class="font-semibold">
            <i class="fas fa-exclamation-triangle mr-1"></i>{{ $statsClass::INVALID_GO_LIVE_NOTE }}
        </p>
    </div>
@endif

@if($goLivePhase === 'before')
    <div class="bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 p-4 mb-6" role="note" data-testid="before-go-live-note">
        <p class="font-semibold"><i class="fas fa-info-circle mr-1"></i>{{ $statsClass::BEFORE_GO_LIVE_NOTE }}</p>
    </div>
@elseif($goLivePhase === 'transition')
    <div class="bg-yellow-50 border-l-4 border-yellow-400 text-yellow-800 p-4 mb-6" role="note" data-testid="transition-note">
        <p class="font-semibold"><i class="fas fa-info-circle mr-1"></i>{{ $statsClass::transitionNote($goLiveDate) }}</p>
    </div>
@endif
