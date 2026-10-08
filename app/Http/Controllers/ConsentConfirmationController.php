<?php

namespace App\Http\Controllers;

use App\Models\Log as AuditLog;
use Illuminate\Http\Request;

/**
 * One-time Code of Conduct + NDPA consent confirmation for users with no
 * recorded acceptance (legacy-imported and staff-registered accounts).
 * EnsureConsentConfirmed sends them here; see Decisions.md 2026-10-08.
 */
class ConsentConfirmationController extends Controller
{
    /** Where to go when there is no intended URL (same as LoginController). */
    private const FALLBACK = '/profile';

    public function show(Request $request)
    {
        if ($request->user()->code_of_conduct_accepted_at !== null) {
            return redirect()->intended(self::FALLBACK);
        }

        return view('consent.confirm');
    }

    public function store(Request $request)
    {
        $request->validate([
            'coc_commitment_1' => ['accepted'],
            'coc_commitment_2' => ['accepted'],
            'coc_commitment_3' => ['accepted'],
            'coc_commitment_4' => ['accepted'],
        ], [], [
            'coc_commitment_1' => 'Code of Conduct confirmation 1',
            'coc_commitment_2' => 'Code of Conduct confirmation 2',
            'coc_commitment_3' => 'Code of Conduct confirmation 3',
            'coc_commitment_4' => 'NDPA data consent',
        ]);

        $user = $request->user();

        $fields = ['code_of_conduct_accepted_at', 'consent_obtained_at', 'consent_obtained_by_id', 'consent_notes'];
        $old = collect($fields)->mapWithKeys(fn ($f) => [$f => $this->auditValue($user->$f)])->all();

        $user->code_of_conduct_accepted_at = now();
        $user->consent_obtained_at = now();
        $user->consent_obtained_by_id = $user->id;
        $user->consent_notes = 'Confirmed by user at login';
        $user->save();

        $new = collect($fields)->mapWithKeys(fn ($f) => [$f => $this->auditValue($user->$f)])->all();

        AuditLog::write(
            'consent_confirmed',
            $user,
            ['branch_id' => $user->branch_id, 'division_id' => $user->division_id],
            $old,
            $new,
            "DB-{$user->id} confirmed the Code of Conduct and NDPA consent."
        );

        return redirect()->intended(self::FALLBACK);
    }

    private function auditValue($value)
    {
        return $value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value;
    }
}
