<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Historial de cambios de rol (tabla rol_asignaciones_historial).
 *
 * Registro de SOLO inserción: lo escribe AdminApiController dentro de la misma transacción
 * que el cambio de rol. Las filas no se editan ni se eliminan desde la aplicación
 * (los eventos updating/deleting lo impiden).
 */
class RolAsignacionHistorial extends Model
{
    public const ACCION_ASIGNACION = 'asignacion';
    public const ACCION_CAMBIO     = 'cambio';
    public const ACCION_RETIRADA   = 'retirada';

    public const ACCIONES = [
        self::ACCION_ASIGNACION,
        self::ACCION_CAMBIO,
        self::ACCION_RETIRADA,
    ];

    /** Etiquetas en español para la interfaz. */
    public const ETIQUETAS = [
        self::ACCION_ASIGNACION => 'Asignación',
        self::ACCION_CAMBIO     => 'Cambio',
        self::ACCION_RETIRADA   => 'Retirada',
    ];

    protected $table = 'rol_asignaciones_historial';

    /** Solo created_at (lo fija la BD con useCurrent o el valor explícito); sin updated_at. */
    public $timestamps = false;

    protected $fillable = [
        'usuario_cedula',
        'usuario_nombre',
        'rol_anterior',
        'rol_nuevo',
        'accion',
        'admin_cedula',
        'admin_nombre',
        'ip',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'usuario_cedula' => 'integer',
            'admin_cedula'   => 'integer',
            'created_at'     => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('El historial de asignaciones de roles no se puede modificar.'));
        static::deleting(fn () => throw new LogicException('El historial de asignaciones de roles no se puede eliminar.'));
    }

    public function etiquetaAccion(): string
    {
        return self::ETIQUETAS[$this->accion] ?? (string) $this->accion;
    }
}
