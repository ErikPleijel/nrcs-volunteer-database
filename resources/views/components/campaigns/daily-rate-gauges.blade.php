@php
    $gauges = [
        [
            'label' => 'Email sent today',
            'sent' => $emailSent,
            'cap' => $emailCap,
            'color' => 'indigo',
        ],
        [
            'label' => 'SMS sent today',
            'sent' => $smsSent,
            'cap' => $smsCap,
            'color' => 'sky',
        ],
    ];

    $statusMeta = function (int $sent, int $cap) {
        if ($cap <= 0) {
            return ['label' => 'No cap set', 'class' => 'text-gray-500'];
        }

        $ratio = $sent / $cap;

        if ($ratio >= 1) {
            return ['label' => 'Cap reached', 'class' => 'text-rose-600'];
        }

        if ($ratio >= 0.7) {
            return ['label' => 'Near cap', 'class' => 'text-amber-600'];
        }

        return ['label' => 'On pace', 'class' => 'text-emerald-600'];
    };

    $arcLength = 157; // tuned for the arc path below
@endphp

{{-- Narrow card: centered, ~50% width on large screens --}}
<div class="max-w-xl lg:max-w-2xl mx-auto">
    <div class="rounded-lg border border-gray-200 bg-white shadow-sm">
        <div class="p-5">
            <div class="flex items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-gray-800">Daily sending rate</p>
                    <p class="mt-1 text-xs text-gray-500">
                        Progress toward today’s caps for Email and SMS.
                    </p>
                </div>
            </div>

            {{-- Two gauges in the same card --}}
            <div class="mt-5 grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach ($gauges as $gauge)
                    @php
                        $cap = (int) $gauge['cap'];
                        $sent = (int) $gauge['sent'];
                        $status = $statusMeta($sent, $cap);

                        $pct = $cap > 0 ? min(100, (int) round(($sent / $cap) * 100)) : 0;
                        $remaining = $cap > 0 ? max(0, $cap - $sent) : null;

                        $dashOffset = $cap > 0 ? round($arcLength * (1 - ($pct / 100)), 1) : $arcLength;

                        $strokeClass = $gauge['color'] === 'sky' ? 'stroke-sky-500' : 'stroke-indigo-500';
                    @endphp

                    <div class="rounded-md border border-gray-100 bg-gray-50 p-4">
                        <p class="text-sm font-semibold text-gray-700">{{ $gauge['label'] }}</p>

                        <div class="mt-3 flex items-end gap-3">
                            {{-- Gauge --}}
                            <div class="relative w-36 h-20 shrink-0">
                                <svg viewBox="0 0 120 70" class="w-full h-full">
                                    {{-- background arc --}}
                                    <path
                                        d="M 10 60 A 50 50 0 0 1 110 60"
                                        fill="none"
                                        stroke-width="10"
                                        class="stroke-gray-200"
                                        stroke-linecap="round"
                                    />
                                    {{-- progress arc --}}
                                    <path
                                        d="M 10 60 A 50 50 0 0 1 110 60"
                                        fill="none"
                                        stroke-width="10"
                                        class="{{ $strokeClass }}"
                                        stroke-linecap="round"
                                        style="stroke-dasharray: {{ $arcLength }}; stroke-dashoffset: {{ $dashOffset }};"
                                    />
                                </svg>

                                {{-- Center label --}}
                                <div class="absolute inset-0 flex flex-col items-center justify-end pb-0.5">
                                    <div class="text-xl font-extrabold text-gray-900">
                                        {{ $cap > 0 ? $pct : '—' }}%
                                    </div>
                                    <div class="text-[11px] text-gray-500 leading-tight">
                                        {{ number_format($sent) }} / {{ $cap > 0 ? number_format($cap) : '—' }}
                                    </div>
                                </div>
                            </div>

                            {{-- Meta --}}
                            <div class="min-w-0 flex-1">
                                <div class="text-sm font-semibold text-gray-900">
                                    {{ number_format($sent) }}
                                    <span class="font-medium text-gray-500">sent</span>
                                </div>

                                <div class="mt-1 flex items-center justify-between text-xs">
                                    <span class="font-medium {{ $status['class'] }}">{{ $status['label'] }}</span>
                                    @if (! is_null($remaining))
                                        <span class="text-gray-500">{{ number_format($remaining) }} left</span>
                                    @endif
                                </div>

                                @if ($cap <= 0)
                                    <p class="mt-2 text-[11px] text-gray-500">
                                        Set a daily cap in Settings to enable pacing.
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Explanatory text below both gauges --}}
            <div class="mt-4 rounded-md bg-white">
                <p class="text-xs text-gray-500">
                    Counts recipients with status <span class="font-mono text-gray-600">sent</span> today (local time).
                    Caps are configured in Settings and help prevent overload and unexpected spikes.
                </p>
            </div>
        </div>
    </div>
</div>
