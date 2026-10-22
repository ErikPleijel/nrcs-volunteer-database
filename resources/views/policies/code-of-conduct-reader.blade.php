{{--
    The Code of Conduct scroll box and the four confirmation checkboxes, shared
    by auth/register and consent/confirm. Include it inside an element with
    x-data="cocReader()"; the same parent can use canSubmit for its submit button.

    The checkboxes unlock once the text has been scrolled to the end, or
    straight away when the text fits without scrolling (large screens).
    Clicking a locked checkbox or its label explains why, and brings the box
    into view.

    cocReader() is a global function in a classic inline script rather than an
    Alpine.data() in resources/js: the app layout loads the CDN Alpine (defer)
    before the Vite module, so Alpine has already started by the time app.js
    runs. An inline script runs during parsing, before either.
--}}
<div>
    <div
        x-ref="cocBox"
        class="rounded-lg border-2 transition-shadow duration-300"
        :class="highlight ? 'border-red-600 ring-4 ring-red-200' : 'border-gray-400'"
    >
        <div class="flex items-center gap-2 px-3 sm:px-4 py-2 bg-gray-100 border-b border-gray-300 rounded-t-lg">
            <i class="fas fa-book-open text-red-700" aria-hidden="true"></i>
            <h3 id="coc-reader-heading" class="text-sm sm:text-base font-semibold text-gray-900">
                Code of Conduct – please read
            </h3>
        </div>

        <div class="relative">
            <div
                id="coc-scroll-container"
                x-ref="cocScroll"
                class="bg-slate-50 px-2 sm:px-4 py-3 max-h-80 overflow-y-auto overscroll-contain rounded-b-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-blue-500"
                tabindex="0"
                role="region"
                aria-labelledby="coc-reader-heading"
                aria-describedby="coc-scroll-hint"
                @scroll.passive="checkScroll()"
                @resize.window.debounce.200ms="checkScroll()"
                @load.window="checkScroll()"
            >
                @include('policies.code-of-conduct', ['embedded' => true])
            </div>

            {{-- Fade showing there is more text below; hidden at the bottom. --}}
            <div
                class="pointer-events-none absolute inset-x-0 bottom-0 h-14 rounded-b-lg bg-gradient-to-t from-slate-50 to-transparent"
                aria-hidden="true"
                x-show="!atBottom"
                x-transition.opacity
            ></div>
        </div>
    </div>

    <p id="coc-scroll-hint" class="mt-2 text-sm font-medium text-gray-700" x-show="!hasScrolledToBottom">
        Scroll down to read the full text ↓
    </p>

    {{-- Right under the box, so it is still on screen once the box has been
         scrolled into view. --}}
    <p
        class="mt-2 rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-900"
        role="status"
        aria-live="polite"
        x-show="lockedMessage && !hasScrolledToBottom"
        style="display: none"
    >
        <i class="fas fa-circle-info mr-1" aria-hidden="true"></i>
        Please read the Code of Conduct first – scroll to the bottom of the text.
    </p>

    {{-- Catches clicks on the locked checkboxes (they ignore pointer events while
         disabled) and on their labels. --}}
    <div @click="explainLocked($event)">
        @include('policies.code-of-conduct-commitments')
    </div>
</div>

@once
    <script>
        function cocReader() {
            return {
                hasScrolledToBottom: false,
                atBottom: false,
                lockedMessage: false,
                highlight: false,
                commitment1: false,
                commitment2: false,
                commitment3: false,
                commitment4: false,

                init() {
                    // After a validation error the ticked boxes come back;
                    // the user has already read the text.
                    [1, 2, 3, 4].forEach((n) => {
                        const box = document.getElementById('coc_commitment_' + n);
                        if (box && box.hasAttribute('data-old-checked')) {
                            this['commitment' + n] = true;
                            this.hasScrolledToBottom = true;
                        }
                    });

                    this.$nextTick(() => this.checkScroll());
                },

                // Also true when the text fits without scrolling.
                checkScroll() {
                    const box = this.$refs.cocScroll;
                    if (!box) {
                        return;
                    }
                    this.atBottom = box.scrollHeight - box.scrollTop - box.clientHeight <= 8;
                    if (this.atBottom) {
                        this.hasScrolledToBottom = true;
                    }
                },

                explainLocked(event) {
                    if (this.hasScrolledToBottom || event.target.closest('a')) {
                        return;
                    }
                    event.preventDefault();
                    this.lockedMessage = true;

                    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                    this.$refs.cocBox.scrollIntoView({ behavior: reduceMotion ? 'auto' : 'smooth', block: 'center' });
                    this.$refs.cocScroll.focus({ preventScroll: true });

                    this.highlight = true;
                    clearTimeout(this.highlightTimer);
                    this.highlightTimer = setTimeout(() => { this.highlight = false; }, 2000);
                },

                get canSubmit() {
                    return this.hasScrolledToBottom &&
                        this.commitment1 && this.commitment2 &&
                        this.commitment3 && this.commitment4;
                }
            };
        }
    </script>
@endonce
