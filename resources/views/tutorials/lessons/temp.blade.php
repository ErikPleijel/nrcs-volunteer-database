{{-- Slide 7 — Deletion & soft delete (split) --}}
<x-tutorial.slide audio="tutorials/audio/level1-registering-deletion.mp3">
    <div class="max-w-3xl mx-auto text-center">
        <h2 class="text-2xl font-bold text-gray-900 mb-3" data-reveal>Deleting a record</h2>
        <p class="text-gray-600 mb-8" data-reveal>To remove a record, open it first — the Delete button lives at the bottom.</p>

        {{-- Path --}}
        <div class="flex flex-col sm:flex-row items-center justify-center gap-3 mb-8" data-reveal>
            <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-up-right-from-square text-indigo-400"></i> Click <strong>View</strong>
            </span>
            <i class="fas fa-arrow-right text-gray-300 hidden sm:block"></i>
            <span class="inline-flex items-center gap-2 rounded-full bg-gray-50 border border-gray-100 px-4 py-2 text-sm text-gray-700">
                <i class="fas fa-arrow-down text-indigo-400"></i> Scroll to the bottom
            </span>
            <i class="fas fa-arrow-right text-gray-300 hidden sm:block"></i>
            <span class="inline-flex items-center gap-2 rounded-full bg-red-50 border border-red-100 px-4 py-2 text-sm text-red-700">
                <i class="fas fa-trash-can"></i> Press <strong>Delete</strong>
            </span>
        </div>

        {{-- Reassurance cards --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-left">
            <div class="rounded-xl bg-gray-50 border border-gray-100 p-5" data-reveal>
                <i class="fas fa-box-archive text-2xl text-green-500 mb-2"></i>
                <p class="font-semibold text-gray-800">Hidden, not erased</p>
                <p class="text-sm text-gray-600">A deleted record is removed from the lists but kept in the database — nothing is truly lost.</p>
            </div>
            <div class="rounded-xl bg-gray-50 border border-gray-100 p-5" data-reveal>
                <i class="fas fa-clipboard-list text-2xl text-indigo-500 mb-2"></i>
                <p class="font-semibold text-gray-800">Every deletion is logged</p>
                <p class="text-sm text-gray-600">The system records who deleted the record, and when.</p>
            </div>
        </div>
    </div>
</x-tutorial.slide>
