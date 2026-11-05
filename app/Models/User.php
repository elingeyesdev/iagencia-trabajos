<?php

namespace App\Models;

//use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable //implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasRoles,HasApiTokens;

    /**
     * Get the attributes that should be cast.
     *
    
     * @return array<string, string>
     */

    protected $table = 'usuarios';
protected $primaryKey = 'cedula'; // ← id
public $timestamps = false;
public $incrementing = false;
protected $keyType = 'int';

protected $fillable = [
    'cedula',
    'nombre',
    'email',
    'contrasena',
    'telefono',
    'direccion',
    'ciudad',
    'fechanac',
    'estado',
    'rol_id',
];

    protected $hidden = [
        'contrasena',
    ];
    protected function casts(): array
{
    return [
        'email_verified_at' => 'datetime',
        'contrasena' => 'hashed',
    ];
}
    public function getAuthPassword()
    {
        return $this->contrasena;
    }
}