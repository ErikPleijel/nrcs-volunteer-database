@props(['triggerClass' => null])

@php $popupId = 'help-popup-' . uniqid(); @endphp

<div class="inline-block">

    {{-- Trigger button — custom when trigger slot is provided, icon-only otherwise --}}
    @if(isset($trigger) && trim((string) $trigger))
        <button
            type="button"

            onclick="document.getElementById('{{ $popupId }}').showModal()"
            class="{{ $triggerClass }}"
            aria-haspopup="dialog"
        >{{ $trigger }}</button>
    @else
        <button
            type="button"
            onclick="document.getElementById('{{ $popupId }}').showModal()"
            class="inline-flex items-center justify-center h-9 w-9 rounded-md bg-gray-100 hover:bg-gray-200 text-gray-600 hover:text-gray-800 border border-gray-300 transition"
            aria-haspopup="dialog"
        >
            <i class="fas fa-circle-question text-lg"></i>
        </button>
    @endif

    {{-- Native dialog --}}
    <dialog
        id="{{ $popupId }}"
        class="help-popup-dialog rounded-xl shadow-xl w-full max-w-md p-0 border-0"
        onclick="if(event.target===this)this.close()"
    >
        <div class="p-6">
            <div class="flex justify-end mb-2">
                <button
                    type="button"
                    onclick="this.closest('dialog').close()"
                    class="text-gray-400 hover:text-gray-600 transition"
                    aria-label="Close"
                >
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>

            {{-- Content --}}
            <div class="text-sm text-gray-700 space-y-2 text-left">
                {{ $slot }}
            </div>

            {{-- Footer --}}
            <div class="mt-6 flex justify-end">
                <button
                    type="button"
                    onclick="this.closest('dialog').close()"
                    class="inline-flex items-center px-4 py-2 rounded-md bg-gray-100 hover:bg-gray-200 text-sm font-medium text-gray-700 transition"
                >
                    Close
                </button>
            </div>
        </div>
    </dialog>

</div>
