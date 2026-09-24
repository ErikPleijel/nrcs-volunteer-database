{{--
    Anonymous volunteer figures (VerificationStatsService) under a successful
    verification: the Red Cross Unit's own, or a branch's. Groups under the
    privacy threshold never show figures.
--}}
@if(!empty($stats))
    <div class="stats-section">
        <div class="stats-heading">
            @if($stats['scope'] === 'unit')
                About this Red Cross Unit
            @else
                About {{ $stats['scope_label'] }} branch
            @endif
        </div>

        @if($stats['suppressed'])
            <p class="stats-note">
                Statistics are shown for groups of {{ \App\Services\VerificationStatsService::MIN_GROUP_SIZE }} or more members.
            </p>
        @else
            <div class="stats-grid">
                <div class="stat">
                    <div class="stat-value">{{ number_format($stats['total']) }}</div>
                    <div class="stat-label">Volunteers</div>
                </div>
                <div class="stat">
                    <div class="stat-value">{{ number_format($stats['male']) }} / {{ number_format($stats['female']) }}</div>
                    <div class="stat-label">Men / Women</div>
                </div>
                <div class="stat">
                    <div class="stat-value">{{ $stats['avg_age'] !== null ? number_format($stats['avg_age'], 1) : '—' }}</div>
                    <div class="stat-label">Average age</div>
                </div>
                <div class="stat">
                    <div class="stat-value">{{ number_format($stats['first_aid']) }}</div>
                    <div class="stat-label">Have had first aid training</div>
                </div>
            </div>
        @endif
    </div>
@endif
