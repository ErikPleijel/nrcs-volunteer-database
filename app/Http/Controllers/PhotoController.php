<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class PhotoController extends Controller
{
    use \Illuminate\Foundation\Auth\Access\AuthorizesRequests;
    /**
     * Stream a protected photo file to an authenticated, authorized browser.
     *
     * Types: 'profile' | 'passport' | 'signature'
     *
     * The 'context=task_force' query param is a narrow, opt-in broadening of
     * the authorization check for the two task-force pages (show and
     * my-task-force), where photos of cross-branch teammates are legitimately
     * visible per COMPLIANCE.md (photos require authentication, not branch
     * restriction) and the app's existing cross-branch task-force design.
     * Every other referrer (search, profile show, id-cards, etc.) omits the
     * param and gets the unmodified UserPolicy::view() branch/division check.
     */
    public function show(User $user, string $type, Request $request): Response
    {
        if ($request->query('context') === 'task_force') {
            $viewer = $request->user();
            $authorized = Gate::forUser($viewer)->allows('view', $user)
                || Gate::forUser($viewer)->allows('viewAsTaskForceMate', $user);

            abort_unless($authorized, 403);
        } elseif ($request->query('context') === 'red_cross_unit') {
            $viewer = $request->user();
            $authorized = Gate::forUser($viewer)->allows('view', $user)
                || Gate::forUser($viewer)->allows('viewAsUnitMate', $user);

            abort_unless($authorized, 403);
        } elseif ($request->query('context') === 'branch_contact') {
            $viewer = $request->user();
            $authorized = Gate::forUser($viewer)->allows('view', $user)
                || Gate::forUser($viewer)->allows('viewAsBranchContact', $user);

            abort_unless($authorized, 403);
        } else {
            $this->authorize('view', $user);
        }

        $path = $this->resolveStoragePath($user, $type);

        if ($path !== null) {
            // Primary: storage/app/private/ (Storage::disk('local') root on Laravel 12)
            $storageFull = Storage::disk('local')->path($path);
            if (file_exists($storageFull)) {
                return response()->file($storageFull);
            }

            // Backward compat: serve from old public/ location for pre-migration files
            $publicFull = public_path($path);
            if (file_exists($publicFull)) {
                return response()->file($publicFull);
            }
        }

        // DEV FALLBACK: fetches from old production server (nrcsvdb.org).
        // Both photos and signatures are stored under images/pictures/ on the
        // legacy server. Field values are relative paths e.g. images/pictures/abc.jpg
        // Base URL: https://nrcsvdb.org/nrcs/database/
        // Remove after production migration when all files are in storage/app/private/photos/.
        $rawField = $type === 'signature' ? $user->getRawOriginal('signature')
                                          : $user->getRawOriginal('picture');
        if ($rawField) {
            $remoteUrl = 'https://nrcsvdb.org/nrcs/database/' . $rawField;
            $remoteResponse = Http::timeout(5)->get($remoteUrl);
            if ($remoteResponse->successful()) {
                return response($remoteResponse->body(), 200, [
                    'Content-Type' => $remoteResponse->header('Content-Type'),
                ]);
            }
        }

        return $this->placeholder($type);
    }

    private function resolveStoragePath(User $user, string $type): ?string
    {
        return match ($type) {
            // basename() handles both legacy format (images/pictures/file.jpg)
            // and new format (bare filename) after MigrateUserImages has run.
            'profile'   => $user->picture
                             ? 'photos/profile/web/' . basename($user->picture)
                             : null,
            'passport'  => $user->passport_photo
                             ? (dirname($user->passport_photo) === '.'
                                ? $user->passport_photo
                                : 'photos/passport/' . basename($user->passport_photo))
                             : null,
            'signature' => $user->signature
                             ? 'photos/signatures/web/' . basename($user->signature)
                             : null,
            default     => null,
        };
    }

    private function placeholder(string $type): BinaryFileResponse
    {
        $file = $type === 'signature'
            ? public_path('images/placeholders/signature-placeholder.jpg')
            : public_path('images/placeholders/profile-placeholder.png');

        return response()->file($file);
    }
}
