<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class SmsService
{
    protected $fromNumber;
    protected $apiKey; // Assuming some API key for SMS service

    public function __construct()
    {
        $this->fromNumber = env('SMS_FROM_NUMBER');
        $this->apiKey = env('SMS_API_KEY'); // Or other specific SMS credentials
    }

    /**
     * Sends a single SMS.
     * For now, this will just log the SMS if SMS credentials are not fully configured.
     * In a real application, you would integrate with an SMS API (e.g., Twilio, Nexmo).
     *
     * @param string $to The recipient's phone number.
     * @param string $body The SMS content.
     * @return bool True if SMS was "sent" (or logged) successfully, false otherwise.
     */
    public function sendSms(string $to, string $body): bool
    {
        // For demonstration, we'll just log the SMS if credentials are missing.
        // In a real application, you'd integrate with an SMS gateway.
        if (empty($this->fromNumber) || empty($this->apiKey)) {
            Log::info('SmsService: SMS credentials (e.g., SMS_FROM_NUMBER, SMS_API_KEY) not fully configured. Logging SMS instead.', [
                'to' => $to,
                'from' => $this->fromNumber,
                'body_length' => strlen($body),
            ]);
            return true; // Simulate success for logging
        }

        Log::info('SmsService: Attempting to send SMS via external API (e.g., Twilio, Nexmo).', [
            'to' => $to,
            'from' => $this->fromNumber,
            'body_length' => strlen($body),
        ]);

        // --- Placeholder for actual SMS API integration ---
        // Example using a hypothetical SMS API (replace with your actual API)
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post('https://api.your-sms-provider.com/v1/send', [ // Replace with actual endpoint
                'from' => $this->fromNumber,
                'to' => $to,
                'message' => $body,
            ]);

            if ($response->successful()) {
                Log::info('SmsService: SMS sent successfully.', ['to' => $to, 'response' => $response->body()]);
                return true;
            } else {
                Log::error('SmsService: Failed to send SMS.', ['to' => $to, 'status' => $response->status(), 'response' => $response->body()]);
                return false;
            }
        } catch (\Exception $e) {
            Log::error('SmsService: Exception occurred while sending SMS.', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'to' => $to
            ]);
            return false;
        }
        // --- End Placeholder ---
    }

    /**
     * Sends multiple SMS messages by iterating and calling sendSms for each.
     *
     * @param array $smsMessages An array of SMS details (e.g., [['to' => '...', 'body' => '...'], ...]).
     * @return bool True if all messages were dispatched successfully (or logged), false if any failed.
     */
    public function sendSmsToMultiple(array $smsMessages): bool
    {
        $allSuccessful = true;
        foreach ($smsMessages as $sms) {
            $success = $this->sendSms($sms['to'], $sms['body']);
            if (!$success) {
                $allSuccessful = false;
                Log::warning('SmsService: Individual SMS failed to be "sent" or logged in bulk operation.', [
                    'recipient' => $sms['to']
                ]);
            }
        }
        return $allSuccessful;
    }
}
