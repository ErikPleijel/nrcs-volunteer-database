@props(['url'])

{{-- Submitter-only withdraw of an own PENDING record. Native confirm dialog. --}}
<form method="POST" action="{{ $url }}" class="inline" x-data>
    @csrf
    <button type="button"
            @click="if (confirm('Withdraw this pending record? This permanently removes your submission.')) $root.submit()"
            class="inline-flex items-center text-xs font-medium text-red-600 hover:text-red-800">
        <i class="fas fa-rotate-left mr-1"></i>Withdraw
    </button>
</form>
