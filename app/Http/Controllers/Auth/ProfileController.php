<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $user = $request->user();
        $validated = $request->validated();

        // El formulario envía `name`; la columna real es `nombre`.
        // Solo se asignan columnas reales y permitidas (nunca cedula, estado, rol ni contrasena).
        $user->nombre = $validated['name'];
        $user->email = $validated['email'];

        // Si cambia el correo, la verificación anterior deja de ser válida.
        $emailCambiado = $user->isDirty('email');

        if ($emailCambiado) {
            $user->email_verified_at = null;
        }

        $user->save();

        $redirect = Redirect::route('profile.edit')->with('status', 'profile-updated');

        if ($emailCambiado) {
            $user->sendEmailVerificationNotification();

            if ($user->verificationNotificationFailed) {
                $redirect->with('verification-link-failed', true);
            }
        }

        return $redirect;
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
