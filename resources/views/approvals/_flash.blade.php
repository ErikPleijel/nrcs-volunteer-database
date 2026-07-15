@if (session('success'))
    <div class="mb-5 flex items-center gap-3 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
        <i class="fas fa-check-circle text-green-500"></i>
        {{ session('success') }}
    </div>
@endif
@if (session('warning'))
    <div class="mb-5 flex items-center gap-3 rounded-lg border border-yellow-200 bg-yellow-50 px-4 py-3 text-sm text-yellow-800">
        <i class="fas fa-triangle-exclamation text-yellow-500"></i>
        {{ session('warning') }}
    </div>
@endif
@if (session('error'))
    <div class="mb-5 flex items-center gap-3 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <i class="fas fa-circle-exclamation text-red-500"></i>
        {{ session('error') }}
    </div>
@endif
