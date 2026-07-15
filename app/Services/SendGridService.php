<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendGridService
{
    protected $apiKey;
    protected $fromEmail;
    protected $fromName;

    public function __construct()
    {
        $this->apiKey = env('SENDGRID_API_KEY');
        $this->fromEmail = env('MAIL_FROM_ADDRESS');
        $this->fromName = env('MAIL_FROM_NAME');
    }

    /**
     * Sends a single email through SendGrid (or logs it if not configured).
     *
     * @param string $to The recipient's email address.
     * @param string $subject The email subject.
     * @param string $htmlContent The HTML content of the email.
     * @param string|null $textContent Optional plain text content of the email.
     * @return bool True if the email was "sent" (or logged) successfully, false otherwise.
     */
    public function sendEmail($to, $subject, $htmlContent, $textContent = null)
    {
        // Check if SendGrid API Key is configured. If not, log the email instead of sending.
        if (empty($this->apiKey)) {
            Log::info('SendGridService: SendGrid API Key is not set. Logging email instead.', [
                'to' => $to,
                'subject' => $subject,
                'from_email' => $this->fromEmail,
                'from_name' => $this->fromName,
                'html_content_length' => strlen($htmlContent),
                'text_content_length' => $textContent ? strlen($textContent) : 0,
            ]);
            return true; // Simulate success for logging
        }

        Log::info('SendGridService: Preparing to send single email', [
            'to' => $to,
            'subject' => $subject,
            'from_email' => $this->fromEmail,
            'has_api_key' => !empty($this->apiKey)
        ]);

        if (empty($this->fromEmail)) {
            Log::error('SendGridService: MAIL_FROM_ADDRESS is not set, even though API Key is. Single email not sent.', [
                'to' => $to,
                'subject' => $subject
            ]);
            return false;
        }

        try {
            $payload = [
                'personalizations' => [
                    [
                        'to' => [
                            [
                                'email' => $to,
                            ]
                        ],
                        'subject' => $subject,
                    ]
                ],
                'from' => [
                    'email' => $this->fromEmail,
                    'name' => $this->fromName,
                ],
                'content' => [
                    [
                        'type' => 'text/html',
                        'value' => $htmlContent,
                    ]
                ]
            ];

            // Add plain text content if provided
            if ($textContent) {
                $payload['content'][] = [
                    'type' => 'text/plain',
                    'value' => $textContent,
                ];
            }

            Log::debug('SendGridService: Sending request to SendGrid for single email', ['payload' => $payload]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.sendgrid.com/v3/mail/send', $payload);

            Log::info('SendGridService: SendGrid response for single email', [
                'status' => $response->status(),
                'successful' => $response->successful(),
                'body' => $response->body()
            ]);

            if ($response->failed()) {
                Log::error('SendGridService: Failed to send single email via SendGrid API', [
                    'status' => $response->status(),
                    'response_body' => $response->body(),
                    'to' => $to,
                    'subject' => $subject
                ]);
            }

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('SendGridService: Exception occurred while sending single email via SendGrid API', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'to' => $to,
                'subject' => $subject
            ]);
            return false;
        }
    }

    /**
     * Sends multiple emails through SendGrid by iterating and calling sendEmail for each.
     * This method is intentionally blocked for bulk emails from the composer.
     *
     * @param array $emails An array of email details (e.g., [['to' => '...', 'subject' => '...', 'body' => '...'], ...]).
     * @return bool False, as bulk sending is blocked.
     */
    public function sendEmailToMultiple(array $emails): bool
    {
        Log::warning('SendGridService: Bulk email functionality is currently blocked for Composer messages. No emails will be sent via sendEmailToMultiple.');
        // Optionally, you might log the emails that would have been sent for debugging
        // Log::debug('Blocked bulk emails:', ['emails' => $emails]);
        return false;
    }
}
