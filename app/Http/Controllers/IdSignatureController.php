<?php

namespace App\Http\Controllers;

use App\Models\Log as AuditLog;
use Illuminate\Http\Request;

class IdSignatureController extends Controller
{
    private string $path;
    private string $filename = 'sg-signature.png';

    public function __construct()
    {
        $this->path = public_path('images/id-card');
    }

    public function index()
    {
        $filePath = $this->path . '/' . $this->filename;
        $signatureExists = file_exists($filePath);

        $data = compact('signatureExists');

        if ($signatureExists) {
            $data['signatureUrl'] = asset('images/id-card/' . $this->filename) . '?v=' . time();
        }

        return view('settings.id-signature', $data);
    }

    public function store(Request $request)
    {
        $request->validate([
            'signature' => ['required', 'file', 'mimes:png', 'max:2048'],
        ]);

        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }

        $file = $request->file('signature');
        $originalFilename = $file->getClientOriginalName();
        $size = $file->getSize();
        $mimeType = $file->getClientMimeType();

        $file->move($this->path, $this->filename);

        AuditLog::write(
            'id_signature_replaced',
            null,
            null,
            null,
            [
                'original_filename' => $originalFilename,
                'size' => $size,
                'mime_type' => $mimeType,
            ],
            'ID card signature image replaced.'
        );

        return redirect()->route('admin.settings.id-signature.index')
            ->with('success', 'Signature uploaded successfully.');
    }
}
