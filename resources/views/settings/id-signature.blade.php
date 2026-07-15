<x-layouts.admin :title="'Settings: Signature ID-Card'">
    <x-slot name="pageHeader">
        <i class="fas fa-signature mr-2 mb-6"></i>ID Card Signature
    </x-slot>
    <x-audit-notice />

    <div class="container mx-auto py-8 px-4 max-w-xl">
        @if (session('success'))
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p>{{ session('success') }}</p>
            </div>
        @endif

        <div class="bg-blue-50 border border-blue-200 text-blue-800 rounded-md p-4 mb-6 text-sm">
            <strong>This image is used as the Secretary General's signature on ID cards.</strong>
            Upload a PNG file with a transparent background.
            The filename is fixed — uploading a new file will replace the existing one.
        </div>

        <div class="bg-white shadow-md rounded-lg p-6 mb-6">
            <h2 class="text-base font-semibold text-gray-700 mb-3">Current signature</h2>
            @if ($signatureExists)
                <img src="{{ $signatureUrl }}" alt="SG Signature"
                     style="max-height:80px; background: repeating-conic-gradient(#e5e7eb 0% 25%, white 0% 50%) 0 0 / 12px 12px;">
            @else
                <p class="text-sm text-gray-500">No signature uploaded yet.</p>
            @endif
        </div>

        <div class="bg-white shadow-md rounded-lg p-6">
            <h2 class="text-base font-semibold text-gray-700 mb-4">Upload / replace signature</h2>
            <form action="{{ route('admin.settings.id-signature.store') }}" method="POST" enctype="multipart/form-data" class="flex items-center gap-4">
                @csrf
                <input type="file" name="signature" accept=".png" required
                       class="block text-sm text-gray-700 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:text-sm file:font-semibold file:bg-gray-100 file:text-gray-700 hover:file:bg-gray-200">
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-sm font-semibold rounded shadow">
                    Upload / replace signature
                </button>
            </form>
            @error('signature')
                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

    </div>
</x-layouts.admin>
