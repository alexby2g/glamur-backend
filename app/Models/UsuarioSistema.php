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
    'rol',
    'empleado_id',
    'foto_perfil',
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

    public function empleado()
    {
        return $this->belongsTo(\App\Models\Empleado::class)->withTrashed();
    }

    public function tokensMoviles()
    {
        return $this->hasMany(UsuarioSistemaToken::class, 'usuario_sistema_id');
    }

    public function setPasswordAttribute($value)
    {
        if ($value && Hash::needsRehash($value)) {
            $this->attributes['password'] = Hash::make($value);
        } else {
            $this->attributes['password'] = $value;
        }
    }
}
