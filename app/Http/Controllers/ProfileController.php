<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * SRS FR-AUTH-005 - profile management, shared by all roles.
 */
class ProfileController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $data = $request->safe()->except('profile_image');

        if ($request->hasFile('profile_image')) {
            $disk = config('rental.uploads.images.disk');

            if ($user->profile_image) {
                Storage::disk($disk)->delete($user->profile_image);
            }

            $data['profile_image'] = $request->file('profile_image')->store('profile-images', $disk);
        }

        // Changing the email invalidates any previous verification.
        if (array_key_exists('email', $data) && $data['email'] !== $user->email) {
            $user->email_verified_at = null;
        }

        $user->fill($data);
        $user->save();

        if ($user->wasChanged()) {
            $this->auditLogger->recordChanges('user.profile_updated', $user);
        }

        return back()->with('status', __('Profile updated.'));
    }

    /**
     * NFR-SEC-002 - password changes go through the hashing cast, and the
     * current password must be confirmed.
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('updatePassword', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $request->user()->update(['password' => $validated['password']]);

        // NFR-SEC-008 - record that it happened, never the value.
        $this->auditLogger->record('user.password_changed', $request->user());

        return back()->with('status', __('Password updated.'));
    }
}
