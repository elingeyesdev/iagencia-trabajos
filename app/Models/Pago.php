<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Pago (simulado o externo) de una contratación.
 *
 * Asignación masiva: $fillable contiene SOLO los datos que aporta el contratador.
 * Los campos calculados o de control (porcentaje_comision, monto_comision,
 * monto_neto, estado, es_simulado, proveedor, referencia_externa, cedula_* y
 * fecha_* de auditoría) los fijan las operaciones de dominio de este modelo
 * (registrarPara, confirmar, anular) con forceFill(), tras validar la propiedad
 * y el estado de la contratación.
 *
 * Defensa de dominio (sustituye a CHECK, no portable en el Schema Builder):
 * el evento `saving` lanza DomainException si el registro es incoherente.
 */
class Pago extends Model
{
    public const ESTADO_PENDIENTE  = 'pendiente';
    public const ESTADO_CONFIRMADO = 'confirmado';
    public const ESTADO_ANULADO    = 'anulado';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_CONFIRMADO,
        self::ESTADO_ANULADO,
    ];

    /**
     * Métodos aprobados (CLAUDE.md, Transaccional). Identificadores en minúsculas
     * y sin tilde; etiquetas: efectivo → Efectivo, transferencia → Transferencia,
     * qr → QR, deposito → Depósito, otro → Otro.
     */
    public const METODOS = [
        'efectivo',
        'transferencia',
        'qr',
        'deposito',
        'otro',
    ];

    // ── Proveedor de pagos ──
    //
    // Hoy solo existe el proveedor SIMULADO: no llama a servicios externos, no mueve dinero
    // y no recibe ni guarda datos bancarios; solo genera una referencia interna.
    // Integrar un proveedor real en el futuro implica: añadir su código a PROVEEDORES,
    // generar su referencia en generarReferencia() y fijar es_simulado = false para él.

    public const PROVEEDOR_SIMULADO = 'simulado';

    /** Proveedores soportados (valores admitidos del parámetro `pagos_proveedor`). */
    public const PROVEEDORES = [
        self::PROVEEDOR_SIMULADO,
    ];

    /** Reintentos ante una colisión (muy improbable) de UNIQUE(proveedor, referencia_externa). */
    private const INTENTOS_REFERENCIA = 3;

    protected $table      = 'pagos';
    protected $primaryKey = 'id_pago';
    public $timestamps    = false;

    protected $fillable = [
        'id_contratacion',
        'monto',
        'fecha_pago',
        'metodo',
        'observacion',
    ];

    /** Mismos valores por defecto que la migración, para que la validación los vea. */
    protected $attributes = [
        'porcentaje_comision' => '0.00',
        'monto_comision'      => '0.00',
        'estado'              => self::ESTADO_PENDIENTE,
        'es_simulado'         => true,
        'proveedor'           => 'simulado',
    ];

    protected function casts(): array
    {
        return [
            'monto'               => 'decimal:2',
            'porcentaje_comision' => 'decimal:2',
            'monto_comision'      => 'decimal:2',
            'monto_neto'          => 'decimal:2',
            'fecha_pago'          => 'date',
            'fecha_registro'      => 'datetime',
            'fecha_confirmacion'  => 'datetime',
            'fecha_anulacion'     => 'datetime',
            'es_simulado'         => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Pago $pago) {
            $pago->validarDominio();
        });
    }

    /**
     * Reglas de integridad mínimas del registro.
     *
     * @throws DomainException
     */
    public function validarDominio(): void
    {
        $monto      = self::centavosRedondeados($this->monto);
        $comision   = self::centavosRedondeados($this->monto_comision ?? 0);
        $neto       = $this->monto_neto === null ? null : self::centavosRedondeados($this->monto_neto);
        $porcentaje = (float) ($this->porcentaje_comision ?? 0);

        if ($monto <= 0) {
            throw new DomainException('El monto del pago debe ser mayor que cero.');
        }
        if (! in_array($this->estado, self::ESTADOS, true)) {
            throw new DomainException('Estado de pago no permitido.');
        }
        if (! in_array($this->metodo, self::METODOS, true)) {
            throw new DomainException('Método de pago no permitido.');
        }
        if ($porcentaje < 0 || $porcentaje > 100) {
            throw new DomainException('El porcentaje de comisión debe estar entre 0 y 100.');
        }
        if ($comision < 0 || $comision > $monto) {
            throw new DomainException('La comisión debe estar entre cero y el monto del pago.');
        }
        if ($neto === null || $neto !== $monto - $comision) {
            throw new DomainException('El monto neto debe ser igual al monto menos la comisión.');
        }
        if (trim((string) $this->referencia_externa) === '') {
            throw new DomainException('La referencia de la operación es obligatoria.');
        }
    }

    /**
     * Conversión tolerante a centavos para la defensa de dominio (admite negativos para poder
     * rechazarlos con un mensaje propio). El cálculo de importes usa aCentavos(), estricta.
     */
    private static function centavosRedondeados($valor): int
    {
        return (int) round(((float) $valor) * 100);
    }

    // ── Comisiones: aritmética en CENTAVOS ENTEROS (único punto de cálculo) ──
    //
    // Pagos, reportes y KPIs deben reutilizar estos métodos. Sin float ni bcmath:
    //  - Los importes y porcentajes decimales se convierten a enteros mediante parseo de cadena.
    //    El porcentaje se expresa en centésimas de punto (12.50 % → 1250).
    //  - comisión_centavos = intdiv(monto_centavos * porcentaje_centésimas + 5000, 10000)
    //    (redondeo half-up al centavo; ambos factores son ≥ 0).
    //  - neto_centavos = monto_centavos - comisión_centavos (siempre exacto).
    //  Rango: monto máx. 99 999 999.99 (≈1e10 centavos) × 10 000 ≈ 1e14 < PHP_INT_MAX (64 bits).
    // El porcentaje vigente se lee con Parametro::comisionVigente().

    /**
     * Calcula la comisión y el neto de un monto.
     *
     * @return array{porcentaje_comision: string, monto_comision: string, monto_neto: string}
     *
     * @throws DomainException si el monto no es > 0 o el porcentaje no está entre 0 y 100.
     */
    public static function calcularComision(string|int|float $monto, string $porcentaje): array
    {
        $montoCentavos = self::aCentavos($monto);
        $centesimas    = self::aCentavos($porcentaje);

        if ($montoCentavos <= 0) {
            throw new DomainException('El monto debe ser mayor que cero.');
        }
        if ($centesimas > 10000) {
            throw new DomainException('El porcentaje de comisión debe estar entre 0 y 100.');
        }

        $comision = intdiv($montoCentavos * $centesimas + 5000, 10000);

        return [
            'porcentaje_comision' => self::formatear($centesimas),
            'monto_comision'      => self::formatear($comision),
            'monto_neto'          => self::formatear($montoCentavos - $comision),
        ];
    }

    /**
     * Convierte un importe decimal no negativo con hasta 2 decimales a centavos enteros
     * sin pasar por float cuando llega como cadena o entero ("12.5" → 1250, "7" → 700).
     * Los float (p. ej. valores devueltos por SQLite) se formatean primero a 2 decimales.
     *
     * @throws DomainException si el valor no es un decimal no negativo con ≤ 2 decimales.
     */
    public static function aCentavos(string|int|float|null $valor): int
    {
        if (is_float($valor)) {
            $valor = sprintf('%.2f', $valor);
        }

        $texto = trim((string) $valor);

        if (! preg_match('/^(\d{1,12})(?:\.(\d{1,2}))?$/', $texto, $m)) {
            throw new DomainException('Importe decimal no válido.');
        }

        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }

    /** Formatea centavos (o centésimas) enteros como decimal con 2 cifras ("1250" → "12.50"). */
    public static function formatear(int $centavos): string
    {
        $signo    = $centavos < 0 ? '-' : '';
        $absoluto = abs($centavos);

        return $signo . intdiv($absoluto, 100) . '.' . str_pad((string) ($absoluto % 100), 2, '0', STR_PAD_LEFT);
    }

    // ── Agregados de importes para reportes y KPIs (solo lectura) ──

    /**
     * Expresión SQL portable (SQLite/MySQL) para sumar una columna decimal en centavos enteros
     * (SUM(ROUND(col * 100))): exacta y sin recalcular comisiones (se usan las columnas
     * monto_comision/monto_neto copiadas en cada pago).
     */
    public static function sumaCentavosSql(string $columna): string
    {
        return "COALESCE(SUM(ROUND({$columna} * 100)), 0)";
    }

    /** Centavos agregados por la base (int/float/string) → texto con 2 decimales. */
    public static function centavosATexto($centavos): string
    {
        return self::formatear((int) round((float) $centavos));
    }

    // ── Proveedor y referencia ──

    /** "SIM-" + ULID en mayúsculas (30 caracteres, único y ordenable por tiempo). */
    public static function generarReferenciaSimulada(): string
    {
        return 'SIM-' . Str::upper((string) Str::ulid());
    }

    /**
     * Código del proveedor configurado en `pagos_proveedor` (por defecto 'simulado').
     *
     * @throws HttpResponseException 503 si el proveedor configurado no está soportado.
     */
    public static function proveedorConfigurado(): string
    {
        $codigo = (string) Parametro::valor(Parametro::PAGOS_PROVEEDOR, self::PROVEEDOR_SIMULADO);

        if (! in_array($codigo, self::PROVEEDORES, true)) {
            self::rechazar('El proveedor de pagos configurado no está soportado.', 503);
        }

        return $codigo;
    }

    /** Identificador único de la operación para pagos.referencia_externa (máx. 100 caracteres). */
    private static function generarReferencia(string $proveedor): string
    {
        return match ($proveedor) {
            self::PROVEEDOR_SIMULADO => self::generarReferenciaSimulada(),
        };
    }

    /**
     * Rechazo de negocio con la convención de la API: {"message": "..."} + código HTTP.
     * Lanzado dentro de una transacción, la revierte.
     *
     * @throws HttpResponseException
     */
    public static function rechazar(string $mensaje, int $codigo = 422): never
    {
        throw new HttpResponseException(response()->json(['message' => $mensaje], $codigo));
    }

    // ── Operaciones de dominio (registro, confirmación y anulación) ──

    /**
     * Registra un pago SIMULADO pendiente sobre una contratación del contratador (Tarea 3.3).
     * No mueve dinero real ni contacta pasarelas. Importes en centavos enteros.
     *
     * @param  array  $datos  Solo datos validados: monto, fecha_pago, metodo, observacion.
     *
     * @throws ModelNotFoundException  contratación inexistente (404).
     * @throws HttpResponseException   403 recurso ajeno, 422 estado/monto/referencia, 503 configuración.
     * @throws ValidationException     422 en `monto` si se supera el saldo disponible.
     */
    public static function registrarPara(int $idContratacion, array $datos, User $contratador): self
    {
        for ($intento = 1; ; $intento++) {
            try {
                return DB::transaction(fn () => self::registrarBloqueado($idContratacion, $datos, $contratador));
            } catch (UniqueConstraintViolationException) {
                // La única restricción única de `pagos` es (proveedor, referencia_externa).
                // La transacción ya se revirtió: se reintenta con una referencia nueva.
                if ($intento >= self::INTENTOS_REFERENCIA) {
                    self::rechazar('No se pudo generar una referencia única para el pago. Intente nuevamente.', 422);
                }
            }
        }
    }

    /**
     * Administrador: confirma un pago pendiente (operación simulada, sin movimiento de dinero).
     * Orden de bloqueo idéntico al registro (contratación → pago) para evitar interbloqueos.
     *
     * @throws ModelNotFoundException  pago inexistente (404).
     * @throws HttpResponseException   422 si no está pendiente o la contratación está cancelada.
     */
    public static function confirmar(int $idPago, User $administrador): self
    {
        return DB::transaction(function () use ($idPago, $administrador) {
            [$pago, $contratacion] = self::bloquearConContratacion($idPago);

            if ($pago->estado !== self::ESTADO_PENDIENTE) {
                self::rechazar($pago->estado === self::ESTADO_CONFIRMADO
                    ? 'El pago ya está confirmado.'
                    : 'No se puede confirmar un pago anulado.');
            }
            if ($contratacion === null || $contratacion->estado === 'cancelada') {
                self::rechazar('No se puede confirmar un pago de una contratación cancelada.');
            }

            $pago->forceFill([
                'estado'             => self::ESTADO_CONFIRMADO,
                'cedula_confirma'    => $administrador->cedula,
                'fecha_confirmacion' => now(),
            ])->save();

            return $pago;
        });
    }

    /**
     * Administrador: anula un pago pendiente o confirmado con motivo obligatorio.
     *
     * @throws ModelNotFoundException  pago inexistente (404).
     * @throws HttpResponseException   422 si ya está anulado.
     * @throws ValidationException     422 en `motivo_anulacion` si el motivo está vacío.
     */
    public static function anular(int $idPago, User $administrador, string $motivo): self
    {
        $motivo = trim($motivo);
        if ($motivo === '') {
            throw ValidationException::withMessages(['motivo_anulacion' => 'El motivo de anulación es obligatorio.']);
        }

        return DB::transaction(function () use ($idPago, $administrador, $motivo) {
            [$pago] = self::bloquearConContratacion($idPago);

            if ($pago->estado === self::ESTADO_ANULADO) {
                self::rechazar('El pago ya está anulado.');
            }

            $pago->forceFill([
                'estado'           => self::ESTADO_ANULADO,
                'cedula_anula'     => $administrador->cedula,
                'fecha_anulacion'  => now(),
                'motivo_anulacion' => $motivo,
            ])->save();

            return $pago;
        });
    }

    /**
     * Bloquea la contratación y luego el pago (mismo orden que registrarPara()).
     *
     * @return array{0: Pago, 1: Contratacion|null}
     */
    private static function bloquearConContratacion(int $idPago): array
    {
        $idContratacion = self::query()->whereKey($idPago)->value('id_contratacion');
        if ($idContratacion === null) {
            throw (new ModelNotFoundException())->setModel(self::class, [$idPago]);
        }

        $contratacion = Contratacion::lockForUpdate()->find($idContratacion);
        $pago         = self::lockForUpdate()->findOrFail($idPago);

        return [$pago, $contratacion];
    }

    private static function registrarBloqueado(int $id, array $datos, User $contratador): self
    {
        // Bloqueo de la contratación: serializa pagos concurrentes sobre la misma contratación
        // para que el tope (suma ≤ monto_acordado) se evalúe sobre datos consistentes.
        $contratacion = Contratacion::lockForUpdate()->findOrFail($id);
        $contratacion->load('postulacion.publicacion');

        $publicacion = $contratacion->postulacion?->publicacion;
        if ($publicacion === null || (int) $publicacion->cedula !== (int) $contratador->cedula) {
            self::rechazar('No autorizado.', 403);
        }

        // Se resuelve tras existencia y propiedad: un recurso inexistente o ajeno recibe 404/403,
        // nunca un 503 que revele la configuración.
        $proveedor = self::proveedorConfigurado();

        if (! in_array($contratacion->estado, ['activa', 'finalizada'], true)) {
            self::rechazar($contratacion->estado === 'cancelada'
                ? 'No se pueden registrar pagos en una contratación cancelada.'
                : 'Solo se pueden registrar pagos en contrataciones activas o finalizadas.');
        }

        try {
            $acordado = self::aCentavos($contratacion->monto_acordado);
        } catch (DomainException) {
            $acordado = 0;
        }
        if ($acordado <= 0) {
            self::rechazar('La contratación no tiene un monto acordado válido mayor que cero.');
        }

        // Defensa en profundidad: la validación del controlador ya garantiza el formato; si aun
        // así llega un importe no parseable, se responde 422 en `monto` y nunca 500.
        try {
            $monto = self::aCentavos($datos['monto'] ?? null);
        } catch (DomainException) {
            throw ValidationException::withMessages(['monto' => 'El monto no tiene un formato válido.']);
        }
        if ($monto <= 0) {
            throw ValidationException::withMessages(['monto' => 'El monto debe ser mayor que cero.']);
        }

        $disponible = $acordado - $contratacion->pagadoNoAnuladoCentavos();

        if ($monto > $disponible) {
            throw ValidationException::withMessages([
                'monto' => 'El monto supera el saldo disponible de la contratación ('
                    . self::formatear(max(0, $disponible)) . ').',
            ]);
        }

        try {
            $calculo = self::calcularComision(self::formatear($monto), Parametro::comisionVigente());
        } catch (DomainException) {
            self::rechazar('La configuración de comisión no es válida. Contacte al administrador.', 503);
        }

        $pago = new self();
        $pago->forceFill([
            'id_contratacion'     => $id,
            'monto'               => self::formatear($monto),
            'fecha_pago'          => $datos['fecha_pago'],
            'metodo'              => $datos['metodo'],
            'observacion'         => $datos['observacion'] ?? null,
            'estado'              => self::ESTADO_PENDIENTE,
            'es_simulado'         => $proveedor === self::PROVEEDOR_SIMULADO,
            'proveedor'           => $proveedor,
            'referencia_externa'  => self::generarReferencia($proveedor),
            'porcentaje_comision' => $calculo['porcentaje_comision'],
            'monto_comision'      => $calculo['monto_comision'],
            'monto_neto'          => $calculo['monto_neto'],
            'cedula_registra'     => $contratador->cedula,
            'fecha_registro'      => now(),
        ])->save();

        return $pago->setRelation('contratacion', $contratacion);
    }

    // ── Relaciones ──

    public function contratacion()
    {
        return $this->belongsTo(Contratacion::class, 'id_contratacion', 'id_contratacion');
    }

    public function registrador()
    {
        return $this->belongsTo(User::class, 'cedula_registra', 'cedula');
    }

    public function confirmador()
    {
        return $this->belongsTo(User::class, 'cedula_confirma', 'cedula');
    }

    public function anulador()
    {
        return $this->belongsTo(User::class, 'cedula_anula', 'cedula');
    }

    // ── Scopes ──

    public function scopeNoAnulados(Builder $query): Builder
    {
        return $query->where('estado', '!=', self::ESTADO_ANULADO);
    }

    public function scopeSimulados(Builder $query): Builder
    {
        return $query->where('es_simulado', true);
    }

    public function scopeReales(Builder $query): Builder
    {
        return $query->where('es_simulado', false);
    }
}
