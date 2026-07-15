<div class="flex items-center gap-2 mt-3">
    <select class="form-input py-1 text-sm">
        <option value="">Insert placeholder…</option>
        @foreach($placeholders as $token => $label)
            <option value="{{ $token }}">{{ $label }}</option>
        @endforeach
    </select>

    <button type="button"
            data-insert-placeholder
            class="rounded-md bg-gray-100 px-3 py-1 text-xs font-semibold">
        Insert
    </button>
</div>
