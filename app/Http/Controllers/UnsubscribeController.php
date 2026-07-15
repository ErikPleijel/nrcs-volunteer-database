<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UnsubscribeController extends Controller
{
    public function showEmail(string $token)
    {
        $user = User::where('id_check_token', $token)->first();

        return view('unsubscribe.show', [
            'user'    => $user,
            'channel' => 'email',
            'token'   => $token,
            'optedOut' => $user?->email_opt_out ?? false,
        ]);
    }

    public function handleEmail(Request $request, string $token)
    {
        $user = User::where('id_check_token', $token)->firstOrFail();

        if ($request->input('action') === 'optout') {
            $user->update(['email_opt_out' => true, 'email_opt_out_at' => now()]);
            $flash = 'You have been unsubscribed from email campaigns.';
        } else {
            $user->update(['email_opt_out' => false, 'email_opt_out_at' => null]);
            $flash = 'You have been re-subscribed to email campaigns.';
        }

        return redirect()->route('unsubscribe.email.show', $token)->with('success', $flash);
    }

    public function showSms(string $token)
    {
        $user = User::where('id_check_token', $token)->first();

        return view('unsubscribe.show', [
            'user'    => $user,
            'channel' => 'sms',
            'token'   => $token,
            'optedOut' => $user?->sms_opt_out ?? false,
        ]);
    }

    public function handleSms(Request $request, string $token)
    {
        $user = User::where('id_check_token', $token)->firstOrFail();

        if ($request->input('action') === 'optout') {
            $user->update(['sms_opt_out' => true, 'sms_opt_out_at' => now()]);
            $flash = 'You have been unsubscribed from SMS campaigns.';
        } else {
            $user->update(['sms_opt_out' => false, 'sms_opt_out_at' => null]);
            $flash = 'You have been re-subscribed to SMS campaigns.';
        }

        return redirect()->route('unsubscribe.sms.show', $token)->with('success', $flash);
    }
}
