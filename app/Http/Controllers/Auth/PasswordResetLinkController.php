<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        // Si el transporte de correo falla (p. ej. SMTP bloqueado o mal configurado en producción) no se
        // devuelve un 500: se registra el fallo sin datos personales y se informa al usuario.
        try {
            $status = Password::sendResetLink(
                $request->only('email')
            );
        } catch (TransportExceptionInterface $e) {
            Log::warning('No se pudo enviar el correo de recuperación de contraseña.', [
                'exception' => $e::class,
            ]);

            return back()->withInput($request->only('email'))
                ->withErrors(['email' => 'No pudimos enviar el correo de recuperación en este momento. Inténtalo de nuevo más tarde.']);
        }

        return $status == Password::RESET_LINK_SENT
                    ? back()->with('status', __($status))
                    : back()->withInput($request->only('email'))
                        ->withErrors(['email' => __($status)]);
    }
}
