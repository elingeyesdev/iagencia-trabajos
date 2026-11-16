<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class Contratacion extends Model
{
    protected $table      = 'contrataciones';
    protected $primaryKey = 'id_contratacion';
    public $timestamps    = false;

    protected $fillable = [
        'id_postulacion',
        'fecha_inicio',
        'fecha_fin',
        'monto_acordado',
        'estado',
    ];

    public function postulacion()
    {
        return $this->belongsTo(Postulacion::class, 'id_postulacion', 'id_postulacion');
    }

    public function calificaciones()
    {
        return $this->hasMany(Calificacion::class, 'id_contratacion', 'id_contratacion');
    }

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'id_contratacion', 'id_contratacion');
    }

    /**
     * Indica si la contratación tiene pagos registrados (historial financiero),
     * en cualquier estado (también anulados: siguen siendo historial).
     *
     * Se conserva la comprobación de la tabla porque el despliegue (Dockerfile)
     * no ejecuta migraciones: si el código llega antes que la migración de
     * `pagos`, el borrado de contrataciones sin pagos sigue funcionando en vez
     * de fallar con error 500.
     */
    public function tieneHistorialFinanciero(): bool
    {
        if (! Schema::hasTable('pagos')) {
            return false;
        }

        return $this->pagos()->exists();
    }

    // ── Resúmenes financieros de pagos (importes en centavos enteros, ver Pago::aCentavos) ──

    /** Suma en centavos de los pagos no anulados (sin float: cada monto se parsea como cadena). */
    public function pagadoNoAnuladoCentavos(): int
    {
        return Pago::query()
            ->noAnulados()
            ->where('id_contratacion', $this->id_contratacion)
            ->pluck('monto')
            ->reduce(fn (int $total, $monto) => $total + Pago::aCentavos($monto), 0);
    }

    /**
     * Resumen financiero (pagos no anulados) devuelto al registrar un pago.
     *
     * @return array{monto_acordado: string, pagado_no_anulado: string, saldo_disponible: string}
     */
    public function resumenPagos(): array
    {
        $acordado = Pago::aCentavos($this->monto_acordado);
        $pagado   = $this->pagadoNoAnuladoCentavos();

        return [
            'monto_acordado'    => Pago::formatear($acordado),
            'pagado_no_anulado' => Pago::formatear($pagado),
            'saldo_disponible'  => Pago::formatear(max(0, $acordado - $pagado)),
        ];
    }

    /**
     * Resumen ampliado para las consultas (GET) de pagos.
     * Si se pasa la colección de pagos ya cargada, no se ejecuta ninguna consulta adicional.
     *
     * @param  iterable<Pago>|null  $pagos
     * @return array{id_contratacion: int, estado_contratacion: string, monto_acordado: string,
     *               pagado_no_anulado: string, pagado_confirmado: string, pagado_pendiente: string,
     *               saldo_disponible: string, estado_pago: string}
     */
    public function resumenPagosDetallado(?iterable $pagos = null): array
    {
        $pagos ??= Pago::query()
            ->where('id_contratacion', $this->id_contratacion)
            ->get(['id_pago', 'monto', 'estado']);

        $confirmado = 0;
        $pendiente  = 0;
        foreach ($pagos as $pago) {
            $centavos = Pago::aCentavos($pago->monto);
            if ($pago->estado === Pago::ESTADO_CONFIRMADO) {
                $confirmado += $centavos;
            } elseif ($pago->estado === Pago::ESTADO_PENDIENTE) {
                $pendiente += $centavos;
            }
        }

        try {
            $acordado = Pago::aCentavos($this->monto_acordado);
        } catch (DomainException) {
            $acordado = 0;
        }

        $noAnulado = $confirmado + $pendiente;

        return [
            'id_contratacion'     => (int) $this->id_contratacion,
            'estado_contratacion' => (string) $this->estado,
            'monto_acordado'      => Pago::formatear($acordado),
            'pagado_no_anulado'   => Pago::formatear($noAnulado),
            'pagado_confirmado'   => Pago::formatear($confirmado),
            'pagado_pendiente'    => Pago::formatear($pendiente),
            'saldo_disponible'    => Pago::formatear(max(0, $acordado - $noAnulado)),
            'estado_pago'         => match (true) {
                $noAnulado <= 0                          => 'sin_pagos',
                $acordado > 0 && $noAnulado >= $acordado => 'completo',
                default                                  => 'parcial',
            },
        ];
    }
}