<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;

/**
 * Parámetro del sistema (clave/valor), p. ej. `comision_porcentaje`, `pagos_proveedor`.
 *
 * `fecha_actualizacion` y `cedula_actualiza` no son asignables en masa: los fijará
 * el futuro endpoint de administración con forceFill.
 */
class Parametro extends Model
{
    /** Clave del porcentaje de comisión de RedWork (copiado en cada pago). */
    public const COMISION_PORCENTAJE = 'comision_porcentaje';

    /** Clave del proveedor de pagos configurado (ver Pago::PROVEEDORES). */
    public const PAGOS_PROVEEDOR = 'pagos_proveedor';

    protected $table      = 'parametros';
    protected $primaryKey = 'id_parametro';
    public $timestamps    = false;

    protected $fillable = [
        'clave',
        'valor',
        'descripcion',
    ];

    protected function casts(): array
    {
        return [
            'fecha_actualizacion' => 'datetime',
        ];
    }

    /**
     * Devuelve el valor (string) de un parámetro o $default si no existe.
     */
    public static function valor(string $clave, $default = null)
    {
        $valor = static::query()->where('clave', $clave)->value('valor');

        return $valor ?? $default;
    }

    /**
     * Porcentaje de comisión vigente normalizado a 2 decimales (p. ej. "0.00", "12.50").
     * Si el parámetro no existe se usa "0.00". Aritmética en centésimas enteras (Pago::aCentavos).
     *
     * @throws DomainException si el parámetro no es un número decimal entre 0 y 100
     *         (configuración inválida: no se debe registrar ningún pago con él).
     */
    public static function comisionVigente(): string
    {
        $valor = static::valor(self::COMISION_PORCENTAJE, '0.00');

        try {
            $centesimas = Pago::aCentavos((string) $valor);
        } catch (DomainException) {
            throw new DomainException('El porcentaje de comisión configurado no es un número válido.');
        }

        if ($centesimas > 10000) {
            throw new DomainException('El porcentaje de comisión configurado debe estar entre 0 y 100.');
        }

        return Pago::formatear($centesimas);
    }
}
