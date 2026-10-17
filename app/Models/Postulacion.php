<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Postulacion extends Model
{
    protected $table      = 'postulaciones';
    protected $primaryKey = 'id_postulacion';
    public $timestamps    = false;

    protected $fillable = [
        'id_labor',
        'cedula',
        'mensaje',
        'fecha_postulacion',
        'estado',
    ];

    public function publicacion()
    {
        return $this->belongsTo(Publicacion::class, 'id_labor', 'id_labor');
    }

    public function trabajador()
    {
        return $this->belongsTo(User::class, 'cedula', 'cedula');
    }

    public function contratacion()
    {
        return $this->hasOne(Contratacion::class, 'id_postulacion', 'id_postulacion');
    }
}