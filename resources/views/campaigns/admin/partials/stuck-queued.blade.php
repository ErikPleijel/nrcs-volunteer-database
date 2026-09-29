{{-- Recipients claimed by the send runner that never got a result recorded (at-most-once sending). --}}
@if (($stuckQueuedCount ?? 0) > 0)
    <div class="rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <strong>{{ number_format($stuckQueuedCount) }} recipient(s) stuck in “queued”</strong>
                for more than {{ \App\Models\MessagingRecipient::STUCK_QUEUED_MINUTES }} minutes.
                Sending started but no result was recorded, so they may or may not have received the message.
                They are never retried automatically.
            </div>
            <form method="POST" action="{{ route('campaigns.admin.recipients.failStuck', $campaign) }}"
                  onsubmit="return confirm('Mark {{ $stuckQueuedCount }} stuck recipient(s) as failed? They will not be retried, because the message may already have been sent.')">
                @csrf
                <button type="submit"
                        class="inline-flex items-center whitespace-nowrap px-3 py-1.5 rounded-md text-sm font-medium bg-amber-700 text-white hover:bg-amber-800">
                    Mark as failed
                </button>
            </form>
        </div>
    </div>
@endif
