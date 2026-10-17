<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
}