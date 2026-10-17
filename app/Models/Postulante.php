<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Postulante extends Model
{
    protected $table      = 'postulantes';
    protected $primaryKey = 'id_postulante';
    public $timestamps    = false;

    protected $fillable = [
        'cedula',
        'habilidades',
        'experiencia',
        'disponibilidad',
        'tarifa',
        'estado',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'cedula', 'cedula');
    }
}