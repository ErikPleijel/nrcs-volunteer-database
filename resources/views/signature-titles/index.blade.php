<x-layouts.admin>
    <x-slot name="title">Signature Titles</x-slot>

    <x-slot name="pageHeader">
        <i class="fas fa-signature mr-3 mb-6"></i> Signature Titles
    </x-slot>
    <x-audit-notice />



    <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-md p-4 mb-6 text-sm">
        <strong>These titles can be shown on certificates.</strong>
    </div>


    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            {{ session('error') }}
        </div>
    @endif

    {{-- Add New Title --}}
    <div class="bg-white shadow-md rounded-lg p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-800 mb-4">Add New Title</h2>
        <form method="POST" action="{{ route('signature-titles.store') }}" class="flex items-start gap-3">
            @csrf
            <div class="flex-1 max-w-sm">
                <input type="text"
                       name="name"
                       value="{{ old('name') }}"
                       maxlength="60"
                       placeholder="e.g. Secretary General"
                       class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 @error('name') border-red-500 @enderror">
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <button type="submit" class="btn-primary whitespace-nowrap">
                Add Title
            </button>
        </form>
    </div>

    {{-- Existing Titles --}}
    <div class="bg-white shadow-md rounded-lg overflow-hidden">
        <table class="min-w-full table-auto">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider w-40">Actions</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                @forelse($titles as $title)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm text-gray-900">
                            <span id="name-display-{{ $title->id }}">{{ $title->name }}</span>

                            <form id="edit-form-{{ $title->id }}"
                                  method="POST"
                                  action="{{ route('signature-titles.update', $title) }}"
                                  class="hidden items-center gap-2">
                                @csrf
                                @method('PUT')
                                <input type="text"
                                       name="name"
                                       value="{{ $title->name }}"
                                       maxlength="60"
                                       class="border border-gray-300 rounded-md px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 w-56">
                                <button type="submit" class="btn-primary text-xs py-1 px-2">Save</button>
                                <button type="button"
                                        onclick="cancelEdit({{ $title->id }})"
                                        class="text-xs text-gray-500 hover:text-gray-700 underline">Cancel</button>
                            </form>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex items-center gap-2">
                                <button type="button"
                                        id="edit-btn-{{ $title->id }}"
                                        onclick="startEdit({{ $title->id }})"
                                        class="btn-primary text-xs py-1 px-3">
                                    Edit
                                </button>
                                <form method="POST"
                                      action="{{ route('signature-titles.destroy', $title) }}"
                                      onsubmit="return confirm('Delete \'{{ addslashes($title->name) }}\'? This cannot be undone.')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="bg-red-600 hover:bg-red-700 text-white text-xs font-bold py-1 px-3 rounded">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="px-6 py-6 text-center text-gray-500 italic text-sm">
                            No signature titles yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <script>
        function startEdit(id) {
            document.getElementById('name-display-' + id).style.display = 'none';
            document.getElementById('edit-btn-' + id).style.display = 'none';
            var form = document.getElementById('edit-form-' + id);
            form.classList.remove('hidden');
            form.classList.add('flex');
            form.querySelector('input[name="name"]').focus();
        }

        function cancelEdit(id) {
            document.getElementById('name-display-' + id).style.display = '';
            document.getElementById('edit-btn-' + id).style.display = '';
            var form = document.getElementById('edit-form-' + id);
            form.classList.add('hidden');
            form.classList.remove('flex');
        }
    </script>
</x-layouts.admin>
