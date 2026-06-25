<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Publicacion extends Model
{
    protected $table      = 'publicaciones';
    protected $primaryKey = 'id_labor';
    public $timestamps    = false;

    protected $fillable = [
        'tipo',
        'titulo',
        'descripcion',
        'pago',
        'lugar',
        'fecha_publicacion',
        'cedula',
        'id_categoria',
        'estado',
    ];

    public function categoria()
    {
        return $this->belongsTo(Categoria::class, 'id_categoria', 'id_categoria');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'cedula', 'cedula');
    }
}