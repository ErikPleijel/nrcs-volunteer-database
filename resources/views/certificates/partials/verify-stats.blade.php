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
                    <div class="stat-label">{{ number_format($stats['first_aid_stale']) }} need a refresher (over {{ \App\Services\VerificationStatsService::FIRST_AID_STALE_MONTHS / 12 }} years)</div>
                </div>
            </div>

            {{-- Volunteering hours logged for the unit / branch, by calendar year. --}}
            <div class="stats-grid">
                <div class="stat">
                    <div class="stat-value">{{ number_format($stats['hours_total']) }}</div>
                    <div class="stat-label">Volunteering hours, all time</div>
                </div>
                <div class="stat">
                    <div class="stat-value">{{ number_format($stats['hours_this_year']) }}</div>
                    <div class="stat-label">Hours in {{ $stats['year'] }} so far</div>
                </div>
                <div class="stat">
                    <div class="stat-value">{{ number_format($stats['hours_last_year']) }}</div>
                    <div class="stat-label">Hours in {{ $stats['year'] - 1 }}</div>
                </div>
                <div class="stat">
                    <div class="stat-value">{{ number_format($stats['hours_two_years_ago']) }}</div>
                    <div class="stat-label">Hours in {{ $stats['year'] - 2 }}</div>
                </div>
            </div>

            {{-- Volunteer / Volunteer & Member / Member split. A unit's own
                 people all belong to it, so Member and Other never apply there. --}}
            <p class="stats-breakdown">
                Volunteers: {{ number_format($stats['volunteer_only']) }}
                · Volunteers &amp; Members: {{ number_format($stats['volunteer_member']) }}
                @if($stats['scope'] !== 'unit')
                    · Supporting Members: {{ number_format($stats['member_only']) }}
                    @if($stats['unclassified'] > 0)
                        · Other: {{ number_format($stats['unclassified']) }}
                    @endif
                @endif
            </p>
        @endif
    </div>
@endif
