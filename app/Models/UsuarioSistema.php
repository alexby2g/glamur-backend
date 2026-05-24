<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class UsuarioSistema extends Model
{
    use HasFactory;

    protected $table = 'usuario_sistemas';

    protected $fillable = [
        'nombre',
        'usuario',
        'password',
        'token',
        'activo',
        'ultimo_acceso',
    ];

    protected $hidden = [
        'password',
        'token',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'ultimo_acceso' => 'datetime',
    ];

    public function setPasswordAttribute($value)
    {
        if ($value && Hash::needsRehash($value)) {
            $this->attributes['password'] = Hash::make($value);
        } else {
            $this->attributes['password'] = $value;
        }
    }
}