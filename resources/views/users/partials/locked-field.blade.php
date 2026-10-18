{{-- A personal field the person manages themselves (users/edit on a self-managed
     account): shown as text, never submitted. Params: $label, $value. --}}
<div>
    <span class="form-label">{{ $label }}</span>
    <p class="mt-1 px-3 py-2 rounded-md bg-gray-50 border border-gray-200 text-gray-700 whitespace-pre-line min-h-[2.5rem]">{{ filled($value) ? $value : '—' }}</p>
</div>
