<x-layouts.admin :title="'National Settings'">

    <x-slot name="pageHeader">
        <i class="fa-solid fa-cog mr-3 mb-6"></i>National Settings
    </x-slot>

    <x-audit-notice />

    <div class="container mx-auto py-8 px-4">
        @if (session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p class="font-bold">Success</p>
                <p>{{ session('success') }}</p>
            </div>
        @endif

        <div class="flex justify-center mb-4">
            <x-help-popup trigger-class="help-btn">
                <x-slot:trigger><i class="fas fa-question-circle text-base mr-1"></i>Guide</x-slot:trigger>

                {{-- Header --}}
                <div class="-mt-8 mb-4 text-center">
                    <i class="fas fa-question-circle text-xl text-sky-500"></i>
                    <h3 class="mt-1 text-base font-semibold text-gray-900">How do I...</h3>
                </div>

                {{-- Sensitive area warning --}}
                <div class="max-w-3xl mx-auto mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800">
                    <i class="fas fa-triangle-exclamation mr-1"></i>
                    These settings affect the <span class="font-semibold">whole organisation</span>. Change with care.
                </div>

                {{-- Accordion --}}
                <div class="max-w-3xl mx-auto">
                    <div x-data="{ open: null }" class="space-y-1 text-sm mb-4">

                        {{-- National Database Settings --}}
                        <div class="rounded-md border border-gray-200 overflow-hidden">
                            <button type="button"
                                    @click="open = open === 'national' ? null : 'national'"
                                    class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                <span><i class="fas fa-database mr-2 text-indigo-400"></i>National Database Settings</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                   :class="open === 'national' ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open === 'national'" x-collapse class="px-4 py-3 bg-white">
                                <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                    <li><span class="font-semibold">Months of inactivity before dormant</span> — controls how long a user must be inactive before becoming Dormant.</li>
                                    <li><span class="font-semibold">Site Motto</span> — displayed in the footer and on public pages.</li>
                                    <li><span class="font-semibold">Social Share Snippet</span> — HTML snippet used for social sharing meta tags.</li>
                                </ul>
                            </div>
                        </div>

                        {{-- Campaign Settings --}}
                        <div class="rounded-md border border-gray-200 overflow-hidden">
                            <button type="button"
                                    @click="open = open === 'campaigns' ? null : 'campaigns'"
                                    class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                <span><i class="fas fa-bullhorn mr-2 text-violet-400"></i>Campaign Settings</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                   :class="open === 'campaigns' ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open === 'campaigns'" x-collapse class="px-4 py-3 bg-white">
                                <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                    <li>These control how campaigns are sent, <span class="font-semibold">system-wide</span>.</li>
                                    <li><span class="font-semibold">Allowed link domains</span> — a comma-separated list of domains allowed in campaign content links, e.g. <span class="font-mono text-xs">example.org, example.com</span>. Case-insensitive.</li>
                                    <li>Keep this list deliberate — it's a safeguard on what links can go out to members and volunteers.</li>
                                    <li><span class="font-semibold">Daily email sending cap</span> — maximum campaign emails sent per day before pausing.</li>
                                    <li><span class="font-semibold">Daily SMS sending cap</span> — maximum campaign SMS sent per day before pausing.</li>
                                </ul>
                            </div>
                        </div>

                        {{-- Signatures & Documents --}}
                        <div class="rounded-md border border-gray-200 overflow-hidden">
                            <button type="button"
                                    @click="open = open === 'signatures' ? null : 'signatures'"
                                    class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                <span><i class="fas fa-signature mr-2 text-amber-400"></i>Signatures &amp; Documents</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                   :class="open === 'signatures' ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open === 'signatures'" x-collapse class="px-4 py-3 bg-white">
                                <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                    <li><span class="font-semibold">ID Card Signature</span> — upload a PNG with a transparent background. Used as the Secretary General's signature on printed ID cards. The filename is fixed — uploading a new file replaces the existing one.</li>
                                    <li><span class="font-semibold">Signature Images</span> — used on certificates, as pre-printed signatures to save time. PNG only, transparent background. Use a descriptive filename, like <span class="font-mono text-xs">charles-smith-signature.png</span>.</li>
                                    <li><span class="font-semibold">Signature Titles</span> — a list of titles shown on certificates, for example Branch Chairman or Branch Health Coordinator.</li>
                                </ul>
                            </div>
                        </div>

                        {{-- Operational Settings --}}
                        <div class="rounded-md border border-gray-200 overflow-hidden">
                            <button type="button"
                                    @click="open = open === 'operational' ? null : 'operational'"
                                    class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                <span><i class="fas fa-cogs mr-2 text-sky-400"></i>Operational Settings</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                   :class="open === 'operational' ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open === 'operational'" x-collapse class="px-4 py-3 bg-white">
                                <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                    <li><span class="font-semibold">Membership Fees</span> — change fee amounts, or add new membership fee types.</li>
                                    <li><span class="font-semibold">Training Types</span> — manage the kinds of trainings, and mark whether certificates for a type are HQ-print only.</li>
                                </ul>
                            </div>
                        </div>

                        {{-- Campaign Purposes --}}
                        <div class="rounded-md border border-gray-200 overflow-hidden">
                            <button type="button"
                                    @click="open = open === 'purposes' ? null : 'purposes'"
                                    class="w-full flex items-center justify-between px-3 py-2 bg-gray-50 hover:bg-gray-100 text-left font-semibold text-gray-700 text-sm">
                                <span><i class="fas fa-envelope-open-text mr-2 text-green-400"></i>Campaign Purposes</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400 transition-transform"
                                   :class="open === 'purposes' ? 'rotate-180' : ''"></i>
                            </button>
                            <div x-show="open === 'purposes'" x-collapse class="px-4 py-3 bg-white">
                                <ul class="space-y-1 text-gray-700 list-disc pl-4">
                                    <li>These are the default message templates behind every campaign.</li>
                                    <li>Each purpose — like <span class="font-semibold">Membership Pre-Expiry Notice</span>, <span class="font-semibold">Training Invitation</span>, or <span class="font-semibold">Welcome &amp; Onboarding</span> — has a default email subject, email body, and SMS body.</li>
                                    <li>These are pre-filled when a branch selects a purpose in the campaign wizard — saving them from writing a message from scratch.</li>
                                </ul>
                            </div>
                        </div>

                    </div>{{-- end accordion --}}
                </div>{{-- end max-w-3xl --}}

            </x-help-popup>
        </div>

        <div class="space-y-8">

            {{-- National Database Settings — most sensitive, given prominence --}}
            <div class="bg-white shadow-lg rounded-lg overflow-hidden border-2 border-indigo-200">
                <div class="px-6 py-4 bg-indigo-50 border-b border-indigo-100">
                    <h2 class="text-lg font-semibold text-indigo-900">
                        <i class="fas fa-database mr-2"></i>National Database Settings
                    </h2>
                </div>
                <div class="px-6 py-5">
                    <a href="{{ route('admin.settings.edit') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded inline-flex items-center">
                        <i class="fas fa-sliders-h mr-2"></i>Open National Database Settings
                    </a>
                </div>
            </div>

            {{-- Signatures & Documents --}}
            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">
                        <i class="fas fa-signature mr-2 text-gray-500"></i>Signatures &amp; Documents
                    </h2>
                </div>
                <div class="px-6 py-5">
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('admin.settings.id-signature.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                            <i class="fas fa-pen-nib mr-1"></i>ID Card Signature
                        </a>
                        <a href="{{ route('admin.settings.signatures.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                            <i class="fas fa-signature mr-1"></i>Signature Images
                        </a>
                        <a href="{{ route('signature-titles.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                            <i class="fas fa-signature mr-1"></i>Signature Titles
                        </a>
                    </div>
                </div>
            </div>

            {{-- Operational Settings --}}
            <div class="bg-white shadow-md rounded-lg overflow-hidden">
                <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                    <h2 class="text-lg font-medium text-gray-900">
                        <i class="fas fa-cogs mr-2 text-gray-500"></i>Operational Settings
                    </h2>
                </div>
                <div class="px-6 py-5">
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('membership-fees.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                            <i class="fas fa-cog mr-1"></i>Membership Fees
                        </a>
                        <a href="{{ route('training-types.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                            <i class="fas fa-graduation-cap mr-1"></i>Training Types
                        </a>
                        <a href="{{ route('admin.settings.campaign-purposes.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                            <i class="fas fa-bullhorn mr-1"></i>Campaign Purposes
                        </a>
                        <a href="{{ route('task-force-types.index') }}" class="bg-gray-600 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                            <i class="fas fa-users-gear mr-1"></i>Task Force Types
                        </a>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-layouts.admin>
