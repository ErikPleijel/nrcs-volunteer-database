<x-layouts.app title="Privacy Policy">
    <section class="py-8 lg:py-16 bg-white">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-6">Privacy Policy</h1>

            {{-- All wording (and the draft banner) lives in this partial, so Legal's
                 final text can replace it without touching this page. --}}
            @include('policies.privacy-policy-text')

            <div class="mt-10">
                <x-dpo-contact :public="true" />
            </div>
        </div>
    </section>
</x-layouts.app>
