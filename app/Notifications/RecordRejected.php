<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app (database channel only) notification sent to the submitter of a record
 * when an approver rejects it. Carries the module, the record id, and the reason.
 *
 */
class RecordRejected extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $module,
        public readonly int|string $recordId,
        public readonly string $reason,
    ) {}

    /**
     * @return string[]
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'record_rejected',
            'module' => $this->module,
            'record_id' => $this->recordId,
            'reason' => $this->reason,
            'message' => sprintf(
                'Your %s record #%s was rejected. Reason: %s',
                $this->module,
                $this->recordId,
                $this->reason
            ),
        ];
    }
}
