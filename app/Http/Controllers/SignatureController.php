<?php

namespace App\Http\Controllers;

use App\Models\Log as AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class SignatureController extends Controller
{
    private string $signaturesPath;

    public function __construct()
    {
        $this->signaturesPath = public_path('images/signatures');
    }

    public function index()
    {
        $files = [];

        if (is_dir($this->signaturesPath)) {
            foreach (glob($this->signaturesPath . '/*.png') as $path) {
                $files[] = basename($path);
            }
        }

        return view('settings.signatures', compact('files'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'signature' => ['required', 'file', 'mimes:png', 'max:2048'],
        ]);

        $file     = $request->file('signature');
        $filename = $file->getClientOriginalName();

        if (!is_dir($this->signaturesPath)) {
            mkdir($this->signaturesPath, 0755, true);
        }

        $file->move($this->signaturesPath, $filename);

        AuditLog::write(
            'signature_image_added',
            null,
            null,
            null,
            ['filename' => $filename],
            "Signature image \"{$filename}\" added."
        );

        return redirect()->route('admin.settings.signatures.index')
            ->with('success', "Signature \"{$filename}\" uploaded.");
    }

    public function destroy(string $filename)
    {
        $target = realpath($this->signaturesPath . '/' . $filename);
        $base   = realpath($this->signaturesPath);

        if (!$target || !$base || !str_starts_with($target, $base . DIRECTORY_SEPARATOR)) {
            return redirect()->route('admin.settings.signatures.index')
                ->with('error', 'File not found.');
        }

        if (!is_file($target)) {
            return redirect()->route('admin.settings.signatures.index')
                ->with('error', 'File not found.');
        }

        unlink($target);

        AuditLog::write(
            'signature_image_removed',
            null,
            null,
            ['filename' => $filename],
            null,
            "Signature image \"{$filename}\" removed."
        );

        return redirect()->route('admin.settings.signatures.index')
            ->with('success', "Signature \"{$filename}\" deleted.");
    }
}
