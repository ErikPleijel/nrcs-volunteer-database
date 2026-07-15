<?php

namespace App\Http\Controllers;

use App\Models\Log as AuditLog;
use App\Models\SignatureTitle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SignatureTitleController extends Controller
{
    public function index()
    {
        $titles = SignatureTitle::orderBy('name')->get();
        return view('signature-titles.index', compact('titles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:60', 'unique:signature_titles,name'],
        ]);

        $signatureTitle = SignatureTitle::create(['name' => $request->name]);

        AuditLog::write(
            'signature_title_created',
            $signatureTitle,
            null,
            null,
            ['name' => $signatureTitle->name],
            "Signature title \"{$signatureTitle->name}\" created."
        );

        return redirect()->route('signature-titles.index')->with('success', 'Signature title added.');
    }

    public function update(Request $request, SignatureTitle $signatureTitle)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('signature_titles', 'name')->ignore($signatureTitle->id)],
        ]);

        $oldName = $signatureTitle->name;

        $signatureTitle->update(['name' => $request->name]);

        if ($oldName !== $signatureTitle->name) {
            AuditLog::write(
                'signature_title_updated',
                $signatureTitle,
                null,
                ['name' => $oldName],
                ['name' => $signatureTitle->name],
                "Signature title updated from \"{$oldName}\" to \"{$signatureTitle->name}\"."
            );
        }

        return redirect()->route('signature-titles.index')->with('success', 'Signature title updated.');
    }

    public function destroy(SignatureTitle $signatureTitle)
    {
        $attributes = $signatureTitle->toArray();

        $signatureTitle->delete();

        AuditLog::write(
            'signature_title_deleted',
            $signatureTitle,
            null,
            $attributes,
            null,
            "Signature title \"{$attributes['name']}\" deleted."
        );

        return redirect()->route('signature-titles.index')->with('success', 'Signature title deleted.');
    }
}
