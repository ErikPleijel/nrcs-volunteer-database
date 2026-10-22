<?php

namespace App\Http\Controllers;

use App\Services\ProfileResources;
use Illuminate\Support\Facades\Storage;

/**
 * Serves the files uploaded under Settings → Useful Links & Downloads to
 * any logged-in user. The file is looked up by its download row's id, so
 * the path never comes from the request. PDFs open in the browser; Word
 * and Excel files are downloaded.
 */
class ProfileDownloadController extends Controller
{
    public function show(string $id)
    {
        $download = ProfileResources::findFile($id);
        $disk = Storage::disk(ProfileResources::DISK);

        abort_if($download === null || ! $disk->exists($download['path']), 404);

        $extension = strtolower(pathinfo($download['path'], PATHINFO_EXTENSION));
        $name = $download['file_name'] ?: basename($download['path']);

        return $extension === 'pdf'
            ? $disk->response($download['path'], $name)
            : $disk->download($download['path'], $name);
    }
}
