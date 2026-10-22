<?php

namespace App\Http\Controllers;

use App\Models\Log as AuditLog;
use Illuminate\Http\Request;

class IdSignatureController extends Controller
{
    // The uploaded file is not in git (.gitignore), so a deploy never overwrites
    // it. Until one is uploaded, ID cards use the placeholder, which is in git.
    public const FILENAME = 'sg-signature.png';

    public const PLACEHOLDER = 'sg-signature-placeholder.png';

    private string $path;
    private string $filename = self::FILENAME;

    public function __construct()
    {
        $this->path = public_path('images/id-card');
    }

    /** URL of the SG signature for ID cards: the uploaded one, else the placeholder. */
    public static function signatureUrl(): string
    {
        $name = file_exists(public_path('images/id-card/'.self::FILENAME)) ? self::FILENAME : self::PLACEHOLDER;

        return asset('images/id-card/'.$name);
    }

    public function index()
    {
        $filePath = $this->path . '/' . $this->filename;
        $signatureExists = file_exists($filePath);

        $data = compact('signatureExists');

        if ($signatureExists) {
            $data['signatureUrl'] = asset('images/id-card/' . $this->filename) . '?v=' . time();
        } else {
            $data['placeholderUrl'] = asset('images/id-card/'.self::PLACEHOLDER);
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
