@props(['status', 'rejectionReason', 'reviewUrl', 'withdrawUrl' => null])

<div>
    <x-approval-status-badge :status="$status" />
    @if($status === 'rejected' && $rejectionReason)
        <div class="text-xs text-red-600 mt-1"><i class="fas fa-comment-dots mr-1"></i>{{ $rejectionReason }}</div>
    @endif
</div>
<div class="mt-2 flex items-center gap-3">
    <a href="{{ $reviewUrl }}" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded text-xs font-medium transition-colors">
        View
    </a>
    @if($status === 'pending' && $withdrawUrl)
        <x-withdraw-button :url="$withdrawUrl" />
    @endif
</div>
