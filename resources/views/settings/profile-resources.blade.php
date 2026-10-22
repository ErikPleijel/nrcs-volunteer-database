<x-layouts.admin title="Useful Links & Downloads">
    <x-slot name="pageHeader">
        <i class="fas fa-link mr-3"></i> Useful Links &amp; Downloads
    </x-slot>
    <x-audit-notice />

    <x-slot name="subHeader">Settings — Shown at the bottom of every My Profile page</x-slot>

    @php
        // "links.2.title" => "Useful link 3", for the error summary.
        $rowLabel = function (string $key): string {
            if (preg_match('/^(links|downloads)\.(\d+)\./', $key, $m)) {
                return ($m[1] === 'links' ? 'Useful link ' : 'Download ').($m[2] + 1);
            }

            return 'Form';
        };
        $fieldClass = 'w-full rounded-md border border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500';
        $iconButton = 'inline-flex items-center justify-center w-8 h-8 rounded-md border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed';
    @endphp

    <div class="container mx-auto px-4 py-8 max-w-5xl">

        @if (session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p class="font-bold">Success</p>
                <p>{{ session('success') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p class="font-bold">Not saved — please correct the following</p>
                <ul class="list-disc pl-5 text-sm mt-1">
                    @foreach ($errors->getMessages() as $key => $messages)
                        @foreach ($messages as $message)
                            <li>{{ $rowLabel($key) }}: {{ $message }}</li>
                        @endforeach
                    @endforeach
                </ul>
                @if (collect(old('downloads', []))->contains(fn ($row) => ($row['source'] ?? null) === 'file'))
                    <p class="text-sm mt-2">Files you had just chosen were not uploaded — please choose them again.</p>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('admin.settings.profile-resources.update') }}" enctype="multipart/form-data"
              class="space-y-8"
              x-data="{ errors: @js($errors->getMessages()) }">
            @csrf

            {{-- ── USEFUL LINKS ───────────────────────────────────────── --}}
            <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden"
                 x-data="{
                     rows: @js($links).map((row, i) => ({ ...row, uid: i })),
                     next: {{ count($links) }},
                     add() {
                         this.rows.push({ title: '', url: '', description: '', uid: this.next++ });
                     },
                     move(i, step) {
                         this.rows.splice(i + step, 0, this.rows.splice(i, 1)[0]);
                         this.errors = {};
                     },
                     remove(i) {
                         this.rows.splice(i, 1);
                         this.errors = {};
                     },
                 }">
                <div class="bg-gray-50 border-b border-gray-200 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-arrow-up-right-from-square mr-2 text-gray-500"></i>Useful links
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Websites shown as cards under <span class="font-semibold">Useful links</span> on everyone's My Profile page, in this order.
                        Each needs a title and a full web address starting with <span class="font-mono text-xs">https://</span>.
                        If the list is empty, the section is hidden.
                    </p>
                </div>

                <div class="px-6 py-5 space-y-4">
                    <p x-show="rows.length === 0" class="text-sm text-gray-500 italic">No links. The Useful links section is hidden on My Profile.</p>

                    <template x-for="(row, i) in rows" :key="row.uid">
                        <div class="rounded-lg border border-gray-200 p-4" data-link-row>
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm font-semibold text-gray-700" x-text="'Link ' + (i + 1)"></span>
                                <div class="flex gap-1">
                                    <button type="button" class="{{ $iconButton }}" @click="move(i, -1)" :disabled="i === 0" title="Move up" aria-label="Move up">
                                        <i class="fas fa-arrow-up text-xs"></i>
                                    </button>
                                    <button type="button" class="{{ $iconButton }}" @click="move(i, 1)" :disabled="i === rows.length - 1" title="Move down" aria-label="Move down">
                                        <i class="fas fa-arrow-down text-xs"></i>
                                    </button>
                                    <button type="button" class="{{ $iconButton }} hover:text-red-600 hover:border-red-300" @click="remove(i)" title="Delete" aria-label="Delete">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                                    <input type="text" maxlength="80" required class="{{ $fieldClass }}"
                                           :name="'links[' + i + '][title]'" x-model="row.title"
                                           placeholder="e.g. IFRC Learning Platform">
                                    <p class="mt-1 text-xs text-red-600" x-show="errors['links.' + i + '.title']" x-text="errors['links.' + i + '.title']?.[0]"></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Web address</label>
                                    <input type="url" maxlength="2048" required class="{{ $fieldClass }}"
                                           :name="'links[' + i + '][url]'" x-model="row.url" placeholder="https://…">
                                    <p class="mt-1 text-xs text-red-600" x-show="errors['links.' + i + '.url']" x-text="errors['links.' + i + '.url']?.[0]"></p>
                                </div>
                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Short description <span class="text-xs text-gray-400 font-normal">(optional)</span>
                                    </label>
                                    <input type="text" maxlength="200" class="{{ $fieldClass }}"
                                           :name="'links[' + i + '][description]'" x-model="row.description">
                                    <p class="mt-1 text-xs text-red-600" x-show="errors['links.' + i + '.description']" x-text="errors['links.' + i + '.description']?.[0]"></p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <button type="button" @click="add()" class="inline-flex items-center px-4 py-2 rounded-md border border-indigo-300 bg-indigo-50 text-indigo-700 text-sm font-semibold hover:bg-indigo-100">
                        <i class="fas fa-plus mr-2"></i>Add link
                    </button>
                </div>
            </div>

            {{-- ── DOWNLOADS ──────────────────────────────────────────── --}}
            <div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden"
                 x-data="{
                     rows: @js($downloads).map((row, i) => ({ ...row, uid: i })),
                     next: {{ count($downloads) }},
                     add() {
                         this.rows.push({ id: '', title: '', source: 'file', url: '', description: '', file_name: null, uid: this.next++ });
                     },
                     move(i, step) {
                         this.rows.splice(i + step, 0, this.rows.splice(i, 1)[0]);
                         this.errors = {};
                     },
                     remove(i) {
                         this.rows.splice(i, 1);
                         this.errors = {};
                     },
                 }">
                <div class="bg-gray-50 border-b border-gray-200 px-6 py-4">
                    <h2 class="text-lg font-semibold text-gray-900">
                        <i class="fas fa-download mr-2 text-gray-500"></i>Downloads
                    </h2>
                    <p class="text-sm text-gray-600 mt-1">
                        Documents shown under <span class="font-semibold">Downloads</span> on My Profile, e.g. the Volunteer Diary.
                        Upload a <span class="font-semibold">PDF, Word (.docx) or Excel (.xlsx)</span> file of up to 20 MB, or link to a document on another website.
                        Only logged-in users can open uploaded files. If the list is empty, the section is hidden.
                    </p>
                </div>

                <div class="px-6 py-5 space-y-4">
                    <p x-show="rows.length === 0" class="text-sm text-gray-500 italic">No downloads. The Downloads section is hidden on My Profile.</p>

                    <template x-for="(row, i) in rows" :key="row.uid">
                        <div class="rounded-lg border border-gray-200 p-4" data-download-row>
                            <input type="hidden" :name="'downloads[' + i + '][id]'" :value="row.id">

                            <div class="flex items-center justify-between mb-3">
                                <span class="text-sm font-semibold text-gray-700" x-text="'Download ' + (i + 1)"></span>
                                <div class="flex gap-1">
                                    <button type="button" class="{{ $iconButton }}" @click="move(i, -1)" :disabled="i === 0" title="Move up" aria-label="Move up">
                                        <i class="fas fa-arrow-up text-xs"></i>
                                    </button>
                                    <button type="button" class="{{ $iconButton }}" @click="move(i, 1)" :disabled="i === rows.length - 1" title="Move down" aria-label="Move down">
                                        <i class="fas fa-arrow-down text-xs"></i>
                                    </button>
                                    <button type="button" class="{{ $iconButton }} hover:text-red-600 hover:border-red-300" @click="remove(i)" title="Delete" aria-label="Delete">
                                        <i class="fas fa-trash text-xs"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                                    <input type="text" maxlength="80" required class="{{ $fieldClass }}"
                                           :name="'downloads[' + i + '][title]'" x-model="row.title" placeholder="e.g. Volunteer Diary">
                                    <p class="mt-1 text-xs text-red-600" x-show="errors['downloads.' + i + '.title']" x-text="errors['downloads.' + i + '.title']?.[0]"></p>
                                </div>

                                <div>
                                    <span class="block text-sm font-medium text-gray-700 mb-1">Where is the document?</span>
                                    <div class="flex flex-wrap gap-4 text-sm text-gray-700 py-2">
                                        <label class="inline-flex items-center gap-2">
                                            <input type="radio" value="file" x-model="row.source" :name="'downloads[' + i + '][source]'" class="text-indigo-600 focus:ring-indigo-500">
                                            Upload a file
                                        </label>
                                        <label class="inline-flex items-center gap-2">
                                            <input type="radio" value="url" x-model="row.source" :name="'downloads[' + i + '][source]'" class="text-indigo-600 focus:ring-indigo-500">
                                            Link to a web address
                                        </label>
                                    </div>
                                </div>

                                <div class="md:col-span-2" x-show="row.source === 'file'">
                                    <p class="text-sm text-gray-700 mb-1" x-show="row.file_name">
                                        <i class="fas fa-paperclip mr-1 text-gray-400"></i>Current file: <span class="font-semibold" x-text="row.file_name"></span>
                                        <span class="text-xs text-gray-500">— choose a new file only to replace it.</span>
                                    </p>
                                    <input type="file" accept=".pdf,.docx,.xlsx"
                                           :name="'downloads[' + i + '][file]'"
                                           class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-indigo-50 file:px-3 file:py-2 file:text-sm file:font-semibold file:text-indigo-700 hover:file:bg-indigo-100">
                                    <p class="mt-1 text-xs text-gray-500">PDF, Word (.docx) or Excel (.xlsx), up to 20 MB.</p>
                                    <p class="mt-1 text-xs text-red-600" x-show="errors['downloads.' + i + '.file']" x-text="errors['downloads.' + i + '.file']?.[0]"></p>
                                </div>

                                <div class="md:col-span-2" x-show="row.source === 'url'">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Web address</label>
                                    <input type="url" maxlength="2048" class="{{ $fieldClass }}"
                                           :name="'downloads[' + i + '][url]'" x-model="row.url" :required="row.source === 'url'" placeholder="https://…">
                                    <p class="mt-1 text-xs text-red-600" x-show="errors['downloads.' + i + '.url']" x-text="errors['downloads.' + i + '.url']?.[0]"></p>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Short description <span class="text-xs text-gray-400 font-normal">(optional)</span>
                                    </label>
                                    <input type="text" maxlength="200" class="{{ $fieldClass }}"
                                           :name="'downloads[' + i + '][description]'" x-model="row.description">
                                    <p class="mt-1 text-xs text-red-600" x-show="errors['downloads.' + i + '.description']" x-text="errors['downloads.' + i + '.description']?.[0]"></p>
                                </div>
                            </div>
                        </div>
                    </template>

                    <button type="button" @click="add()" class="inline-flex items-center px-4 py-2 rounded-md border border-indigo-300 bg-indigo-50 text-indigo-700 text-sm font-semibold hover:bg-indigo-100">
                        <i class="fas fa-plus mr-2"></i>Add download
                    </button>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-4 pt-2">
                <p class="text-sm text-gray-500">Nothing changes on My Profile until you click Save.</p>
                <button type="submit"
                        class="inline-flex items-center px-6 py-3 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded-md shadow transition">
                    <i class="fas fa-save mr-2"></i> Save
                </button>
            </div>
        </form>

        <div class="mt-6">
            <a href="{{ route('admin.settings.index') }}" class="btn-backlink">← Back to Settings</a>
        </div>
    </div>
</x-layouts.admin>
