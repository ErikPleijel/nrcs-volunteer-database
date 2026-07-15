<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Map a RecordRejected module key to its route-name prefix so we can
     * deep-link to the scope-lifting review page.
     */
    private array $moduleRoutes = [
        'donation' => 'donations',
        'payment' => 'membership-payments',
        'activity' => 'activities',
        'training' => 'trainings',
    ];

    /**
     * Mark a single notification read and deep-link to its target record.
     */
    public function read(string $id): RedirectResponse
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        $data = $notification->data;

        if (($data['type'] ?? null) === 'record_rejected') {
            $prefix = $this->moduleRoutes[$data['module'] ?? ''] ?? null;
            if ($prefix && ! empty($data['record_id'])) {
                return redirect()->route($prefix.'.review', $data['record_id']);
            }
        }

        if (($data['type'] ?? null) === 'campaign_decided') {
            if (! empty($data['campaign_id'])) {
                return redirect()->route('campaigns.mine.show', $data['campaign_id']);
            }
        }

        return redirect()->back();
    }

    /**
     * Mark all of the current user's unread notifications read.
     */
    public function readAll(): RedirectResponse
    {
        Auth::user()->unreadNotifications->markAsRead();

        return redirect()->back();
    }
}
