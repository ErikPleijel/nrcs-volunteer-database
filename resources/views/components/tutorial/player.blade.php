@props(['lessonKey', 'title'])

@php
    $levelNum = config('tutorials.lessons')[$lessonKey]['level'];
    $backUrl   = route('tutorials.level', ['level' => $levelNum]);
    $completeUrl = route('tutorials.complete', $lessonKey);
@endphp

@once
<style>
[x-cloak]{display:none!important}
/*
 * Only hide [data-reveal] elements once JS has run and added .tut-reveal-ready.
 * If Alpine never initialises, the class is never added and content is visible.
 * Also skipped entirely for prefers-reduced-motion users (they never get hidden state).
 */
@media (prefers-reduced-motion: no-preference) {
    .tut-reveal-ready [data-reveal] {
        opacity: 0;
        transform: translateY(12px);
    }
}
/* Pulse on the sound icon while narration is playing */
@keyframes tut-pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.4; }
}
.tut-narrating {
    animation: tut-pulse 1.5s ease-in-out infinite;
}
/* Coachmark ring: expanding indigo glow around the sound toggle */
@keyframes tut-ring-pulse {
    0%   { box-shadow: 0 0 0 0   rgba(99, 102, 241, 0.55); }
    65%  { box-shadow: 0 0 0 9px rgba(99, 102, 241, 0); }
    100% { box-shadow: 0 0 0 0   rgba(99, 102, 241, 0); }
}
.tut-ring {
    animation: tut-ring-pulse 1.5s ease-out infinite;
    border-radius: 0.5rem;
}
/* Narration progress bar fill — smooth normally, instant under reduced motion */
.tut-progress-fill {
    transition: width 200ms linear;
}
/* Reduced-motion: static ring, no animations, instant progress fill */
@media (prefers-reduced-motion: reduce) {
    .tut-ring {
        animation: none;
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.45);
    }
    .tut-narrating { animation: none; }
    .tut-progress-fill { transition: none; }
}
</style>
<script>
function tutorialPlayerData(completeUrl) {
    return {
        // Server-rendered route('tutorials.complete', $lessonKey) — passed in via
        // x-data so the POST target can never be undefined/built client-side.
        completeUrl: completeUrl,
        current: 0,
        total: 0,
        completed: false,
        completedOnce: false,
        slides: [],
        soundOn: false,
        hasInteracted: false,
        coachmarkDismissed: false,
        narrating: false,
        paused: false,
        audioProgress: 0,
        audioEl: null,
        prefetchEl: null,

        init() {
            // Add readiness class FIRST so the CSS rule kicks in before
            // the reveal animation; $nextTick lets that paint commit first.
            this.$el.classList.add('tut-reveal-ready');

            // Single reusable Audio object for all narration
            this.audioEl = new Audio();
            this.audioEl.preload = 'none';

            // Separate element used only to warm the browser cache for the next clip
            this.prefetchEl = new Audio();
            this.prefetchEl.preload = 'auto';
            this.audioEl.addEventListener('ended', () => { this.narrating = false; this.audioProgress = 100; });
            this.audioEl.addEventListener('timeupdate', () => {
                const d = this.audioEl.duration;
                this.audioProgress = (d && isFinite(d)) ? (this.audioEl.currentTime / d) * 100 : 0;
            });

            this.slides = Array.from(this.$el.querySelectorAll('[data-slide]'));
            this.total  = this.slides.length;
            this.slides.forEach((s, i) => {
                s.style.display = i === 0 ? 'block' : 'none';
            });
            this.$nextTick(() => {
                if (this.slides[0]) this.revealSlide(this.slides[0]);
                if (this.total <= 1) this.markComplete();
                // No autoplay on load — narration starts only when the user turns it on
            });
        },

        isFirst() { return this.current === 0; },
        isLast()  { return this.total > 0 && this.current === this.total - 1; },

        next() {
            if (this.isLast()) return;
            this.coachmarkDismissed = true;
            this.stopAudio();
            this.slides[this.current].style.display = 'none';
            this.current++;
            this.slides[this.current].style.display = 'block';
            this.playSlideAudio(this.slides[this.current]);
            this.revealSlide(this.slides[this.current]);
            if (this.isLast()) this.markComplete();
            this.prefetchNext();
        },

        prev() {
            if (this.isFirst()) return;
            this.coachmarkDismissed = true;
            this.stopAudio();
            this.slides[this.current].style.display = 'none';
            this.current--;
            this.slides[this.current].style.display = 'block';
            this.playSlideAudio(this.slides[this.current]);
            this.revealSlide(this.slides[this.current]);
            this.prefetchNext();
        },

        goTo(i) {
            this.stopAudio();
            this.slides[this.current].style.display = 'none';
            this.current = i;
            this.slides[this.current].style.display = 'block';
            this.playSlideAudio(this.slides[this.current]);
            this.revealSlide(this.slides[this.current]);
            if (this.isLast()) this.markComplete();
            this.prefetchNext();
        },

        playSlideAudio(slideEl) {
            const src = slideEl.dataset.audio;
            if (!src) {
                this.stopAudio();
                return;
            }
            if (!this.soundOn) return;
            // Stop whatever was playing and reset before switching src
            this.audioEl.pause();
            this.audioEl.currentTime = 0;
            this.narrating = false;
            this.paused = false;
            this.audioProgress = 0;
            this.audioEl.src = src;
            this.audioEl.play()
                .then(() => { this.narrating = true; })
                .catch(() => { this.narrating = false; });
        },

        stopAudio() {
            this.audioEl.pause();
            this.audioEl.currentTime = 0;
            this.narrating = false;
            this.paused = false;
            this.audioProgress = 0;
        },

        toggleSound() {
            this.soundOn = !this.soundOn;
            this.hasInteracted = true;
            if (!this.soundOn) {
                this.stopAudio();
            } else {
                this.coachmarkDismissed = true;
                // This click is a user gesture — browser will allow the play()
                this.playSlideAudio(this.slides[this.current]);
                this.prefetchNext();
            }
        },

        revealSlide(slideEl) {
            const els = Array.from(slideEl.querySelectorAll('[data-reveal]'));
            if (!els.length) return;
            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            // Reset all to hidden with no transition so the start state is clean
            els.forEach(el => {
                el.style.transition = 'none';
                el.style.transitionDelay = '0ms';
                el.style.opacity = '0';
                el.style.transform = 'translateY(12px)';
            });
            if (reducedMotion) {
                els.forEach(el => {
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                });
                return;
            }
            // Force reflow to commit the reset state before adding the transition
            void slideEl.offsetHeight;
            els.forEach((el, i) => {
                const delay = Math.min(i, 7) * 500;
                el.style.transition = 'opacity 500ms ease-out, transform 500ms ease-out';
                el.style.transitionDelay = delay + 'ms';
                el.style.opacity = '1';
                el.style.transform = 'translateY(0)';
            });
        },

        markComplete() {
            if (this.completedOnce) return;
            this.completedOnce = true;
            const url   = this.completeUrl;
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token ?? '' },
            })
            .then(async (response) => {
                console.log('markComplete request:', { url: url, payload: { method: 'POST', csrf: token ?? '' } });
                console.log('markComplete response:', { status: response.status, ok: response.ok });
                if (!response.ok) {
                    console.error('markComplete error body:', await response.text());
                    throw new Error('markComplete HTTP ' + response.status);
                }
                return response.json();
            })
            .then(() => { this.completed = true; })
            .catch(err => console.error('markComplete failed:', err));
        },

        togglePause() {
            if (this.narrating && !this.paused) {
                this.audioEl.pause();
                this.paused = true;
            } else if (this.paused) {
                this.audioEl.play()
                    .then(() => { this.paused = false; })
                    .catch(() => {});
            }
            // If neither narrating nor paused, the button is disabled — do nothing
        },

        repeatNarration() {
            const slideEl = this.slides[this.current];
            const src = slideEl && slideEl.dataset.audio;
            if (!src) return;
            this.soundOn = true;
            this.coachmarkDismissed = true;
            this.audioEl.pause();
            this.audioEl.currentTime = 0;
            this.paused = false;
            this.audioProgress = 0;
            this.narrating = false;
            this.audioEl.src = src;
            this.audioEl.play()
                .then(() => { this.narrating = true; })
                .catch(() => { this.narrating = false; });
        },

        currentSlideHasAudio() {
            return !!(this.slides[this.current] && this.slides[this.current].dataset.audio);
        },

        prefetchNext() {
            const nextSlide = this.slides[this.current + 1];
            const src = nextSlide && nextSlide.dataset.audio;
            if (!src) return;
            const abs = new URL(src, location.href).href;
            if (this.prefetchEl.src !== abs) {
                this.prefetchEl.src = src;
                this.prefetchEl.load();
            }
        },
    };
}
</script>
@endonce

