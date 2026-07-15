<x-layouts.admin :title="'Settings: Signature Images'">

    <x-slot name="pageHeader">
        <i class="fas fa-signature mr-2 mb-6"></i>Signature Images
    </x-slot>
    <x-audit-notice />

    <div class="container mx-auto py-8 px-4 max-w-3xl">

        @if (session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p>{{ session('success') }}</p>
            </div>
        @endif

        @if (session('error'))
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p>{{ session('error') }}</p>
            </div>
        @endif

        <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-md p-4 mb-6 text-sm">
            <ul class="list-disc pl-4 space-y-1">
                <li><strong>These signatures can be shown on certificates.</strong></li>
                <li>PNG files only.</li>
                <li>The background must be transparent.</li>
                <li>Use a descriptive filename, e.g. <span class="font-mono">charles-smith-signature.png</span>.</li>
            </ul>
        </div>

        {{-- Upload form --}}
        <div class="bg-white shadow-md rounded-lg p-6 mb-8">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Upload a signature</h2>
            <form action="{{ route('admin.settings.signatures.store') }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-4">
                @csrf
                <input type="file" name="signature" accept=".png" required
                       class="block text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded shadow">
                    Upload
                </button>
            </form>
            @error('signature')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        {{-- Existing signatures --}}
        <div class="bg-white shadow-md rounded-lg overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800">Current signatures</h2>
            </div>

            @if (count($files) === 0)
                <p class="px-6 py-5 text-sm text-gray-500">No signatures uploaded yet.</p>
            @else
                <ul class="divide-y divide-gray-200">
                    @foreach ($files as $filename)
                        <li class="flex items-center justify-between px-6 py-4 gap-4">
                            <div class="flex items-center gap-4">
                                <img src="{{ asset('images/signatures/' . $filename) }}"
                                     alt="{{ $filename }}"
                                     style="max-height:60px; background: repeating-conic-gradient(#e5e7eb 0% 25%, white 0% 50%) 0 0 / 12px 12px;">
                                <span class="text-sm text-gray-700 font-mono">{{ $filename }}</span>
                            </div>

                            <form action="{{ route('admin.settings.signatures.destroy', $filename) }}" method="POST"
                                  onsubmit="return confirm('Delete {{ $filename }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="text-sm text-red-600 hover:text-red-800 font-medium">
                                    Delete
                                </button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</x-layouts.admin>
