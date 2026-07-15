<?php

namespace App\Http\Controllers;

use App\Services\SendGridService;
use Illuminate\Http\Request;

class EmailController extends Controller
{
    protected $sendGrid;

    public function __construct(SendGridService $sendGrid)
    {
        $this->sendGrid = $sendGrid;
    }

    public function sendNotification(Request $request)
    {
        $success = $this->sendGrid->sendEmail(
            $request->email,
            'Notification from Red Cross Volunteers',
            '<h2>Hello!</h2><p>This is your notification message.</p>'
        );

        if ($success) {
            return back()->with('success', 'Email sent successfully!');
        } else {
            return back()->with('error', 'Failed to send email.');
        }
    }

    public function sendBulkEmails(Request $request)
    {
        $recipients = ['email1@example.com', 'email2@example.com'];

        $success = $this->sendGrid->sendEmailToMultiple(
            $recipients,
            'Bulk Notification',
            '<h2>Group Message</h2><p>This message was sent to multiple recipients.</p>'
        );

        return $success ? 'Bulk emails sent!' : 'Bulk email failed!';
    }
}
