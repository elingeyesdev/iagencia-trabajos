<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Calificacion extends Model
{
    protected $table      = 'calificaciones';
    protected $primaryKey = 'id_calificacion';
    public $timestamps    = false;

    protected $fillable = [
        'id_contratacion',
        'cedula_calificador',
        'cedula_calificado',
        'puntuacion',
        'comentario',
        'fecha',
    ];

    public function contratacion()
    {
        return $this->belongsTo(Contratacion::class, 'id_contratacion', 'id_contratacion');
    }

    public function calificador()
    {
        return $this->belongsTo(User::class, 'cedula_calificador', 'cedula');
    }

    public function calificado()
    {
        return $this->belongsTo(User::class, 'cedula_calificado', 'cedula');
    }
}