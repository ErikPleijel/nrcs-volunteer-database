<?php

namespace App\Http\Controllers;

use App\Models\CampaignPurpose;
use App\Models\Log as AuditLog;
use Illuminate\Http\Request;

class CampaignPurposeSettingsController extends Controller
{
    public function index()
    {
        $this->authorizeAccess();

        $purposes = CampaignPurpose::orderBy('sort_order')->get();

        return view('settings.campaign-purposes', compact('purposes'));
    }

    public function update(Request $request)
    {
        $this->authorizeAccess();

        $data = $request->validate([
            'purposes'                          => ['required', 'array'],
            'purposes.*.id'                     => ['required', 'exists:campaign_purposes,id'],
            'purposes.*.default_subject'        => ['nullable', 'string', 'max:255'],
            'purposes.*.default_email_body'     => ['nullable', 'string', 'max:20000'],
            'purposes.*.default_sms_body'       => ['nullable', 'string', 'max:800'],
            'purposes.*.default_channel'        => ['required', 'in:email,email_fallback_sms,sms,both'],
            'purposes.*.default_call_window'    => ['nullable', 'boolean'],
            'purposes.*.sort_order'             => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        // Snapshot BEFORE the mass update — CampaignPurpose::where(...)->update() is a query-builder
        // update, so no Eloquent events fire and there's no dirty-tracking to rely on afterward.
        $originalPurposes = CampaignPurpose::whereIn('id', collect($data['purposes'])->pluck('id'))
            ->get()
            ->keyBy('id');

        foreach ($data['purposes'] as $row) {
            // Browsers normalize <textarea> line breaks to CRLF on submission, while
            // stored/seeded values use bare LF — normalize back to LF here so an
            // unedited textarea doesn't falsely diff against its own stored value.
            $defaultEmailBody = $row['default_email_body'] ?? null;
            if ($defaultEmailBody !== null) {
                $defaultEmailBody = str_replace(["\r\n", "\r"], "\n", $defaultEmailBody);
            }

            $defaultSmsBody = $row['default_sms_body'] ?? null;
            if ($defaultSmsBody !== null) {
                $defaultSmsBody = str_replace(["\r\n", "\r"], "\n", $defaultSmsBody);
            }

            $newAttributes = [
                'default_subject'    => $row['default_subject'] ?? null,
                'default_email_body' => $defaultEmailBody,
                'default_sms_body'   => $defaultSmsBody,
                'default_channel'    => $row['default_channel'],
                'default_call_window' => !empty($row['default_call_window']),
                'sort_order'         => $row['sort_order'] ?? 0,
            ];

            CampaignPurpose::where('id', $row['id'])->update($newAttributes);

            $original = $originalPurposes->get($row['id']);
            if (!$original) {
                continue;
            }

            $oldValues = [];
            $changedValues = [];
            foreach ($newAttributes as $field => $newValue) {
                if ($original->{$field} != $newValue) {
                    $oldValues[$field] = $original->{$field};
                    $changedValues[$field] = $newValue;
                }
            }

            if (!empty($changedValues)) {
                AuditLog::write(
                    'campaign_purpose_updated',
                    $original,
                    null,
                    $oldValues,
                    $changedValues,
                    sprintf(
                        'Campaign purpose "%s" updated (fields changed: %s).',
                        $original->name,
                        implode(', ', array_keys($changedValues))
                    )
                );
            }
        }

        return redirect()
            ->route('admin.settings.campaign-purposes.index')
            ->with('success', 'Campaign purposes updated successfully.');
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();
        if (!$user->hasRole('national_db_administrator')) {
            abort(403);
        }
    }
}
