<x-layouts.admin>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-gray-900">Create Campaign Request</h1>
            <p class="mt-1 text-sm text-gray-600">
                Draft a message and submit it for approval. Your scope is applied automatically.
                @if(!empty($prefillLifecycle))
                    <span class="ml-1">Lifecycle: <span class="font-semibold">{{ $prefillLifecycle }}</span></span>
                @endif
            </p>
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-md bg-green-50 p-4 text-sm text-green-800 border border-green-200">
                {{ session('success') }}
            </div>
        @endif

        @if($errors->any())
            <div class="mb-4 rounded-md bg-red-50 p-4 text-sm text-red-800 border border-red-200">
                <ul class="list-disc pl-5 space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="rounded-lg border border-gray-200 bg-white p-6">
            <form method="POST" action="{{ route('campaigns.store') }}" class="space-y-5">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700">Title (internal)</label>
                        <input name="title" value="{{ old('title') }}"
                               class="mt-1 w-full rounded-md border-gray-300 focus:border-slate-500 focus:ring-slate-500"
                               placeholder="e.g. Dormant re-engagement (Dec)">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Channel</label>
                        <select name="channel"
                                class="mt-1 w-full rounded-md border-gray-300 focus:border-slate-500 focus:ring-slate-500"
                                required>
                            <option value="email" @selected(old('channel')==='email')>Email</option>
                            <option value="sms" @selected(old('channel')==='sms')>SMS</option>
                            <option value="both" @selected(old('channel')==='both')>Email + SMS</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Audience type</label>
                        <select name="audience_type"
                                class="mt-1 w-full rounded-md border-gray-300 focus:border-slate-500 focus:ring-slate-500"
                                required>
                            <option value="member" @selected(old('audience_type')==='member')>Member</option>
                            <option value="volunteer" @selected(old('audience_type')==='volunteer')>Volunteer</option>
                            <option value="both" @selected(old('audience_type')==='both')>Both</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Lifecycle</label>
                        <select name="lifecycle"
                                class="mt-1 w-full rounded-md border-gray-300 focus:border-slate-500 focus:ring-slate-500">
                            <option value="">(optional)</option>
                            @foreach(['awaiting_assignment','active','dormant','archived'] as $lc)
                                <option value="{{ $lc }}" @selected(old('lifecycle', $prefillLifecycle) === $lc)>
                                    {{ str_replace('_',' ', ucfirst($lc)) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700">Email subject</label>
                        <input name="subject" value="{{ old('subject') }}"
                               class="mt-1 w-full rounded-md border-gray-300 focus:border-slate-500 focus:ring-slate-500"
                               placeholder="(Email only)">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700">Message body</label>
                    <textarea name="body" rows="8" required
                              class="mt-1 w-full rounded-md border-gray-300 focus:border-slate-500 focus:ring-slate-500"
                              placeholder="Write your email (HTML allowed) or SMS text...">{{ old('body') }}</textarea>
                    <p class="mt-2 text-xs text-gray-500">
                        Tip: keep one clear next step. Be respectful and low-pressure for dormant outreach.
                    </p>
                </div>

                {{-- IMPORTANT: Replace this with your real filter builder output.
                     For now we store a minimal placeholder so validation passes. --}}
                <input type="hidden" name="filter_json[dummy]" value="1">

                <div class="flex items-center justify-between gap-3">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="submit_now" value="1"
                               class="rounded border-gray-300 text-slate-700 focus:ring-slate-600">
                        Submit for approval now
                    </label>

                    <button type="submit"
                            class="inline-flex items-center justify-center rounded-md bg-slate-700 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-slate-800">
                        Save campaign request
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-layouts.admin>
