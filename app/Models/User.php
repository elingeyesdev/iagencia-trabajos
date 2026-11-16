<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles,HasApiTokens;

    /**
     * Get the attributes that should be cast.
     *
    
     * @return array<string, string>
     */

    protected $table = 'usuarios';
protected $primaryKey = 'cedula'; // ← id
public $timestamps = false;
public $incrementing = false;
protected $keyType = 'int';

protected $fillable = [
    'cedula',
    'nombre',
    'email',
    'contrasena',
    'telefono',
    'direccion',
    'ciudad',
    'fechanac',
    'estado',
    'rol_id',
];

    protected $hidden = [
        'contrasena',
    ];
    protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'contrasena' => 'hashed',
    ];
}
    public function getAuthPassword()
    {
        return $this->contrasena;
    }

    /**
     * Indica si el último intento de enviar el correo de verificación falló
     * por el transporte de correo. No se persiste en la base de datos.
     */
    public bool $verificationNotificationFailed = false;

    /**
     * Envía la notificación nativa VerifyEmail sin provocar un 500 si el
     * transporte de correo falla: se registra el fallo (sin datos sensibles)
     * y se marca para que el controlador informe al usuario.
     */
    public function sendEmailVerificationNotification()
    {
        $this->verificationNotificationFailed = false;

        try {
            $this->notify(new VerifyEmail);
        } catch (TransportExceptionInterface $e) {
            $this->verificationNotificationFailed = true;

            Log::warning('No se pudo enviar el correo de verificación.', [
                'exception' => $e::class,
            ]);
        }
    }

    // ── Políticas de validación de usuario (Tareas 1.5 y 1.7) ──
    //
    // Mayoría de edad: regla y mensajes de la fecha de nacimiento, compartidos por el
    // registro web y las APIs de administración.
    // - La fecha de referencia (hoy) se calcula en cada llamada con Carbon::today(),
    //   en la zona horaria de la aplicación; respeta Carbon::setTestNow()/travelTo().
    // - Criterio del 29-feb: se usa subYearsNoOverflow(18). Si hoy es 29-feb y hace
    //   18 años no fue bisiesto, el límite es el 28-feb de ese año (con desbordamiento
    //   sería el 1-mar y se aceptaría a alguien un día antes de cumplir 18). Un nacido
    //   el 29-feb se considera mayor de edad a partir del 1-mar en años no bisiestos.
    //
    // Contraseñas: la regla se define una sola vez en AppServiceProvider mediante
    // Password::defaults(); aquí solo se centralizan los textos en español (mensajes
    // en línea del validador, sin depender del locale ni de un directorio lang/es).

    /** Edad mínima exigida. */
    public const EDAD_MINIMA = 18;

    /** Mensaje de edad para las altas realizadas por un administrador. */
    public const MENSAJE_EDAD_ADMIN = 'El usuario debe tener al menos 18 años.';

    /** Mensaje de edad por defecto (registro propio). */
    public const MENSAJE_EDAD_REGISTRO = 'Debes tener al menos 18 años para registrarte.';

    /**
     * Reglas de validación de la fecha de nacimiento.
     *
     * @return array<int, string>
     */
    public static function reglasFechaNacimiento(): array
    {
        $hoy = Carbon::today();

        return [
            'bail',
            'required',
            'date_format:Y-m-d',
            'before:'.$hoy->copy()->addDay()->toDateString(),
            'before_or_equal:'.$hoy->copy()->subYearsNoOverflow(self::EDAD_MINIMA)->toDateString(),
        ];
    }

    /**
     * Mensajes por campo en español para la fecha de nacimiento.
     *
     * @return array<string, string>
     */
    public static function mensajesFechaNacimiento(string $attribute = 'fechanac', ?string $mensajeEdad = null): array
    {
        return [
            "{$attribute}.required"        => 'La fecha de nacimiento es obligatoria.',
            "{$attribute}.date_format"     => 'La fecha de nacimiento no es una fecha válida.',
            "{$attribute}.before"          => 'La fecha de nacimiento no puede ser una fecha futura.',
            "{$attribute}.before_or_equal" => $mensajeEdad ?? self::MENSAJE_EDAD_REGISTRO,
        ];
    }

    /**
     * Mensajes por campo en español de la política de contraseñas.
     *
     * @return array<string, string>
     */
    public static function mensajesContrasena(string $attribute = 'password'): array
    {
        return [
            "{$attribute}.required"         => 'La contraseña es obligatoria.',
            "{$attribute}.string"           => 'La contraseña debe ser un texto.',
            "{$attribute}.confirmed"        => 'La confirmación de la contraseña no coincide.',
            "{$attribute}.min"              => 'La contraseña debe tener al menos :min caracteres.',
            "{$attribute}.password.mixed"   => 'La contraseña debe contener al menos una letra mayúscula y una minúscula.',
            "{$attribute}.password.numbers" => 'La contraseña debe contener al menos un número.',
            "{$attribute}.password.symbols" => 'La contraseña debe contener al menos un símbolo.',
        ];
    }
}