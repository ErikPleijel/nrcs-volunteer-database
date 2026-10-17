<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Traits\HandlesImageUploads;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * users/{user}/images: an admin uploads someone's photo or signature. A page of
 * its own (not users/edit) so it has its own check, UserPolicy::uploadImages():
 * everyone who may edit the person, plus division DB assistants holding the
 * "Can upload photos and signatures" tick box (own division, no admin roles).
 * People upload their own on My Profile (ProfileController).
 *
 * A new image clears an earlier "upload again" rejection: User::booted().
 */
class UserImageController extends Controller
{
    use AuthorizesRequests, HandlesImageUploads;

    public function edit(User $user): View|RedirectResponse
    {
        if ($user->anonymized_at !== null) {
            return redirect()->route('users.show', $user);
        }

        $this->authorize('uploadImages', $user);

        return view('users.images', compact('user'));
    }

    /**
     * Update the user's profile picture (file upload or camera capture).
     */
    public function updatePhoto(Request $request, User $user): RedirectResponse
    {
        if ($user->anonymized_at !== null) {
            return redirect()->route('users.show', $user);
        }

        $this->authorize('uploadImages', $user);

        $request->validate([
            'picture' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'captured_photo' => ['nullable', 'string'], // For base64 captured photos
        ]);

        $pictureFilename = $this->processPhotoUpload($request, 'profile', 'picture', 'captured_photo');

        if ($pictureFilename) {
            // Delete old photo files if a new one is successfully uploaded/captured
            if ($user->picture) {
                $this->deleteUserImage($user->picture, 'profile');
            }

            $user->picture = $pictureFilename;
            $user->image_upload_date = now();
            $user->save();

            return back()->with('success', 'Profile picture updated successfully.');
        }

        // If no file was uploaded and no photo was captured, or if there was an error
        return back()->with('error', 'Photo uploaded but could not be processed — please try a different image or contact support.');
    }

    /**
     * Update the user's signature (file upload or camera capture).
     */
    public function updateSignature(Request $request, User $user): RedirectResponse
    {
        if ($user->anonymized_at !== null) {
            return redirect()->route('users.show', $user);
        }

        $this->authorize('uploadImages', $user);

        $request->validate([
            'signature_file' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:1024'],
            'captured_signature' => ['nullable', 'string'], // For base64 captured signatures
        ]);

        $signatureFilename = $this->processPhotoUpload($request, 'signatures', 'signature_file', 'captured_signature');

        if ($signatureFilename) {
            // Delete old signature files if a new one is successfully uploaded/captured
            if ($user->signature) {
                $this->deleteUserImage($user->signature, 'signatures');
            }
            $user->signature = $signatureFilename;
            $user->save();

            return back()->with('success', 'Signature updated successfully.');
        }

        return back()->with('error', 'Signature uploaded but could not be processed — please try a different image or contact support.');
    }
}
