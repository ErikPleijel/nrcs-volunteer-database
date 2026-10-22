<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class UnsubscribeController extends Controller
{
    public function showEmail(string $token)
    {
        return $this->show($token, 'email');
    }

    public function handleEmail(Request $request, string $token)
    {
        $user = $this->findUser($token) ?? abort(404);

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
        return $this->show($token, 'sms');
    }

    public function handleSms(Request $request, string $token)
    {
        $user = $this->findUser($token) ?? abort(404);

        if ($request->input('action') === 'optout') {
            $user->update(['sms_opt_out' => true, 'sms_opt_out_at' => now()]);
            $flash = 'You have been unsubscribed from SMS campaigns.';
        } else {
            $user->update(['sms_opt_out' => false, 'sms_opt_out_at' => null]);
            $flash = 'You have been re-subscribed to SMS campaigns.';
        }

        return redirect()->route('unsubscribe.sms.show', $token)->with('success', $flash);
    }

    /** Unknown token: the page's "invalid link" message, with a 404 status. */
    private function show(string $token, string $channel)
    {
        $user = $this->findUser($token);

        return response()->view('unsubscribe.show', [
            'user'     => $user,
            'channel'  => $channel,
            'token'    => $token,
            'optedOut' => (bool) $user?->{$channel.'_opt_out'},
        ], $user ? 200 : 404);
    }

    /**
     * Links sent since optout_token was added carry it; older messages carry
     * id_check_token, which must keep working.
     */
    private function findUser(string $token): ?User
    {
        return User::where('optout_token', $token)
            ->orWhere('id_check_token', $token)
            ->first();
    }
}
