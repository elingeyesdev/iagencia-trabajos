<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    protected $table      = 'categorias';
    protected $primaryKey = 'id_categoria';
    public $timestamps    = false;
    protected $fillable = [
        'nombre_categoria',
        'descripcion',
        'estado',
    ];

    public function publicaciones()
    {
        return $this->hasMany(Publicacion::class, 'id_categoria', 'id_categoria');
    }
}