{{--
    overflow-hidden is intentionally absent here: the coachmark tip is absolutely
    positioned above the footer and must not be clipped. The visual border-radius
    effect is preserved by adding rounded-t-xl / rounded-b-xl to the header and
    footer instead.
--}}
<div x-data="tutorialPlayerData(@js($completeUrl))"
     data-complete-url="{{ $completeUrl }}"
     @keydown.arrow-right.window="next()"
     @keydown.arrow-left.window="prev()"
     class="bg-blue-50 rounded-xl shadow-lg border border-gray-700">

    {{-- Header: rounded-t-xl preserves the visual corner clip from the removed overflow-hidden --}}
    <div class="flex items-center justify-between px-6 py-3 bg-gray-50 border-b border-gray-200 rounded-t-xl">
        <span class="font-semibold text-gray-800 truncate pr-4">{{ $title }}</span>
        <span class="text-sm text-gray-500 whitespace-nowrap flex-shrink-0"
              x-text="`Slide ${current + 1} of ${total}`"></span>
    </div>

    {{-- Slides (managed by JS show/hide) --}}
    <div>
        {{ $slot }}
    </div>

    {{-- Footer: rounded-b-xl preserves the visual corner clip from the removed overflow-hidden --}}
    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 rounded-b-xl flex flex-col gap-3">

        {{-- Narration progress bar: spans full footer width, tracks audio playback position --}}
        <div class="-mx-6 -mt-4 h-1 bg-gray-200 overflow-hidden">
            <div class="h-full bg-gray-500 tut-progress-fill"
                 :style="`width: ${audioProgress}%`"></div>
        </div>

        {{-- Status row: completion badge + back link (hidden until relevant) --}}
        <div x-show="completed || isLast()"
             x-cloak
             class="flex flex-col items-center gap-3 w-full">
            <span x-show="completed"
                  x-transition.opacity
                  class="flex items-center gap-1.5 text-green-700 text-sm font-medium">
                <i class="fas fa-check-circle"></i> Lesson completed
            </span>
            <a x-show="isLast()"
               href="{{ $backUrl }}"
               class="inline-flex items-center gap-2 px-4 py-2 border border-indigo-300 text-indigo-700 bg-indigo-50 hover:bg-indigo-100 rounded-lg text-sm font-medium">
                <i class="fas fa-list"></i> Back to lesson list
            </a>
        </div>

        {{-- Navigation row: Previous | dots + sound | Next --}}
        {{-- Layout via order classes only: mobile stacks (controls, Previous, Next);
             desktop is a single row (Previous, center zone, Next). --}}
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-2">

            {{--
                Center zone: relative so the coachmark tip can be absolute-positioned
                above it. The tip floats out of flow upward — it is not clipped because
                overflow-hidden was removed from the outer card.
                order-1 sm:order-2 → top on mobile, middle on desktop.
            --}}
            <div class="order-1 sm:order-2 w-full sm:w-auto sm:flex-1 flex flex-wrap items-center justify-center gap-3 relative">

                {{-- Coachmark tip: printed-label style, not interactive --}}
                <div x-show="!coachmarkDismissed"
                     x-transition.opacity.duration.400ms
                     class="absolute left-1/2 -translate-x-1/2 flex flex-col items-center pointer-events-none z-10"
                     style="bottom: calc(100% + 2px);">
                    <div class="border-2 border-slate-700 bg-yellow-100 rounded-full text-gray-900 text-xl px-2 py-1 whitespace-nowrap">
                        Start narration
                    </div>
                    <i class="hidden fas fa-arrow-down text-2xl text-gray-700 mt-1 pointer-events-none" style="transform: rotate(-45deg);"></i>
                </div>

                {{-- Progress dots --}}
                <div class="flex items-center gap-2">
                    <template x-for="i in total" :key="i">
                        <button type="button"
                                @click="goTo(i - 1)"
                                :class="current === i - 1
                                    ? 'w-3 h-3 bg-indigo-600 rounded-full'
                                    : 'w-2 h-2 bg-gray-300 hover:bg-gray-400 rounded-full'"
                                class="transition-all duration-150 focus:outline-none"
                                :aria-label="`Go to slide ${i}`">
                        </button>
                    </template>
                </div>

                {{-- Sound toggle: fa-play when off (inviting), fa-volume-up when on --}}
                <button type="button"
                        @click="toggleSound()"
                        :aria-pressed="soundOn.toString()"
                        :aria-label="soundOn ? 'Turn narration off' : 'Turn narration on'"
                        :class="[
                            soundOn ? 'text-indigo-600 hover:bg-indigo-50' : 'text-gray-400 hover:bg-gray-100',
                            !coachmarkDismissed ? 'tut-ring' : ''
                        ]"
                        class="p-2 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-300">
                    <i class="fas text-2xl" :class="soundOn ? 'fa-volume-up' : 'fa-play'"></i>
                </button>

                {{-- Pause/resume: holds position in the clip; disabled when nothing is playing or paused --}}
                <button type="button"
                        @click="togglePause()"
                        :disabled="!narrating && !paused"
                        :aria-label="paused ? 'Resume narration' : 'Pause narration'"
                        :class="(!narrating && !paused) ? 'text-gray-300 cursor-not-allowed' : 'text-gray-400 hover:bg-gray-100'"
                        class="p-2 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-300 disabled:opacity-40">
                    <i class="fas text-2xl" :class="paused ? 'fa-play' : 'fa-pause'"></i>
                </button>

                {{-- Repeat: replays current slide's narration from the start; disabled when slide has no audio --}}
                <button type="button"
                        @click="repeatNarration()"
                        :disabled="!currentSlideHasAudio()"
                        aria-label="Replay narration"
                        :class="currentSlideHasAudio() ? 'text-gray-400 hover:bg-gray-100' : 'text-gray-300 cursor-not-allowed'"
                        class="p-2 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-300 disabled:opacity-40">
                    <i class="fas fa-rotate-right text-2xl"></i>
                </button>

            </div>

            {{-- Previous --}}
            <button type="button"
                    @click="prev()"
                    :disabled="isFirst()"
                    class="order-2 sm:order-1 inline-flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed flex-shrink-0">
                <i class="fas fa-arrow-left"></i> Previous
            </button>

            {{-- Next --}}
            {{-- TODO: Optional "now you may proceed" glow — add a ring/pulse to this button after the user has heard the narration; not yet implemented --}}
            <button type="button"
                    @click="next()"
                    :disabled="isLast()"
                    class="order-3 inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium disabled:opacity-40 disabled:cursor-not-allowed flex-shrink-0">
                Next <i class="fas fa-arrow-right"></i>
            </button>

        </div>
    </div>

</div>
